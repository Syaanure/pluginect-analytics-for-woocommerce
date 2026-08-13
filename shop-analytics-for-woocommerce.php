<?php
/**
 * Plugin Name: Shop Analytics for WooCommerce
 * Description: Mesure locale d'audience et de ventes pour WooCommerce, sans service tiers ni cookie par défaut.
 * Version:     4.31.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author:      Pluginect
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shop-analytics-for-woocommerce
 * Domain Path: /languages
 *
 * L'adresse IP sert une fraction de seconde à calculer un identifiant
 * pseudonyme quotidien, puis elle est oubliée. Un seul cookie est possible,
 * facultatif et sans identifiant de visite :
 * la mémoire d'attribution, qui ne retient que la provenance d'une
 * visite — voir includes/track.php pour le détail.
 */

defined( 'ABSPATH' ) || exit;

define( 'CBAZ_VERSION', '4.31.1' );
define( 'CBAZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBAZ_URL', plugin_dir_url( __FILE__ ) );

add_action( 'init', 'cbaz_load_textdomain' );
/** Charge les traductions après l'initialisation de WordPress. */
function cbaz_load_textdomain() {
	$domain = 'shop-analytics-for-woocommerce';
	$locale = determine_locale();
	$file   = CBAZ_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

	load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Les variantes anglaises non livrées utilisent le catalogue anglais commun.
	if ( ! is_readable( $file ) && 0 === strpos( $locale, 'en_' ) ) {
		$file = CBAZ_DIR . 'languages/' . $domain . '-en_US.mo';
	}

	// Le chargement explicite garantit les catalogues embarqués, y compris depuis WP 6.7.
	if ( is_readable( $file ) ) {
		unload_textdomain( $domain, true );
		load_textdomain( $domain, $file );
	}
}

/** Durée d'inactivité au-delà de laquelle une nouvelle visite commence. */
const CBAZ_SESSION_GAP = 30 * MINUTE_IN_SECONDS;

require_once CBAZ_DIR . 'includes/features.php';
require_once CBAZ_DIR . 'includes/db.php';
require_once CBAZ_DIR . 'includes/orders.php';
require_once CBAZ_DIR . 'includes/track.php';
require_once CBAZ_DIR . 'includes/query.php';
require_once CBAZ_DIR . 'includes/history.php';
require_once CBAZ_DIR . 'includes/campaigns.php';
require_once CBAZ_DIR . 'includes/geo.php';
require_once CBAZ_DIR . 'includes/filters.php';
require_once CBAZ_DIR . 'includes/admin.php';

/*
 * Point d'accroche du module Pro.
 *
 * Il se branche ici plutôt que sur `plugins_loaded` : à cet instant toutes
 * les fonctions du cœur existent, et l'ordre de chargement des extensions
 * n'a plus d'importance.
 */
add_action( 'plugins_loaded', 'cbaz_loaded', 20 );
function cbaz_loaded() {
	/**
	 * Signale que le cœur est prêt.
	 *
	 * @param int $api Version de la surface d'extension.
	 */
	do_action( 'cbaz_ready', CBAZ_API );
}

/*
 * Déclaration de compatibilité avec le stockage moderne des commandes.
 * Sans elle, WooCommerce signale l'extension comme incompatible et
 * refuse d'activer ce stockage — même quand tout fonctionne.
 */
add_action( 'before_woocommerce_init', 'cbaz_declare_compat' );
function cbaz_declare_compat() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

// ══════════════════════════════════════════════════════════════
//  RÉGLAGES
// ══════════════════════════════════════════════════════════════

function cbaz_defaults() {
	return [
		'enabled'          => 1,
		/*
		 * Conservation du DÉTAIL — les visites une à une, leurs pages,
		 * leurs évènements. Deux ans : au-delà, personne ne relit le
		 * parcours d'une visite, et il pèse lourd.
		 *
		 * Les chiffres, eux, ne périment pas : ils partent dans
		 * l'historique consolidé ci-dessous et survivent à cette purge.
		 * Voir includes/history.php.
		 */
		'retention_months' => 24,
		/*
		 * Conservation de l'HISTORIQUE — un résumé par jour, sans la
		 * moindre visite individuelle. Dix ans tiennent dans ce qu'une
		 * semaine de détail occupe, et c'est ce qui permet de comparer
		 * une rentrée à celle d'il y a trois ans.
		 */
		'history_years'    => 10,
		'exclude_roles'    => [ 'administrator', 'shop_manager' ],
		'exclude_paths'    => "/wp-admin\n/checkout-pay",
		'track_events'     => 1,
		/*
		 * Les robots ne sont pas des clients. Un aspirateur de contenu
		 * ou une sonde de supervision qui passe toutes les minutes
		 * gonfle les visites sans rien acheter, et fausse d'autant le
		 * taux de conversion — le seul chiffre qui compte vraiment.
		 */
		'exclude_bots'     => 1,
		/*
		 * Zéro par défaut : AUCUN cookie n'est déposé.
		 *
		 * Une valeur supérieure active la mémoire d'attribution, qui
		 * rattache un achat au clic de campagne qui l'a précédé même
		 * plusieurs jours avant — au prix d'un cookie de provenance.
		 * C'est un choix à faire en connaissance de cause, pas un
		 * réglage qu'on subit à l'installation.
		 */
		'attribution_days' => 0,
		/* La suppression des statistiques exige un choix explicite. */
		'delete_on_uninstall' => 0,
	];
}

function cbaz_opt( $key = null ) {
	static $opts = null;

	if ( null === $opts ) {
		$opts = wp_parse_args( get_option( 'cbaz_settings', [] ), cbaz_defaults() );
	}

	return null === $key ? $opts : ( $opts[ $key ] ?? null );
}

/**
 * Qui a le droit de déclencher une tâche de maintenance ?
 *
 * Les deux capacités plutôt qu'une seule : « manage_woocommerce »
 * n'existe plus si l'extension WooCommerce est désactivée, et s'y fier
 * seule bloquerait alors la mise à jour du schéma — une panne
 * silencieuse, découverte des semaines plus tard.
 */
function cbaz_can_maintain() {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

// ══════════════════════════════════════════════════════════════
//  INSTALLATION
// ══════════════════════════════════════════════════════════════

register_activation_hook( __FILE__, 'cbaz_activate' );
function cbaz_activate() {
	cbaz_install_tables();

	if ( ! wp_next_scheduled( 'cbaz_daily_purge' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cbaz_daily_purge' );
	}

	// Les liens courts /go/... passent par une règle de réécriture,
	// inactive tant que les permaliens n'ont pas été régénérés.
	cbaz_short_link_rule();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'cbaz_deactivate' );
function cbaz_deactivate() {
	wp_clear_scheduled_hook( 'cbaz_daily_purge' );
	flush_rewrite_rules();
}

/*
 * Le schéma peut évoluer d'une version à l'autre ; on le compare à
 * chaque chargement d'administration, jamais côté public — dbDelta est
 * coûteux et n'a rien à faire dans le chemin d'une page vue.
 */
add_action( 'admin_init', 'cbaz_maybe_upgrade' );
function cbaz_maybe_upgrade() {
	global $wpdb;

	if ( ! cbaz_can_maintain() ) {
		return;
	}

	$installed = get_option( 'cbaz_db_version' );
	if ( $installed === CBAZ_VERSION ) {
		return;
	}

	cbaz_install_tables();

	/* 4.31.1 corrige la source de vérité des commandes/remboursements.
	 * Rejouer les jours dont le détail existe encore évite de conserver
	 * les anciens agrégats commerciaux après la mise à niveau. */
	if ( $installed && version_compare( (string) $installed, '4.31.1', '<' ) ) {
		$sessions = cbaz_table( 'sessions' );
		$earliest = $wpdb->get_var( "SELECT MIN(DATE(started_at)) FROM {$sessions}" );
		if ( $earliest ) {
			update_option( 'cbaz_rollup_upto', $earliest, false );
		}
	}

	update_option( 'cbaz_db_version', CBAZ_VERSION, false );
}

// ══════════════════════════════════════════════════════════════
//  RATTRAPAGE DES SOURCES
//
//  Les visites déjà mesurées portent l'écriture brute du référent :
//  « l.instagram.com » ici, « instagram.com » là, « ig » ailleurs. On
//  réécrit une bonne fois pour toutes, sinon l'historique reste éclaté
//  en trois lignes de rapport alors que les nouvelles visites, elles,
//  arrivent déjà normalisées.
// ══════════════════════════════════════════════════════════════

const CBAZ_CANON_VERSION = '1';

add_action( 'admin_init', 'cbaz_maybe_canon_sources', 20 );
function cbaz_maybe_canon_sources() {
	// admin_init se déclenche aussi sur admin-ajax.php, donc pour des
	// comptes sans aucun droit sur les statistiques. Une réécriture de
	// table n'a pas à partir de là.
	if ( ! cbaz_can_maintain() ) {
		return;
	}

	if ( get_option( 'cbaz_sources_canon' ) === CBAZ_CANON_VERSION ) {
		return;
	}

	global $wpdb;

	$table = cbaz_table( 'sessions' );

	foreach ( [ 'source', 'first_source' ] as $colonne ) {
		$valeurs = $wpdb->get_col( "SELECT DISTINCT {$colonne} FROM {$table} WHERE {$colonne} <> ''" );

		foreach ( $valeurs as $brut ) {
			$propre = cbaz_canon_source( $brut );

			if ( $propre !== $brut ) {
				$wpdb->update( $table, [ $colonne => $propre ], [ $colonne => $brut ] );
			}
		}
	}

	// Un référent absent n'est pas un support manquant : c'est du direct.
	$wpdb->update( $table, [ 'medium' => 'direct' ], [ 'medium' => '(none)' ] );

	update_option( 'cbaz_sources_canon', CBAZ_CANON_VERSION, false );
}

// ══════════════════════════════════════════════════════════════
//  PURGE
// ══════════════════════════════════════════════════════════════

add_action( 'cbaz_daily_purge', 'cbaz_purge_old_data' );
function cbaz_purge_old_data() {
	global $wpdb;

	/*
	 * Résumer AVANT d'effacer. Un jour supprimé sans avoir été
	 * consolidé serait perdu deux fois — et la perte ne se verrait
	 * qu'au moment où l'on voudrait comparer, des années plus tard.
	 */
	cbaz_rollup_pending();

	$months = max( 1, (int) cbaz_opt( 'retention_months' ) );
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

	/*
	 * Garde-fou : le rattrapage traite un nombre borné de journées par
	 * nuit. Sur une base qui a des années de détail non consolidé, la
	 * purge doit attendre que la consolidation l'ait rejointe, sinon
	 * elle effacerait plus vite qu'on ne résume.
	 */
	$frontier = cbaz_rollup_frontier() . ' 00:00:00';

	if ( $frontier < $cutoff ) {
		$cutoff = $frontier;
	}

	$sessions = cbaz_table( 'sessions' );
	$views    = cbaz_table( 'views' );
	$events   = cbaz_table( 'events' );

	// Les lignes filles d'abord : une vue orpheline serait invisible
	// dans les rapports mais continuerait d'occuper la table.
	$wpdb->query( $wpdb->prepare( "DELETE v FROM {$views} v INNER JOIN {$sessions} s ON s.id = v.session_id WHERE s.started_at < %s", $cutoff ) );
	$wpdb->query( $wpdb->prepare( "DELETE e FROM {$events} e INNER JOIN {$sessions} s ON s.id = e.session_id WHERE s.started_at < %s", $cutoff ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$sessions} WHERE started_at < %s", $cutoff ) );

	// L'historique consolidé a sa propre échéance, bien plus lointaine.
	$years = max( 1, (int) cbaz_opt( 'history_years' ) );
	$limit = gmdate( 'Y-m-d', strtotime( "-{$years} years" ) );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . cbaz_table( 'daily' ) . ' WHERE day < %s', $limit ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . cbaz_table( 'daily_dim' ) . ' WHERE day < %s', $limit ) );
}

/*
 * La consolidation ne peut pas attendre la seule tâche de nuit : sur
 * une installation existante, il y a des mois de détail à résumer, et
 * la purge n'avancera pas tant que ce n'est pas fait. On rattrape donc
 * aussi, par petites tranches, à l'ouverture de l'administration.
 */
add_action( 'admin_init', 'cbaz_catch_up_rollup', 30 );
function cbaz_catch_up_rollup() {
	if ( ! cbaz_can_maintain() ) {
		return;
	}

	if ( get_transient( 'cbaz_rollup_lock' ) || cbaz_rollup_frontier() > cbaz_yesterday() ) {
		return;
	}

	set_transient( 'cbaz_rollup_lock', 1, 5 * MINUTE_IN_SECONDS );
	cbaz_rollup_pending( 40 );
}
