<?php
/**
 * Schéma.
 *
 * Trois tables de mesure et une de campagnes. Les index sont posés sur
 * ce que les rapports interrogent réellement : la date, l'empreinte du
 * visiteur, la campagne. Sur une boutique, ces tables grossissent vite
 * — sans index, la page « Vue d'ensemble » deviendrait aussi lente que
 * les écrans qu'on a passé une journée à déboguer.
 */

defined( 'ABSPATH' ) || exit;

function cbaz_table( $name ) {
	global $wpdb;

	return $wpdb->prefix . 'cbaz_' . $name;
}

function cbaz_install_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset  = $wpdb->get_charset_collate();
	$sessions = cbaz_table( 'sessions' );
	$views    = cbaz_table( 'views' );
	$events   = cbaz_table( 'events' );
	$camps    = cbaz_table( 'campaigns' );
	$daily    = cbaz_table( 'daily' );
	$dims     = cbaz_table( 'daily_dim' );

	/*
	 * visitor_hash n'est pas réversible et change chaque jour : il
	 * permet de recoller les pages d'une même visite, pas de suivre
	 * quelqu'un d'un jour sur l'autre.
	 */
	dbDelta( "CREATE TABLE {$sessions} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		visitor_hash CHAR(32) NOT NULL DEFAULT '',
		started_at DATETIME NOT NULL,
		last_seen DATETIME NOT NULL,
		pageviews SMALLINT UNSIGNED NOT NULL DEFAULT 1,
		engaged_seconds INT UNSIGNED NOT NULL DEFAULT 0,
		entry_path VARCHAR(190) NOT NULL DEFAULT '',
		exit_path VARCHAR(190) NOT NULL DEFAULT '',
		referrer_host VARCHAR(120) NOT NULL DEFAULT '',
		source VARCHAR(80) NOT NULL DEFAULT '',
		medium VARCHAR(80) NOT NULL DEFAULT '',
		campaign VARCHAR(120) NOT NULL DEFAULT '',
		term VARCHAR(120) NOT NULL DEFAULT '',
		content VARCHAR(120) NOT NULL DEFAULT '',
		country CHAR(2) NOT NULL DEFAULT '',
		device VARCHAR(10) NOT NULL DEFAULT '',
		screen VARCHAR(12) NOT NULL DEFAULT '',
		lang VARCHAR(12) NOT NULL DEFAULT '',
		browser VARCHAR(24) NOT NULL DEFAULT '',
		os VARCHAR(24) NOT NULL DEFAULT '',
		is_new TINYINT(1) NOT NULL DEFAULT 1,
		order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
		first_source VARCHAR(80) NOT NULL DEFAULT '',
		first_medium VARCHAR(80) NOT NULL DEFAULT '',
		first_campaign VARCHAR(120) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY started_at (started_at),
		KEY visitor_hash (visitor_hash, last_seen),
		KEY campaign (campaign),
		KEY source_medium (source, medium),
		KEY country (country),
		KEY order_id (order_id)
	) {$charset};" );

	dbDelta( "CREATE TABLE {$views} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id BIGINT UNSIGNED NOT NULL,
		path VARCHAR(190) NOT NULL DEFAULT '',
		title VARCHAR(190) NOT NULL DEFAULT '',
		viewed_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY viewed_at (viewed_at),
		KEY path (path)
	) {$charset};" );

	/*
	 * `label` sert au terme d'une recherche interne comme au chemin d'une
	 * page dont on mesure le temps de lecture : deux usages, une seule
	 * colonne, plutôt qu'une table par type d'évènement.
	 *
	 * Le commentaire vit ici et non dans le SQL : dbDelta lit chaque ligne
	 * du CREATE TABLE comme une colonne, et prendrait un commentaire pour
	 * un champ à ajouter.
	 */
	dbDelta( "CREATE TABLE {$events} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id BIGINT UNSIGNED NOT NULL,
		name VARCHAR(40) NOT NULL DEFAULT '',
		object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		value DECIMAL(12,2) NOT NULL DEFAULT 0,
		label VARCHAR(190) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY name_date (name, created_at),
		KEY name_label (name, label(64))
	) {$charset};" );

	/*
	 * Le budget vit sur la fiche : sans lui, on saurait ce qu'une
	 * campagne rapporte mais jamais si elle est rentable, ce qui est
	 * la seule question qui compte au moment d'en relancer une.
	 */
	dbDelta( "CREATE TABLE {$camps} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		label VARCHAR(190) NOT NULL DEFAULT '',
		slug VARCHAR(80) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		source VARCHAR(80) NOT NULL DEFAULT '',
		medium VARCHAR(80) NOT NULL DEFAULT '',
		campaign VARCHAR(120) NOT NULL DEFAULT '',
		term VARCHAR(120) NOT NULL DEFAULT '',
		content VARCHAR(120) NOT NULL DEFAULT '',
		target_url VARCHAR(255) NOT NULL DEFAULT '',
		cost DECIMAL(12,2) NOT NULL DEFAULT 0,
		goal_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
		starts_on DATE NULL,
		ends_on DATE NULL,
		notes TEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY campaign (campaign),
		UNIQUE KEY slug (slug)
	) {$charset};" );

	/*
	 * HISTORIQUE CONSOLIDÉ.
	 *
	 * Le détail d'une visite — ses pages, ses évènements, sa seconde
	 * d'arrivée — a une valeur qui s'épuise en quelques mois. Le total
	 * du 14 mars 2019, lui, garde tout son sens : c'est ce qui permet
	 * de dire si la rentrée est meilleure que celle d'il y a trois ans.
	 *
	 * D'où deux régimes. Le détail vit le temps qu'on lui accorde puis
	 * disparaît ; le résumé quotidien reste dix ans. Une journée pèse
	 * ici une ligne et quelques dizaines d'octets : dix ans d'historique
	 * tiennent dans ce qu'une seule semaine de détail occupe.
	 *
	 * La consolidation a lieu AVANT la purge, jamais après : un jour
	 * effacé sans avoir été résumé serait perdu deux fois.
	 */
	dbDelta( "CREATE TABLE {$daily} (
		day DATE NOT NULL,
		sessions INT UNSIGNED NOT NULL DEFAULT 0,
		visitors INT UNSIGNED NOT NULL DEFAULT 0,
		pageviews INT UNSIGNED NOT NULL DEFAULT 0,
		bounces INT UNSIGNED NOT NULL DEFAULT 0,
		orders INT UNSIGNED NOT NULL DEFAULT 0,
		revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
		PRIMARY KEY (day)
	) {$charset};" );

	/*
	 * Une ligne par jour ET par valeur : « 12 mars, source, Instagram,
	 * 40 visites, 2 commandes ». C'est ce qui permet de répondre, dans
	 * cinq ans, à « Instagram m'a rapporté combien en 2026 ? » sans
	 * avoir gardé une seule visite individuelle.
	 */
	dbDelta( "CREATE TABLE {$dims} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		day DATE NOT NULL,
		kind VARCHAR(12) NOT NULL DEFAULT '',
		label VARCHAR(120) NOT NULL DEFAULT '',
		sessions INT UNSIGNED NOT NULL DEFAULT 0,
		orders INT UNSIGNED NOT NULL DEFAULT 0,
		revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY day_kind_label (day, kind, label),
		KEY day_kind (day, kind),
		KEY kind_label (kind, label(40))
	) {$charset};" );
}

// ══════════════════════════════════════════════════════════════
//  HORODATAGE
//
//  Nos tables sont écrites avec current_time('mysql'), donc à L'HEURE
//  DU SITE. Or WordPress fixe le fuseau de PHP sur UTC : strtotime()
//  lisait « 17:47 » comme 17:47 UTC, puis wp_date() y ajoutait le
//  décalage du site pour afficher — deux heures de plus en été. Des
//  visites se retrouvaient datées dans le futur.
//
//  D'où cette lecture explicite, dans le fuseau où la valeur a été
//  écrite. Les durées, elles, n'étaient pas touchées : deux dates
//  décalées du même montant se soustraient correctement.
// ══════════════════════════════════════════════════════════════

/** Horodatage d'une date de nos tables, écrite à l'heure du site. */
function cbaz_ts( $local ) {
	if ( ! $local ) {
		return 0;
	}

	$date = date_create( (string) $local, wp_timezone() );

	return $date ? $date->getTimestamp() : 0;
}

/**
 * Horodatage d'une date de commande.
 *
 * WooCommerce ne stocke pas de la même façon selon la version : la
 * table des commandes retient de l'UTC, l'ancien stockage dans les
 * publications retient l'heure du site. On lit donc selon le cas
 * plutôt que de supposer.
 */
function cbaz_order_ts( $value ) {
	if ( ! $value ) {
		return 0;
	}

	$schema = cbaz_order_schema();

	if ( ! empty( $schema['hpos'] ) ) {
		$date = date_create( (string) $value, new DateTimeZone( 'UTC' ) );
		return $date ? $date->getTimestamp() : 0;
	}

	return cbaz_ts( $value );
}
