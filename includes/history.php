<?php
/**
 * Consolidation et historique long.
 *
 * Deux régimes de conservation cohabitent. Le DÉTAIL — chaque visite,
 * chaque page, chaque évènement — sert à comprendre ce qui vient de se
 * passer : on le garde quelques mois, parce qu'au-delà personne ne
 * relit le parcours d'une visite de l'hiver dernier, et parce qu'il
 * pèse lourd. Le RÉSUMÉ quotidien, lui, ne périme pas : savoir que le
 * 14 mars 2019 a fait 62 visites et 3 commandes garde exactement la
 * même valeur dans dix ans, et c'est ce qui permet de dire si cette
 * rentrée-ci vaut mieux que celle d'il y a trois ans.
 *
 * Le résumé est écrit une fois par nuit pour la veille, puis ne bouge
 * plus. Il est calculé AVANT que la purge n'efface le détail — sinon
 * un jour serait perdu deux fois.
 *
 * Ordre de grandeur : une journée résumée pèse une ligne de totaux et
 * quelques lignes de répartition. Dix ans d'historique tiennent dans
 * ce qu'une seule semaine de détail occupe.
 */

defined( 'ABSPATH' ) || exit;

/** Les répartitions conservées : ce qu'on voudra encore comparer dans dix ans. */
function cbaz_history_dims() {
	return [
		'source'   => 'source',
		'medium'   => 'medium',
		'campaign' => 'campaign',
		'country'  => 'country',
		'device'   => 'device',
	];
}

// ══════════════════════════════════════════════════════════════
//  ÉCRITURE
// ══════════════════════════════════════════════════════════════

/**
 * Résume une journée.
 *
 * L'opération est rejouable : on efface le résumé du jour avant de le
 * réécrire. Consolider deux fois la même date ne double donc rien —
 * une propriété qui vaut cher le jour où une tâche planifiée se
 * déclenche en retard, ou deux fois.
 */
function cbaz_rollup_day( $day ) {
	global $wpdb;

	$daily    = cbaz_table( 'daily' );
	$dims     = cbaz_table( 'daily_dim' );
	$sessions = cbaz_table( 'sessions' );

	$wpdb->delete( $daily, [ 'day' => $day ] );
	$wpdb->delete( $dims, [ 'day' => $day ] );

	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$daily} (day, sessions, visitors, pageviews, bounces, orders, revenue)
		 SELECT %s,
			COUNT(*),
			COUNT(DISTINCT visitor_hash),
			COALESCE(SUM(pageviews), 0),
			SUM(CASE WHEN pageviews = 1 THEN 1 ELSE 0 END),
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END),
			COALESCE(SUM(revenue), 0)
		 FROM {$sessions}
		 WHERE DATE(started_at) = %s
		 HAVING COUNT(*) > 0",
		$day,
		$day
	) );

	foreach ( cbaz_history_dims() as $kind => $column ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$dims} (day, kind, label, sessions, orders, revenue)
			 SELECT %s, %s, {$column},
				COUNT(*),
				SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END),
				COALESCE(SUM(revenue), 0)
			 FROM {$sessions}
			 WHERE DATE(started_at) = %s AND {$column} <> ''
			 GROUP BY {$column}",
			$day,
			$kind,
			$day
		) );
	}
}

/**
 * Rattrape tous les jours pas encore résumés.
 *
 * Le nombre de journées traitées est plafonné : sur une base qui a
 * trois ans de détail, tout consolider d'un coup bloquerait la tâche
 * de nuit. Le rattrapage reprend là où il s'est arrêté, nuit après
 * nuit, jusqu'à rejoindre la veille.
 */
function cbaz_rollup_pending( $max_days = 120 ) {
	global $wpdb;

	$sessions = cbaz_table( 'sessions' );
	$depuis   = get_option( 'cbaz_rollup_upto' );

	if ( ! $depuis ) {
		$premier = $wpdb->get_var( "SELECT MIN(DATE(started_at)) FROM {$sessions}" );
		$depuis  = $premier ? $premier : gmdate( 'Y-m-d' );
	}

	// L'heure du SITE, pas celle du serveur : les visites sont
	// horodatées avec current_time(), et une journée résumée dans un
	// autre fuseau serait tronquée d'un bout et doublée de l'autre.
	$hier   = cbaz_yesterday();
	$jour   = $depuis;
	$faits  = 0;

	while ( $jour <= $hier && $faits < $max_days ) {
		cbaz_rollup_day( $jour );

		$jour = gmdate( 'Y-m-d', strtotime( $jour . ' +1 day' ) );
		$faits++;
	}

	// La date mémorisée est le premier jour NON consolidé : le détail
	// à partir de là fait encore autorité.
	update_option( 'cbaz_rollup_upto', $jour, false );

	return $faits;
}

/** La veille, dans l'heure du site. */
function cbaz_yesterday() {
	return gmdate( 'Y-m-d', strtotime( current_time( 'mysql' ) ) - DAY_IN_SECONDS );
}

/** Premier jour dont le détail fait encore autorité. */
function cbaz_rollup_frontier() {
	$upto = get_option( 'cbaz_rollup_upto' );

	return $upto ? $upto : gmdate( 'Y-m-d' );
}

// ══════════════════════════════════════════════════════════════
//  LECTURE
//
//  L'historique est lu en deux morceaux, sans recouvrement : le résumé
//  jusqu'à la frontière, le détail à partir d'elle. Une même journée
//  n'est donc jamais comptée deux fois — c'est le seul point où cette
//  mécanique peut mentir, et c'est celui qu'elle protège.
// ══════════════════════════════════════════════════════════════

/**
 * Totaux par mois, sur toute la profondeur disponible.
 *
 * @return array Lignes ordonnées, avec period (Y-m), sessions, orders, revenue…
 */
function cbaz_history_months( $limit_years = 10 ) {
	global $wpdb;

	$daily    = cbaz_table( 'daily' );
	$sessions = cbaz_table( 'sessions' );
	$frontier = cbaz_rollup_frontier();
	$depuis   = gmdate( 'Y-m-d', strtotime( "-{$limit_years} years" ) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT period, SUM(sessions) AS sessions, SUM(visitors) AS visitors,
			SUM(pageviews) AS pageviews, SUM(bounces) AS bounces,
			SUM(orders) AS orders, SUM(revenue) AS revenue
		 FROM (
			SELECT DATE_FORMAT(day, '%%Y-%%m') AS period, sessions, visitors, pageviews, bounces, orders, revenue
			FROM {$daily} WHERE day >= %s AND day < %s
			UNION ALL
			SELECT DATE_FORMAT(started_at, '%%Y-%%m') AS period, 1, 0, pageviews,
				CASE WHEN pageviews = 1 THEN 1 ELSE 0 END,
				CASE WHEN order_id > 0 THEN 1 ELSE 0 END, revenue
			FROM {$sessions} WHERE DATE(started_at) >= %s
		 ) AS flux
		 GROUP BY period
		 ORDER BY period ASC",
		$depuis,
		$frontier,
		$frontier
	) );

	foreach ( $rows as $row ) {
		$row->sessions  = (int) $row->sessions;
		$row->visitors  = (int) $row->visitors;
		$row->pageviews = (int) $row->pageviews;
		$row->bounces   = (int) $row->bounces;
		$row->orders    = (int) $row->orders;
		$row->revenue   = (float) $row->revenue;
	}

	return $rows;
}

/** Les mêmes totaux, regroupés par année. */
function cbaz_history_years( array $months ) {
	$annees = [];

	foreach ( $months as $m ) {
		$an = substr( $m->period, 0, 4 );

		if ( ! isset( $annees[ $an ] ) ) {
			$annees[ $an ] = (object) [
				'year' => $an, 'sessions' => 0, 'visitors' => 0, 'pageviews' => 0,
				'bounces' => 0, 'orders' => 0, 'revenue' => 0.0, 'months' => 0,
			];
		}

		foreach ( [ 'sessions', 'visitors', 'pageviews', 'bounces', 'orders', 'revenue' ] as $k ) {
			$annees[ $an ]->$k += $m->$k;
		}

		$annees[ $an ]->months++;
	}

	krsort( $annees );

	return $annees;
}

/**
 * Répartition d'une année selon une dimension.
 *
 * C'est la question à laquelle sert vraiment l'historique long :
 * « Instagram m'a rapporté combien en 2026 ? », posée en 2031, sans
 * qu'une seule visite individuelle ait été conservée.
 */
function cbaz_history_breakdown( $kind, $year, $limit = 10 ) {
	global $wpdb;

	$dims     = cbaz_table( 'daily_dim' );
	$sessions = cbaz_table( 'sessions' );
	$frontier = cbaz_rollup_frontier();
	$colonnes = cbaz_history_dims();

	if ( ! isset( $colonnes[ $kind ] ) ) {
		return [];
	}

	$column = $colonnes[ $kind ];
	$debut  = $year . '-01-01';
	$fin    = $year . '-12-31';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT label, SUM(sessions) AS sessions, SUM(orders) AS orders, SUM(revenue) AS revenue
		 FROM (
			SELECT label, sessions, orders, revenue
			FROM {$dims} WHERE kind = %s AND day BETWEEN %s AND %s AND day < %s
			UNION ALL
			SELECT {$column} AS label, 1, CASE WHEN order_id > 0 THEN 1 ELSE 0 END, revenue
			FROM {$sessions}
			WHERE DATE(started_at) BETWEEN %s AND %s AND DATE(started_at) >= %s AND {$column} <> ''
		 ) AS flux
		 GROUP BY label
		 ORDER BY sessions DESC
		 LIMIT %d",
		$kind,
		$debut,
		$fin,
		$frontier,
		$debut,
		$fin,
		$frontier,
		$limit
	) );
}

/** Poids de l'historique, pour le dire honnêtement dans les réglages. */
function cbaz_history_weight() {
	global $wpdb;

	$daily = cbaz_table( 'daily' );
	$dims  = cbaz_table( 'daily_dim' );

	return [
		'days'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily}" ),
		'rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$dims}" ),
		'oldest'  => $wpdb->get_var( "SELECT MIN(day) FROM {$daily}" ),
	];
}
