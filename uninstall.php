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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ce fichier interroge les tables propres au plugin ({prefix}cbaz_*), pour lesquelles WordPress n'offre aucune API : les noms de tables viennent de cbaz_table(), les valeurs passent par $wpdb->prepare(), et les lectures lourdes sont consolidées par jour (history.php) plutôt que mises en cache objet.

global $wpdb;

$cbaz_settings = get_option( 'cbaz_settings', [] );

if ( empty( $cbaz_settings['delete_on_uninstall'] ) ) {
	return;
}

// ── Tables ──────────────────────────────────────────────────────
foreach ( [ 'daily_dim', 'daily', 'events', 'views', 'sessions', 'campaigns' ] as $cbaz_table ) {
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- suppression des tables de l'extension à la désinstallation, noms construits sur le préfixe
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cbaz_' . $cbaz_table );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
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
] as $cbaz_option ) {
	delete_option( $cbaz_option );
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
$cbaz_hpos = $wpdb->prefix . 'wc_orders_meta';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cbaz_hpos ) ) === $cbaz_hpos ) {
	$wpdb->query( "DELETE FROM {$cbaz_hpos} WHERE meta_key LIKE '\_cbaz\_%'" );
}
