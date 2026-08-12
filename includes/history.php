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
	$from     = $day . ' 00:00:00';
	$next     = gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) ) . ' 00:00:00';

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
		 WHERE started_at >= %s AND started_at < %s",
		$day,
		$from,
		$next
	) );

	/* Les totaux commerciaux ont WooCommerce pour source de vérité, y
	 * compris les commandes non attribuées et les remboursements. */
	$shop = cbaz_shop_totals( [ 'from' => $from, 'to' => gmdate( 'Y-m-d H:i:s', strtotime( $next ) - 1 ) ] );
	$wpdb->update( $daily, [ 'orders' => $shop['orders'], 'revenue' => $shop['revenue'] ], [ 'day' => $day ] );

	foreach ( cbaz_history_dims() as $kind => $column ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$dims} (day, kind, label, sessions, orders, revenue)
			 SELECT %s, %s, {$column},
				COUNT(*),
				0,
				0
			 FROM {$sessions}
			 WHERE started_at >= %s AND started_at < %s AND {$column} <> ''
			 GROUP BY {$column}",
			$day,
			$kind,
			$from,
			$next
		) );
	}

	cbaz_rollup_commerce_dims( $day, $from, gmdate( 'Y-m-d H:i:s', strtotime( $next ) - 1 ) );
}

/** Injecte dans les dimensions le CA net issu des commandes et remboursements. */
function cbaz_rollup_commerce_dims( $day, $from, $to ) {
	global $wpdb;

	$schema = cbaz_order_schema();
	$total  = cbaz_total_expr( 'o' );
	$refund = cbaz_refund_expr( 'r' );
	$range  = cbaz_order_range( [ 'from' => $from, 'to' => $to ] );
	$paid   = cbaz_status_list( cbaz_paid_statuses() );
	$all    = cbaz_status_list( cbaz_revenue_statuses() );
	$keys   = [
		'_cbaz_source' => 'source', '_cbaz_medium' => 'medium', '_cbaz_campaign' => 'campaign',
		'_cbaz_country' => 'country', '_cbaz_device' => 'device',
	];
	$key_sql = cbaz_status_list( array_keys( $keys ) );

	$gross = $wpdb->get_results( $wpdb->prepare(
		"SELECT am.meta_key, am.meta_value AS label,
			SUM(CASE WHEN o.{$schema['status']} IN ({$paid}) THEN 1 ELSE 0 END) AS orders,
			SUM({$total['select']}) AS revenue
		FROM {$schema['orders']} o {$total['join']}
		INNER JOIN {$schema['meta']} am ON am.{$schema['meta_fk']} = o.{$schema['id']} AND am.meta_key IN ({$key_sql})
		WHERE o.{$schema['type']} = 'shop_order' AND o.{$schema['status']} IN ({$all})
			AND o.{$schema['date']} BETWEEN %s AND %s AND am.meta_value <> ''
		GROUP BY am.meta_key, am.meta_value",
		$range['from'], $range['to']
	) );
	$refunds = $wpdb->get_results( $wpdb->prepare(
		"SELECT am.meta_key, am.meta_value AS label, SUM({$refund['select']}) AS revenue
		FROM {$schema['orders']} r
		INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = r.{$schema['parent']}
		{$refund['join']}
		INNER JOIN {$schema['meta']} am ON am.{$schema['meta_fk']} = o.{$schema['id']} AND am.meta_key IN ({$key_sql})
		WHERE r.{$schema['type']} = 'shop_order_refund' AND r.{$schema['date']} BETWEEN %s AND %s AND am.meta_value <> ''
		GROUP BY am.meta_key, am.meta_value",
		$range['from'], $range['to']
	) );
	$commerce = [];

	foreach ( $gross as $row ) {
		$id = $row->meta_key . "\0" . $row->label;
		$commerce[ $id ] = [ 'meta_key' => $row->meta_key, 'label' => $row->label, 'orders' => (int) $row->orders, 'revenue' => (float) $row->revenue ];
	}
	foreach ( $refunds as $row ) {
		$id = $row->meta_key . "\0" . $row->label;
		if ( ! isset( $commerce[ $id ] ) ) { $commerce[ $id ] = [ 'meta_key' => $row->meta_key, 'label' => $row->label, 'orders' => 0, 'revenue' => 0.0 ]; }
		$commerce[ $id ]['revenue'] -= (float) $row->revenue;
	}

	$dims = cbaz_table( 'daily_dim' );
	foreach ( $commerce as $row ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$dims} (day, kind, label, sessions, orders, revenue)
			VALUES (%s, %s, %s, 0, %d, %f)
			ON DUPLICATE KEY UPDATE orders = VALUES(orders), revenue = VALUES(revenue)",
			$day, $keys[ $row['meta_key'] ], $row['label'], $row['orders'], $row['revenue']
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
	$lock     = 'cbaz-rollup-' . substr( md5( $wpdb->prefix ), 0, 16 );
	$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );

	if ( '0' === (string) $acquired ) {
		return 0;
	}

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

	if ( '1' === (string) $acquired ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}

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
			SELECT DATE_FORMAT(started_at, '%%Y-%%m') AS period,
				COUNT(*) AS sessions, COUNT(DISTINCT visitor_hash) AS visitors,
				SUM(pageviews) AS pageviews,
				SUM(CASE WHEN pageviews = 1 THEN 1 ELSE 0 END) AS bounces,
				SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders,
				SUM(revenue) AS revenue
			FROM {$sessions} WHERE started_at >= %s
			GROUP BY DATE_FORMAT(started_at, '%%Y-%%m')
		 ) AS flux
		 GROUP BY period
		 ORDER BY period ASC",
		$depuis,
		$frontier,
		$frontier . ' 00:00:00'
	) );

	foreach ( $rows as $row ) {
		$row->sessions  = (int) $row->sessions;
		$row->visitors  = (int) $row->visitors;
		$row->pageviews = (int) $row->pageviews;
		$row->bounces   = (int) $row->bounces;
		$row->orders    = (int) $row->orders;
		$row->revenue   = (float) $row->revenue;
	}

	/* La partie non consolidée doit elle aussi prendre WooCommerce comme
	 * source commerciale, et non la seule fraction attribuée aux visites. */
	$shop = cbaz_shop_months( [
		'from' => max( $depuis, $frontier ) . ' 00:00:00',
		'to'   => current_time( 'mysql' ),
	], false );
	$map = [];
	foreach ( $rows as $row ) { $map[ $row->period ] = $row; }
	foreach ( $shop as $period => $commerce ) {
		if ( ! isset( $map[ $period ] ) ) {
			$map[ $period ] = (object) [ 'period' => $period, 'sessions' => 0, 'visitors' => 0, 'pageviews' => 0, 'bounces' => 0, 'orders' => 0, 'revenue' => 0.0 ];
		}
		$map[ $period ]->orders  = $commerce['orders'];
		$map[ $period ]->revenue = $commerce['revenue'];
	}
	ksort( $map );

	return array_values( $map );
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
	$fin_exclusive = ( (int) $year + 1 ) . '-01-01 00:00:00';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT label, SUM(sessions) AS sessions, SUM(orders) AS orders, SUM(revenue) AS revenue
		 FROM (
			SELECT label, sessions, orders, revenue
			FROM {$dims} WHERE kind = %s AND day BETWEEN %s AND %s AND day < %s
			UNION ALL
			SELECT {$column} AS label, 1, CASE WHEN order_id > 0 THEN 1 ELSE 0 END, revenue
			FROM {$sessions}
			WHERE started_at >= %s AND started_at < %s AND started_at >= %s AND {$column} <> ''
		 ) AS flux
		 GROUP BY label
		 ORDER BY sessions DESC
		 LIMIT %d",
		$kind,
		$debut . ' 00:00:00',
		$fin_exclusive,
		$frontier . ' 00:00:00',
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
