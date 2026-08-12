<?php
/**
 * Campagnes.
 *
 * Deux moitiés qui se rejoignent : on compose ici les liens à diffuser,
 * et on mesure ce qu'ils rapportent réellement.
 *
 * Choix structurant : le chiffre d'affaires d'une campagne est lu SUR
 * LES COMMANDES, pas sur les visites. Trois raisons. Les commandes ne
 * sont jamais purgées, donc une opération d'il y a deux ans reste
 * comparable. Elles connaissent les remboursements, que la table des
 * visites ignore — sans quoi une campagne suivie de retours paraîtrait
 * brillante. Et elles portent le montant exact encaissé, pas une copie
 * qui finirait par diverger.
 */

defined( 'ABSPATH' ) || exit;

// ══════════════════════════════════════════════════════════════
//  FICHES
// ══════════════════════════════════════════════════════════════

function cbaz_campaigns( $status = '', $limit = 200 ) {
	global $wpdb;

	$t     = cbaz_table( 'campaigns' );
	$where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';

	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} {$where} ORDER BY created_at DESC LIMIT %d", $limit ) );
}

function cbaz_campaign( $id ) {
	global $wpdb;

	$t = cbaz_table( 'campaigns' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) );
}

function cbaz_campaign_by_slug( $slug ) {
	global $wpdb;

	$t = cbaz_table( 'campaigns' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", $slug ) );
}

function cbaz_statuses() {
	return [
		'active'  => 'En cours',
		'planned' => 'Planifiée',
		'ended'   => 'Terminée',
	];
}

/**
 * Normalise une valeur UTM.
 *
 * Minuscules et tirets : « Instagram », « instagram » et « INSTAGRAM »
 * doivent compter pour un seul canal, sinon le rapport se disperse en
 * trois lignes pour une même réalité.
 */
function cbaz_slug_utm( $value ) {
	$value = remove_accents( (string) $value );
	$value = strtolower( trim( $value ) );
	$value = preg_replace( '/[^a-z0-9._-]+/', '-', $value );

	return trim( substr( $value, 0, 120 ), '-' );
}

function cbaz_save_campaign( array $data, $id = 0 ) {
	global $wpdb;

	$row = [
		'label'        => sanitize_text_field( $data['label'] ?? '' ),
		'status'       => isset( cbaz_statuses()[ $data['status'] ?? '' ] ) ? $data['status'] : 'active',
		'source'       => cbaz_slug_utm( $data['source'] ?? '' ),
		'medium'       => cbaz_slug_utm( $data['medium'] ?? '' ),
		'campaign'     => cbaz_slug_utm( $data['campaign'] ?? '' ),
		'term'         => cbaz_slug_utm( $data['term'] ?? '' ),
		'content'      => cbaz_slug_utm( $data['content'] ?? '' ),
		'target_url'   => esc_url_raw( $data['target_url'] ?? home_url( '/' ) ),
		'cost'         => round( (float) str_replace( ',', '.', (string) ( $data['cost'] ?? 0 ) ), 2 ),
		'goal_revenue' => round( (float) str_replace( ',', '.', (string) ( $data['goal_revenue'] ?? 0 ) ), 2 ),
		'starts_on'    => cbaz_clean_date( $data['starts_on'] ?? '' ),
		'ends_on'      => cbaz_clean_date( $data['ends_on'] ?? '' ),
		'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
	];

	if ( '' === $row['campaign'] || '' === $row['source'] ) {
		return new WP_Error( 'cbaz_incomplete', 'Une campagne a besoin au minimum d’une source et d’un nom de campagne.' );
	}

	$t = cbaz_table( 'campaigns' );

	/*
	 * Un nom de campagne, une fiche.
	 *
	 * Les statistiques sont regroupées par NOM de campagne : deux
	 * fiches portant le même nom pointeraient sur les mêmes chiffres et
	 * apparaîtraient en double dans la liste, sans qu'aucune ne soit
	 * fausse. On met donc à jour l'existante plutôt que d'en créer une
	 * seconde — ce qui protège aussi du double envoi d'un formulaire.
	 */
	if ( ! $id ) {
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t} WHERE campaign = %s LIMIT 1",
			$row['campaign']
		) );

		if ( $existing ) {
			$id = $existing;
		}
	}

	$row['slug'] = cbaz_unique_slug( $data['slug'] ?? $row['campaign'], (int) $id );

	if ( $id ) {
		$wpdb->update( $t, $row, [ 'id' => (int) $id ] );

		return (int) $id;
	}

	$row['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $t, $row );

	return (int) $wpdb->insert_id;
}

function cbaz_clean_date( $value ) {
	$value = trim( (string) $value );

	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
}

/** Raccourci unique : deux campagnes ne peuvent pas se disputer la même adresse. */
function cbaz_unique_slug( $wanted, $id = 0 ) {
	global $wpdb;

	$t    = cbaz_table( 'campaigns' );
	$base = cbaz_slug_utm( $wanted );
	$base = $base ? substr( $base, 0, 70 ) : 'campagne';
	$slug = $base;
	$i    = 2;

	while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE slug = %s AND id <> %d", $slug, $id ) ) ) {
		$slug = $base . '-' . $i++;
	}

	return $slug;
}

function cbaz_delete_campaign( $id ) {
	global $wpdb;

	// Seule la fiche disparaît : les commandes gardent leur attribution,
	// sinon l'historique se réécrirait tout seul.
	$wpdb->delete( cbaz_table( 'campaigns' ), [ 'id' => (int) $id ] );
}

// ══════════════════════════════════════════════════════════════
//  LIENS
// ══════════════════════════════════════════════════════════════

/**
 * Le lien à diffuser.
 *
 * Un seul paramètre — ?utm=rentree-2026 — plutôt que les cinq
 * utm_source, utm_medium et compagnie. La personne arrive directement
 * sur la page voulue, sans redirection, et l'adresse reste lisible dans
 * une story ou sur un flyer.
 *
 * Le détail (source, support, contenu) n'a pas besoin de voyager dans
 * l'URL : il est déjà sur la fiche, le serveur va l'y chercher au
 * moment où la visite arrive.
 */
function cbaz_campaign_url( $campaign ) {
	$base = $campaign->target_url ? $campaign->target_url : home_url( '/' );
	$ref  = $campaign->slug ? $campaign->slug : $campaign->campaign;

	return add_query_arg( 'utm', $ref, $base );
}

/**
 * Version longue, en paramètres UTM standards.
 *
 * À réserver aux outils qui les exigent — régies publicitaires,
 * plateformes d'emailing — ou quand le lien doit rester lisible par un
 * autre outil de mesure que le nôtre.
 */
function cbaz_campaign_url_long( $campaign ) {
	$args = [];

	foreach ( [ 'source', 'medium', 'campaign', 'term', 'content' ] as $key ) {
		$value = $campaign->$key ?? '';

		if ( '' !== $value ) {
			$args[ 'utm_' . $key ] = $value;
		}
	}

	$base = $campaign->target_url ? $campaign->target_url : home_url( '/' );

	return add_query_arg( $args, $base );
}

/**
 * Retrouve une campagne d'après la référence portée par ?utm=.
 *
 * On accepte le raccourci comme le nom de campagne : les deux se
 * ressemblent, et personne ne devrait avoir à se souvenir lequel est
 * lequel au moment de composer un lien à la main.
 */
function cbaz_campaign_by_ref( $ref ) {
	global $wpdb;

	$ref = cbaz_slug_utm( $ref );

	if ( ! $ref ) {
		return null;
	}

	$t = cbaz_table( 'campaigns' );

	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE slug = %s OR campaign = %s ORDER BY (slug = %s) DESC LIMIT 1",
		$ref,
		$ref,
		$ref
	) );
}

/**
 * Lien court sans paramètre.
 *
 * Utile là où un point d'interrogation passe mal — un QR code imprimé,
 * une plateforme qui tronque les URL. Il redirige vers la version à un
 * paramètre.
 */
function cbaz_short_url( $campaign ) {
	return home_url( '/go/' . $campaign->slug );
}

add_action( 'init', 'cbaz_short_link_rule' );
function cbaz_short_link_rule() {
	add_rewrite_rule( '^go/([^/]+)/?$', 'index.php?cbaz_go=$matches[1]', 'top' );
}

add_filter( 'query_vars', 'cbaz_query_vars' );
function cbaz_query_vars( $vars ) {
	$vars[] = 'cbaz_go';

	return $vars;
}

add_action( 'template_redirect', 'cbaz_short_link_redirect' );
function cbaz_short_link_redirect() {
	$slug = get_query_var( 'cbaz_go' );

	if ( ! $slug ) {
		return;
	}

	$campaign = cbaz_campaign_by_slug( sanitize_title( $slug ) );

	if ( ! $campaign ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	// 302 et non 301 : une redirection permanente serait mise en cache
	// par le navigateur, et les visites suivantes ne repasseraient plus
	// par ici — la campagne cesserait d'être comptée.
	wp_redirect( cbaz_campaign_url( $campaign ), 302 );
	exit;
}

/** La règle de réécriture doit exister dès l'activation. */
add_action( 'cbaz_flush_rules', 'flush_rewrite_rules' );

// ══════════════════════════════════════════════════════════════
//  RÉSULTATS
// ══════════════════════════════════════════════════════════════


/**
 * Fiches en double, héritées d'avant le garde-fou.
 *
 * On ne supprime rien : la plus ancienne est conservée, les suivantes
 * sont simplement écartées de l'affichage. Supprimer d'office un
 * enregistrement que quelqu'un a saisi n'est pas à nous de le décider.
 */
function cbaz_campaigns_unique( $limit = 200 ) {
	$vues = [];

	foreach ( cbaz_campaigns( '', $limit ) as $sheet ) {
		if ( ! isset( $vues[ $sheet->campaign ] ) ) {
			$vues[ $sheet->campaign ] = $sheet;
		}
	}

	return array_values( $vues );
}
