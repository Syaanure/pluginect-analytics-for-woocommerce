<?php
/**
 * Accès aux commandes.
 *
 * WooCommerce sait ranger ses commandes de deux façons : dans wp_posts,
 * comme historiquement, ou dans ses propres tables — le « stockage
 * haute performance », activé par défaut sur les installations récentes
 * et sur beaucoup de boutiques migrées.
 *
 * Interroger wp_posts sans vérifier reviendrait, sur une boutique en
 * stockage moderne, à ne trouver aucune commande : tous les chiffres de
 * vente afficheraient zéro sans le moindre message d'erreur. C'est
 * exactement le genre de panne qu'on ne remarque qu'au bout d'un mois.
 *
 * Ce fichier isole la différence : le reste du code ne s'en occupe plus.
 */

defined( 'ABSPATH' ) || exit;

/** WooCommerce range-t-il ses commandes dans ses propres tables ? */
function cbaz_hpos() {
	static $on = null;

	if ( null !== $on ) {
		return $on;
	}

	$on = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	return $on;
}

/**
 * Noms de tables et de colonnes pour le mode en vigueur.
 *
 * Différence à retenir : en stockage moderne le total est une colonne,
 * alors qu'en stockage historique c'est une métadonnée — d'où deux
 * façons d'écrire la même somme.
 */
function cbaz_order_schema() {
	global $wpdb;

	if ( cbaz_hpos() ) {
		return [
			'hpos'    => true,
			'orders'  => $wpdb->prefix . 'wc_orders',
			'meta'    => $wpdb->prefix . 'wc_orders_meta',
			'id'      => 'id',
			'meta_fk' => 'order_id',
			'status'  => 'status',
			'date'    => 'date_created_gmt',
			'parent'  => 'parent_order_id',
			'type'    => 'type',
		];
	}

	return [
		'hpos'    => false,
		'orders'  => $wpdb->posts,
		'meta'    => $wpdb->postmeta,
		'id'      => 'ID',
		'meta_fk' => 'post_id',
		'status'  => 'post_status',
		'date'    => 'post_date',
		'parent'  => 'post_parent',
		'type'    => 'post_type',
	];
}

/** Statuts considérés comme du chiffre d'affaires réalisé. */
function cbaz_paid_statuses() {
	$statuses = function_exists( 'wc_get_is_paid_statuses' )
		? array_map( function ( $status ) { return 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status; }, wc_get_is_paid_statuses() )
		: [ 'wc-completed', 'wc-processing' ];

	return apply_filters( 'cbaz_paid_statuses', $statuses );
}

/** Statuts dont le montant brut doit exister avant déduction des avoirs. */
function cbaz_revenue_statuses() {
	return array_values( array_unique( array_merge( cbaz_paid_statuses(), [ 'wc-refunded' ] ) ) );
}

function cbaz_status_list( array $statuses ) {
	return "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";
}

/** Expression SQL du total d'une commande, et la jointure qu'elle exige. */
function cbaz_total_expr( $alias = 'o' ) {
	global $wpdb;

	$s = cbaz_order_schema();

	if ( $s['hpos'] ) {
		return [ 'select' => "{$alias}.total_amount", 'join' => '' ];
	}

	return [
		'select' => 'tot.meta_value',
		'join'   => "INNER JOIN {$wpdb->postmeta} tot ON tot.post_id = {$alias}.ID AND tot.meta_key = '_order_total'",
	];
}

/** Montant positif d'un remboursement et jointure nécessaire. */
function cbaz_refund_expr( $alias = 'r' ) {
	global $wpdb;
	$s = cbaz_order_schema();

	if ( $s['hpos'] ) {
		return [ 'select' => "ABS({$alias}.total_amount)", 'join' => '' ];
	}

	return [
		'select' => 'ABS(ref.meta_value)',
		'join'   => "INNER JOIN {$wpdb->postmeta} ref ON ref.post_id = {$alias}.ID AND ref.meta_key = '_refund_amount'",
	];
}

/**
 * Convertit les bornes locales des rapports vers le stockage des commandes.
 *
 * Les anciennes commandes utilisent `post_date` (heure du site), tandis que
 * HPOS utilise `date_created_gmt`. Comparer les mêmes chaînes aux deux colonnes
 * décalait les ventes HPOS du fuseau du site, notamment autour de minuit.
 */
function cbaz_order_range( array $range ) {
	if ( ! cbaz_hpos() ) {
		return $range;
	}

	foreach ( [ 'from', 'to', 'prev_from', 'prev_to' ] as $key ) {
		if ( empty( $range[ $key ] ) ) {
			continue;
		}

		$date = date_create( $range[ $key ], wp_timezone() );
		if ( $date ) {
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			$range[ $key ] = $date->format( 'Y-m-d H:i:s' );
		}
	}

	return $range;
}

/**
 * Lien vers la fiche commande.
 *
 * L'écran d'édition n'est pas au même endroit selon le mode de
 * stockage : un lien codé en dur enverrait dans le vide sur la moitié
 * des boutiques.
 */
function cbaz_order_edit_url( $order_id ) {
	if ( cbaz_hpos() ) {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $order_id );
	}

	return admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
}
