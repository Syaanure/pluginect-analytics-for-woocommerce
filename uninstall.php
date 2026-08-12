<?php
/**
 * Désinstallation.
 *
 * Les statistiques sont conservées par défaut afin qu'une suppression
 * accidentelle ou une réinstallation ne détruise pas des années de données.
 * L'effacement complet n'a lieu que si l'administrateur l'a demandé dans
 * Réglages > Données avant de supprimer l'extension.
 *
 * À savoir : WordPress n'exécute ce fichier que sur une SUPPRESSION, pas
 * sur une désactivation. Désactiver l'extension conserve donc l'intégralité
 * des données — c'est voulu, on désactive souvent pour diagnostiquer.
 *
 * Les métadonnées d'attribution posées sur les commandes (_cbaz_source,
 * _cbaz_campaign…) sont retirées elles aussi : elles appartiennent à
 * cette extension, et les garder polluerait les commandes sans que rien
 * ne sache plus les lire.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$settings = get_option( 'cbaz_settings', [] );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

// ── Tables ──────────────────────────────────────────────────────
foreach ( [ 'daily_dim', 'daily', 'events', 'views', 'sessions', 'campaigns' ] as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cbaz_' . $table );
}

// ── Réglages et mémoire interne ─────────────────────────────────
foreach ( [
	'cbaz_settings',
	'cbaz_db_version',
	'cbaz_saved_at',
	'cbaz_secret',
	'cbaz_sources_canon',
	'cbaz_rollup_upto',
	'cbaz_favoris',
] as $option ) {
	delete_option( $option );
}

// ── Tâche de nuit ───────────────────────────────────────────────
wp_clear_scheduled_hook( 'cbaz_daily_purge' );

/*
 * Transients : le sel du jour, le verrou de consolidation et les
 * compteurs de débit. Ils portent tous notre préfixe, et une requête
 * directe est ici plus sûre qu'une liste écrite à la main — un
 * compteur oublié resterait douze mois en base.
 */
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_cbaz\_%'
	    OR option_name LIKE '\_transient\_timeout\_cbaz\_%'"
);

// ── Attribution recopiée sur les commandes ──────────────────────
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_cbaz\_%'" );

// Stockage moderne des commandes WooCommerce, s'il est en place.
$hpos = $wpdb->prefix . 'wc_orders_meta';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
	$wpdb->query( "DELETE FROM {$hpos} WHERE meta_key LIKE '\_cbaz\_%'" );
}
