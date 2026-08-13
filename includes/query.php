<?php
/**
 * Rapports.
 *
 * Toutes les lectures passent par ici. Chaque fonction rend un tableau
 * simple, prêt à afficher : les vues ne font aucun calcul.
 */

defined( 'ABSPATH' ) || exit;

// ══════════════════════════════════════════════════════════════
//  PÉRIODE
// ══════════════════════════════════════════════════════════════

function cbaz_presets() {
	return [
		'today' => __( "Aujourd'hui", 'shop-analytics-for-woocommerce' ),
		'7d'    => __( '7 derniers jours', 'shop-analytics-for-woocommerce' ),
		'30d'   => __( '30 derniers jours', 'shop-analytics-for-woocommerce' ),
		'90d'   => __( '90 derniers jours', 'shop-analytics-for-woocommerce' ),
		'12m'   => __( '12 derniers mois', 'shop-analytics-for-woocommerce' ),
	];
}

/**
 * Bornes de la période courante et de la précédente.
 *
 * La période de comparaison a exactement la même durée : sans ça, une
 * variation en pourcentage ne voudrait rien dire.
 */
function cbaz_range( $preset = null ) {
	$preset = $preset ? $preset : ( sanitize_key( $_GET['periode'] ?? '' ) ?: '30d' );
	$perso  = 'perso' === $preset ? cbaz_custom_dates() : null;

	if ( $perso ) {
		// Bornes de journées entières : demander « du 1er au 3 » et
		// n'obtenir que deux jours et demi serait incompréhensible.
		$start = strtotime( $perso[0] . ' 00:00:00' );
		$end   = strtotime( $perso[1] . ' 23:59:59' );
		$days  = max( 1, (int) round( ( $end - $start ) / DAY_IN_SECONDS ) );
	} else {
		if ( ! isset( cbaz_presets()[ $preset ] ) ) {
			$preset = '30d';
		}

		$end   = current_time( 'timestamp' );
		$days  = [ 'today' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365 ][ $preset ];
		$start = strtotime( 'today', $end ) - ( $days - 1 ) * DAY_IN_SECONDS;
	}

	$span = $end - $start;

	return [
		'preset'    => $perso ? 'perso' : $preset,
		'du'        => $perso ? $perso[0] : '',
		'au'        => $perso ? $perso[1] : '',
		'days'      => $days,
		'from'      => gmdate( 'Y-m-d H:i:s', $start ),
		'to'        => gmdate( 'Y-m-d H:i:s', $end ),
		'prev_from' => gmdate( 'Y-m-d H:i:s', $start - $span ),
		'prev_to'   => gmdate( 'Y-m-d H:i:s', $start ),
	];
}

/**
 * Dates d'une plage personnalisée, ou null si elle ne tient pas debout.
 *
 * Les deux bornes viennent de l'adresse : on ne fait donc confiance ni
 * à leur format, ni à leur ordre, ni à leur vraisemblance. Des dates
 * inversées sont remises à l'endroit plutôt que rejetées — c'est une
 * maladresse de saisie, pas une erreur qui mérite un message.
 */
function cbaz_custom_dates() {
	$du = sanitize_text_field( wp_unslash( $_GET['du'] ?? '' ) );
	$au = sanitize_text_field( wp_unslash( $_GET['au'] ?? '' ) );

	foreach ( [ $du, $au ] as $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! strtotime( $date ) ) {
			return null;
		}
	}

	if ( $du > $au ) {
		[ $du, $au ] = [ $au, $du ];
	}

	// Pas d'avenir : une période qui déborde sur demain donnerait une
	// moyenne journalière fausse, diluée sur des jours vides.
	$aujourdhui = gmdate( 'Y-m-d', current_time( 'timestamp' ) );

	if ( $au > $aujourdhui ) {
		$au = $aujourdhui;
	}

	if ( $du > $aujourdhui ) {
		return null;
	}

	return [ $du, $au ];
}

/** Intitulé lisible de la période en cours. */
function cbaz_range_label( array $range ) {
	if ( 'perso' !== $range['preset'] ) {
		return cbaz_presets()[ $range['preset'] ] ?? __( '30 derniers jours', 'shop-analytics-for-woocommerce' );
	}

	$du = strtotime( $range['du'] );
	$au = strtotime( $range['au'] );

	if ( $range['du'] === $range['au'] ) {
		return wp_date( 'j F Y', $du );
	}

	// Le millésime ne se répète pas quand les deux bornes le partagent.
	$format = gmdate( 'Y', $du ) === gmdate( 'Y', $au ) ? 'j M' : 'j M Y';

	return wp_date( $format, $du ) . ' – ' . wp_date( 'j M Y', $au );
}

/**
 * Variation en pourcentage.
 *
 * Renvoie null quand il n'y a rien à quoi comparer. C'est important :
 * passer de zéro à quarante n'est pas « +100 % », c'est une grandeur
 * sans rapport avec un pourcentage. Afficher 100 laissait croire à un
 * doublement, et rendait la couleur incompréhensible sur les
 * indicateurs qu'il vaut mieux voir baisser — d'où des « +100 % » en
 * rouge qui n'avaient aucun sens.
 */
function cbaz_delta( $now, $before ) {
	if ( ! $before ) {
		return null;
	}

	return round( ( ( $now - $before ) / $before ) * 100, 1 );
}

// ══════════════════════════════════════════════════════════════
//  INDICATEURS
// ══════════════════════════════════════════════════════════════

function cbaz_totals( $from, $to ) {
	global $wpdb;

	$s = cbaz_table( 'sessions' );

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT
			COUNT(*) AS sessions,
			COUNT(DISTINCT visitor_hash) AS visitors,
			SUM(pageviews) AS pageviews,
			SUM(CASE WHEN pageviews = 1 THEN 1 ELSE 0 END) AS bounces,
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders,
			SUM(revenue) AS revenue,
			AVG(engaged_seconds) AS duration
		FROM {$s} WHERE started_at BETWEEN %s AND %s" . cbaz_filter_where(),
		$from,
		$to
	), ARRAY_A );

	$row = $row ? array_map( 'floatval', $row ) : [];

	$row = wp_parse_args( $row, [
		'sessions'  => 0,
		'visitors'  => 0,
		'pageviews' => 0,
		'bounces'   => 0,
		'orders'    => 0,
		'revenue'   => 0,
		'duration'  => 0,
	] );
	$tracked_orders = $row['orders'];

	/*
	 * Les ventes viennent de WooCommerce, pas de notre table.
	 *
	 * La mesure ne connaît que ce qu'elle a vu passer depuis son
	 * installation ; WooCommerce, lui, a tout l'historique. Lire le
	 * chiffre d'affaires dans les visites afficherait zéro sur une
	 * boutique qui vend depuis deux ans — et donnerait l'impression
	 * que rien ne fonctionne.
	 *
	 * On garde à part le CA « rattaché », celui qu'une visite mesurée
	 * explique : l'écart entre les deux est lui-même une information.
	 */
	$shop = cbaz_shop_totals( [ 'from' => $from, 'to' => $to ] );

	$row['attributed'] = $row['revenue'];
	$row['revenue']    = $shop['revenue'];
	$row['orders']     = $shop['orders'];
	$row['tracked_orders'] = $tracked_orders;

	$row['bounce_rate'] = $row['sessions'] ? ( $row['bounces'] / $row['sessions'] ) * 100 : 0;
	/* La conversion relie des achats MESURÉS aux visites mesurées. Utiliser
	 * toutes les commandes WooCommerce (imports, admin, période pré-plugin)
	 * pouvait produire un taux supérieur à 100 %. */
	$row['cr']          = $row['sessions'] ? ( $tracked_orders / $row['sessions'] ) * 100 : 0;
	$row['aov']         = $row['orders'] ? $row['revenue'] / $row['orders'] : 0;
	$row['per_session'] = $row['sessions'] ? $row['revenue'] / $row['sessions'] : 0;

	return $row;
}

function cbaz_kpis( array $range ) {
	$now  = cbaz_totals( $range['from'], $range['to'] );
	$then = cbaz_totals( $range['prev_from'], $range['prev_to'] );

	$defs = [
		[ 'visitors', __( 'Visiteurs', 'shop-analytics-for-woocommerce' ), 'int' ],
		[ 'sessions', __( 'Visites', 'shop-analytics-for-woocommerce' ), 'int' ],
		[ 'pageviews', __( 'Pages vues', 'shop-analytics-for-woocommerce' ), 'int' ],
		[ 'revenue', __( "Chiffre d'affaires", 'shop-analytics-for-woocommerce' ), 'money' ],
		[ 'cr', __( 'Taux de conversion', 'shop-analytics-for-woocommerce' ), 'pct' ],
		[ 'aov', __( 'Panier moyen', 'shop-analytics-for-woocommerce' ), 'money' ],
	];

	$out = [];

	foreach ( $defs as list( $key, $label, $format ) ) {
		$out[] = [
			'key'    => $key,
			'label'  => $label,
			'format' => $format,
			'value'  => $now[ $key ],
			'delta'  => cbaz_delta( $now[ $key ], $then[ $key ] ),
		];
	}

	return [ 'cards' => $out, 'now' => $now, 'then' => $then ];
}

// ══════════════════════════════════════════════════════════════
//  COURBE
// ══════════════════════════════════════════════════════════════

/** Une ligne par jour, sans trou : un jour sans visite vaut zéro. */
function cbaz_series( array $range ) {
	global $wpdb;

	$s    = cbaz_table( 'sessions' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(started_at) AS d,
			COUNT(*) AS sessions,
			SUM(pageviews) AS pageviews,
			SUM(revenue) AS revenue,
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders
		FROM {$s} WHERE started_at BETWEEN %s AND %s" . cbaz_filter_where() . "
		GROUP BY DATE(started_at) ORDER BY d ASC",
		$range['from'],
		$range['to']
	), OBJECT_K );

	// Les ventes du jour, prises chez WooCommerce : sans elles, la
	// courbe du chiffre d'affaires resterait plate sur tout
	// l'historique antérieur à l'installation.
	$shop = cbaz_shop_series( $range );

	$out    = [];
	$cursor = strtotime( $range['from'] );
	$end    = strtotime( $range['to'] );

	while ( $cursor <= $end ) {
		$day = gmdate( 'Y-m-d', $cursor );
		$r   = $rows[ $day ] ?? null;
		$d   = $shop[ $day ] ?? null;

		$out[] = [
			'date'      => $day,
			'label'     => wp_date( 'j M', $cursor ),
			'sessions'  => $r ? (int) $r->sessions : 0,
			'pageviews' => $r ? (int) $r->pageviews : 0,
			'orders'    => $d ? (int) $d->orders : 0,
			'revenue'   => $d ? (float) $d->revenue : 0,
		];

		$cursor += DAY_IN_SECONDS;
	}

	return $out;
}

/** Ventes WooCommerce jour par jour. */
function cbaz_shop_series( array $range ) {
	global $wpdb;

	$schema   = cbaz_order_schema();
	$paid     = cbaz_paid_statuses();
	$statuses = cbaz_status_list( cbaz_revenue_statuses() );
	$total    = cbaz_total_expr( 'o' );
	$refund   = cbaz_refund_expr( 'r' );
	$order_range = cbaz_order_range( $range );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(o.{$schema['date']}, '%%Y-%%m-%%d %%H:00:00') AS created,
			SUM({$total['select']}) AS revenue,
			SUM(CASE WHEN o.{$schema['status']} IN (" . cbaz_status_list( $paid ) . ") THEN 1 ELSE 0 END) AS orders
		FROM {$schema['orders']} o
		{$total['join']}
		WHERE o.{$schema['type']} = 'shop_order' AND o.{$schema['status']} IN ({$statuses})
			AND o.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ) . "
		GROUP BY DATE_FORMAT(o.{$schema['date']}, '%%Y-%%m-%%d %%H')",
		$order_range['from'],
		$order_range['to']
	) );

	$out = [];
	foreach ( $rows as $row ) {
		$day = wp_date( 'Y-m-d', cbaz_order_ts( $row->created ) );
		if ( ! isset( $out[ $day ] ) ) { $out[ $day ] = (object) [ 'orders' => 0, 'revenue' => 0.0 ]; }
		$out[ $day ]->revenue += (float) $row->revenue;
		$out[ $day ]->orders += (int) $row->orders;
	}

	$refunds = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(r.{$schema['date']}, '%%Y-%%m-%%d %%H:00:00') AS created,
			SUM({$refund['select']}) AS refunded
		FROM {$schema['orders']} r
		INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = r.{$schema['parent']}
		{$refund['join']}
		WHERE r.{$schema['type']} = 'shop_order_refund'
			AND r.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ) . "
		GROUP BY DATE_FORMAT(r.{$schema['date']}, '%%Y-%%m-%%d %%H')",
		$order_range['from'],
		$order_range['to']
	) );

	foreach ( $refunds as $row ) {
		$day = wp_date( 'Y-m-d', cbaz_order_ts( $row->created ) );
		if ( ! isset( $out[ $day ] ) ) { $out[ $day ] = (object) [ 'orders' => 0, 'revenue' => 0.0 ]; }
		$out[ $day ]->revenue -= (float) $row->refunded;
	}

	return $out;
}

/** Totaux WooCommerce par mois, avec conversion exacte des heures HPOS. */
function cbaz_shop_months( array $range, $apply_filters = true ) {
	global $wpdb;

	$schema = cbaz_order_schema();
	$total  = cbaz_total_expr( 'o' );
	$refund = cbaz_refund_expr( 'r' );
	$paid   = cbaz_status_list( cbaz_paid_statuses() );
	$all    = cbaz_status_list( cbaz_revenue_statuses() );
	$dates  = cbaz_order_range( $range );
	$filter = $apply_filters ? cbaz_filter_order_where( 'o' ) : '';
	$rows   = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(o.{$schema['date']}, '%%Y-%%m-%%d %%H:00:00') AS created,
			SUM({$total['select']}) AS revenue,
			SUM(CASE WHEN o.{$schema['status']} IN ({$paid}) THEN 1 ELSE 0 END) AS orders
		FROM {$schema['orders']} o {$total['join']}
		WHERE o.{$schema['type']} = 'shop_order' AND o.{$schema['status']} IN ({$all})
			AND o.{$schema['date']} BETWEEN %s AND %s{$filter}
		GROUP BY DATE_FORMAT(o.{$schema['date']}, '%%Y-%%m-%%d %%H')",
		$dates['from'], $dates['to']
	) );
	$refunds = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(r.{$schema['date']}, '%%Y-%%m-%%d %%H:00:00') AS created,
			SUM({$refund['select']}) AS refunded
		FROM {$schema['orders']} r
		INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = r.{$schema['parent']}
		{$refund['join']}
		WHERE r.{$schema['type']} = 'shop_order_refund' AND r.{$schema['date']} BETWEEN %s AND %s{$filter}
		GROUP BY DATE_FORMAT(r.{$schema['date']}, '%%Y-%%m-%%d %%H')",
		$dates['from'], $dates['to']
	) );
	$out = [];
	foreach ( $rows as $row ) {
		$month = wp_date( 'Y-m', cbaz_order_ts( $row->created ) );
		if ( ! isset( $out[ $month ] ) ) { $out[ $month ] = [ 'orders' => 0, 'revenue' => 0.0 ]; }
		$out[ $month ]['orders'] += (int) $row->orders;
		$out[ $month ]['revenue'] += (float) $row->revenue;
	}
	foreach ( $refunds as $row ) {
		$month = wp_date( 'Y-m', cbaz_order_ts( $row->created ) );
		if ( ! isset( $out[ $month ] ) ) { $out[ $month ] = [ 'orders' => 0, 'revenue' => 0.0 ]; }
		$out[ $month ]['revenue'] -= (float) $row->refunded;
	}

	return $out;
}

// ══════════════════════════════════════════════════════════════
//  RÉPARTITIONS
// ══════════════════════════════════════════════════════════════

function cbaz_group( $column, array $range, $limit = 10, $where = '' ) {
	global $wpdb;

	$s      = cbaz_table( 'sessions' );
	$column = preg_replace( '/[^a-z_]/', '', $column );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT {$column} AS label,
			COUNT(*) AS sessions,
			SUM(revenue) AS revenue,
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders
		FROM {$s}
		WHERE started_at BETWEEN %s AND %s {$where}" . cbaz_filter_where() . "
		GROUP BY {$column} ORDER BY sessions DESC LIMIT %d",
		$range['from'],
		$range['to'],
		$limit
	) );
}

/**
 * La période précédente, présentée comme une période à part entière.
 *
 * Permet de rejouer n'importe quelle requête de rapport sur l'intervalle
 * d'avant sans lui apprendre à le faire : cbaz_funnel( cbaz_prev_range(
 * $range ) ) donne l'entonnoir précédent, et ainsi de suite.
 */
function cbaz_prev_range( array $range ) {
	$prev = $range;

	$prev['from'] = $range['prev_from'];
	$prev['to']   = $range['prev_to'];

	return $prev;
}

/**
 * Les mêmes chiffres, sur la période PRÉCÉDENTE, indexés par libellé.
 *
 * C'est ce qui permet d'afficher une variation ligne à ligne : sans
 * point de comparaison, « 42 visites » ne dit pas si la source monte
 * ou s'effondre — et c'est pourtant la seule chose qu'on veut savoir
 * en balayant un tableau.
 *
 * Aucune limite ici, volontairement : une valeur qui figure dans le
 * classement d'aujourd'hui peut très bien avoir été onzième la
 * semaine dernière. La borner ferait apparaître de fausses créations.
 *
 * @return array libellé => objet { sessions, revenue, orders }
 */
function cbaz_group_previous( $column, array $range, $where = '' ) {
	global $wpdb;

	$s      = cbaz_table( 'sessions' );
	$column = preg_replace( '/[^a-z_]/', '', $column );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT {$column} AS label,
			COUNT(*) AS sessions,
			SUM(revenue) AS revenue,
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders
		FROM {$s}
		WHERE started_at BETWEEN %s AND %s {$where}" . cbaz_filter_where() . "
		GROUP BY {$column}",
		$range['prev_from'],
		$range['prev_to']
	) );

	$map = [];

	foreach ( $rows as $row ) {
		$map[ (string) $row->label ] = $row;
	}

	return $map;
}

/**
 * Variation d'une ligne face à la période précédente.
 *
 * Renvoie null quand la ligne n'existait pas avant : l'affichage
 * montre alors « nouveau » plutôt qu'un pourcentage inventé.
 */
function cbaz_row_delta( array $previous, $label, $now, $field = 'sessions' ) {
	$before = isset( $previous[ (string) $label ] ) ? (float) $previous[ (string) $label ]->$field : 0;

	return cbaz_delta( (float) $now, $before );
}

function cbaz_sources( array $range, $limit = 10 ) {
	global $wpdb;

	$s = cbaz_table( 'sessions' );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT source, medium,
			COUNT(*) AS sessions,
			SUM(revenue) AS revenue,
			SUM(CASE WHEN order_id > 0 THEN 1 ELSE 0 END) AS orders
		FROM {$s} WHERE started_at BETWEEN %s AND %s" . cbaz_filter_where() . "
		GROUP BY source, medium ORDER BY sessions DESC LIMIT %d",
		$range['from'],
		$range['to'],
		$limit
	) );
}

function cbaz_pages( array $range, $limit = 12 ) {
	global $wpdb;

	$v = cbaz_table( 'views' );
	$s = cbaz_table( 'sessions' );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT v.path AS label, MAX(v.title) AS title, COUNT(*) AS views, COUNT(DISTINCT v.session_id) AS sessions
		FROM {$v} v INNER JOIN {$s} s ON s.id = v.session_id
		WHERE v.viewed_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "
		GROUP BY v.path ORDER BY views DESC LIMIT %d",
		$range['from'],
		$range['to'],
		$limit
	) );
}

function cbaz_countries( array $range, $limit = 40 ) {
	return cbaz_group( 'country', $range, $limit, "AND country <> ''" );
}

// ══════════════════════════════════════════════════════════════
//  ENTONNOIR
// ══════════════════════════════════════════════════════════════

function cbaz_funnel( array $range ) {
	global $wpdb;

	$s = cbaz_table( 'sessions' );
	$e = cbaz_table( 'events' );

	$sessions = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$s} WHERE started_at BETWEEN %s AND %s" . cbaz_filter_where(),
		$range['from'],
		$range['to']
	) );

	$step = function ( $name ) use ( $wpdb, $e, $s, $range ) {
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT e.session_id) FROM {$e} e
			INNER JOIN {$s} s ON s.id = e.session_id
			WHERE e.name = %s AND s.started_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "",
			$name,
			$range['from'],
			$range['to']
		) );
	};

	$steps = [
		[ 'label' => __( 'Visites', 'shop-analytics-for-woocommerce' ), 'value' => $sessions ],
		[ 'label' => __( 'Ajout au panier', 'shop-analytics-for-woocommerce' ), 'value' => $step( 'add_to_cart' ) ],
		[ 'label' => __( 'Commande entamée', 'shop-analytics-for-woocommerce' ), 'value' => $step( 'begin_checkout' ) ],
		[ 'label' => __( 'Achat', 'shop-analytics-for-woocommerce' ), 'value' => $step( 'purchase' ) ],
	];

	// Le pourcentage se lit par rapport au départ, et la perte par
	// rapport à l'étape précédente : ce sont deux lectures différentes.
	$first = max( 1, $steps[0]['value'] );
	$prev  = null;

	foreach ( $steps as $i => $s2 ) {
		$steps[ $i ]['pct']  = round( ( $s2['value'] / $first ) * 100, 1 );
		$steps[ $i ]['drop'] = ( null === $prev || ! $prev ) ? 0 : round( ( 1 - $s2['value'] / $prev ) * 100, 1 );
		$prev                = $s2['value'];
	}

	return $steps;
}

// ══════════════════════════════════════════════════════════════
//  PRODUITS ET VENTES
//
//  Lus directement chez WooCommerce : recopier les montants créerait
//  une seconde vérité, qui finirait par diverger.
// ══════════════════════════════════════════════════════════════

function cbaz_top_products( array $range, $limit = 10 ) {
	global $wpdb;

	if ( ! function_exists( 'wc_get_order' ) ) {
		return [];
	}

	$schema   = cbaz_order_schema();
	$paid     = cbaz_status_list( cbaz_paid_statuses() );
	$statuses = cbaz_status_list( cbaz_revenue_statuses() );
	$order_range = cbaz_order_range( $range );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT MAX(activity.label) AS label, activity.product_id,
			SUM(activity.qty) AS qty, SUM(activity.revenue) AS revenue,
			COUNT(DISTINCT activity.sale_order_id) AS orders
		FROM (
			SELECT oi.order_item_name AS label,
				CAST(pid.meta_value AS UNSIGNED) AS product_id,
				CAST(qty.meta_value AS SIGNED) AS qty,
				CAST(tot.meta_value AS DECIMAL(12,2)) AS revenue,
				CASE WHEN o.{$schema['status']} IN ({$paid}) THEN oi.order_id ELSE NULL END AS sale_order_id
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty
				ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_qty'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta tot
				ON tot.order_item_id = oi.order_item_id AND tot.meta_key = '_line_total'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid
				ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
			INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = oi.order_id
			WHERE oi.order_item_type = 'line_item'
				AND o.{$schema['type']} = 'shop_order'
				AND o.{$schema['status']} IN ({$statuses})
				AND o.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ) . "
			UNION ALL
			SELECT ri.order_item_name AS label,
				CAST(rpid.meta_value AS UNSIGNED) AS product_id,
				-ABS(CAST(rqty.meta_value AS SIGNED)) AS qty,
				-ABS(CAST(rtot.meta_value AS DECIMAL(12,2))) AS revenue,
				NULL AS sale_order_id
			FROM {$wpdb->prefix}woocommerce_order_items ri
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta rqty
				ON rqty.order_item_id = ri.order_item_id AND rqty.meta_key = '_qty'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta rtot
				ON rtot.order_item_id = ri.order_item_id AND rtot.meta_key = '_line_total'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta rpid
				ON rpid.order_item_id = ri.order_item_id AND rpid.meta_key = '_product_id'
			INNER JOIN {$schema['orders']} r ON r.{$schema['id']} = ri.order_id
			INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = r.{$schema['parent']}
			WHERE ri.order_item_type = 'line_item'
				AND r.{$schema['type']} = 'shop_order_refund'
				AND r.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ) . "
		) activity
		WHERE activity.product_id > 0
		GROUP BY activity.product_id
		ORDER BY revenue DESC LIMIT %d",
		$order_range['from'],
		$order_range['to'],
		$order_range['from'],
		$order_range['to'],
		$limit
	) );

	return $rows ? $rows : [];
}

/** Ventes WooCommerce de la période, indépendamment de la mesure. */
function cbaz_shop_totals( array $range ) {
	global $wpdb;

	$schema   = cbaz_order_schema();
	$paid_statuses = cbaz_status_list( cbaz_paid_statuses() );
	$statuses = cbaz_status_list( cbaz_revenue_statuses() );
	$total    = cbaz_total_expr( 'o' );
	$refund   = cbaz_refund_expr( 'r' );
	$order_range = cbaz_order_range( $range );

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(CASE WHEN o.{$schema['status']} IN ({$paid_statuses}) THEN 1 ELSE 0 END) AS orders,
			COALESCE(SUM({$total['select']}), 0) AS revenue
		FROM {$schema['orders']} o
		{$total['join']}
		WHERE o.{$schema['type']} = 'shop_order'
			AND o.{$schema['status']} IN ({$statuses})
			AND o.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ),
		$order_range['from'],
		$order_range['to']
	) );

	$refunded = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM({$refund['select']}), 0)
		FROM {$schema['orders']} r
		INNER JOIN {$schema['orders']} o ON o.{$schema['id']} = r.{$schema['parent']}
		{$refund['join']}
		WHERE r.{$schema['type']} = 'shop_order_refund'
			AND r.{$schema['date']} BETWEEN %s AND %s" . cbaz_filter_order_where( 'o' ),
		$order_range['from'],
		$order_range['to']
	) );

	return [
		'orders'  => $row ? (int) $row->orders : 0,
		'revenue' => $row ? (float) $row->revenue - $refunded : -$refunded,
	];
}

// ══════════════════════════════════════════════════════════════
//  TEMPS RÉEL
// ══════════════════════════════════════════════════════════════

function cbaz_realtime() {
	global $wpdb;

	$s     = cbaz_table( 'sessions' );
	$v     = cbaz_table( 'views' );
	$e     = cbaz_table( 'events' );
	$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 30 * MINUTE_IN_SECONDS );

	/*
	 * Le fil mélange pages vues et évènements marchands.
	 *
	 * Un ajout au panier compte davantage qu'une page vue de plus :
	 * les voir séparément obligerait à regarder deux listes pour
	 * comprendre une seule séquence.
	 */
	$feed = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM (
			SELECT 'view' AS kind, v.path AS path, v.title AS title, 0 AS value, 0 AS object_id,
				v.viewed_at AS at, s.country, s.device, s.source
			FROM {$v} v INNER JOIN {$s} s ON s.id = v.session_id
			WHERE v.viewed_at >= %s
			UNION ALL
			SELECT e.name AS kind, e.label AS path, '' AS title, e.value, e.object_id,
				e.created_at AS at, s.country, s.device, s.source
			FROM {$e} e INNER JOIN {$s} s ON s.id = e.session_id
			WHERE e.created_at >= %s AND e.name <> 'page_time'
		) AS flux
		ORDER BY at DESC LIMIT 25",
		$since,
		$since
	) );

	return [
		'online' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE last_seen >= %s", $since ) ),
		'pages'  => $wpdb->get_results( $wpdb->prepare(
			"SELECT path AS label, COUNT(*) AS views FROM {$v} WHERE viewed_at >= %s GROUP BY path ORDER BY views DESC LIMIT 8",
			$since
		) ),
		'feed'   => $feed,
		'minutes' => $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE_FORMAT(viewed_at, '%%Y-%%m-%%d %%H:%%i') AS m, COUNT(*) AS views
			FROM {$v} WHERE viewed_at >= %s GROUP BY m ORDER BY m ASC",
			$since
		) ),
	];
}

/** Libellé et ton d'un évènement du fil. */
/**
 * Intitulé, couleur et détail d'une étape de parcours.
 *
 * $stored est le libellé retenu au moment de l'évènement — le nom du
 * produit, le terme cherché. Il sert de repli quand le produit n'existe
 * plus : sans lui, une consultation ancienne n'affichait que « produit ».
 *
 * Attention : dans les requêtes de parcours, ce libellé est aliasé en
 * « path » côté évènements. C'est cette colonne qu'il faut passer ici.
 */
function cbaz_event_label( $kind, $object_id = 0, $value = 0, $stored = '' ) {
	$map = [
		'view'           => [ __( 'Page vue', 'shop-analytics-for-woocommerce' ), 'view' ],
		'view_item'      => [ __( 'Consultation produit', 'shop-analytics-for-woocommerce' ), 'view' ],
		'add_to_cart'    => [ __( 'Ajout au panier', 'shop-analytics-for-woocommerce' ), 'cart' ],
		'view_cart'      => [ __( 'Panier consulté', 'shop-analytics-for-woocommerce' ), 'cart' ],
		'begin_checkout' => [ __( 'Début de commande', 'shop-analytics-for-woocommerce' ), 'checkout' ],
		'purchase'       => [ __( 'Commande', 'shop-analytics-for-woocommerce' ), 'order' ],
		'search'         => [ __( 'Recherche', 'shop-analytics-for-woocommerce' ), 'view' ],
	];

	$meta = $map[ $kind ] ?? [ ucfirst( str_replace( '_', ' ', $kind ) ), 'view' ];

	// Un ajout au panier n'a de sens qu'avec le produit concerné, une
	// commande qu'avec son montant.
	$detail = '';

	$url   = '';
	$price = '';

	if ( in_array( $kind, [ 'view_item', 'add_to_cart' ], true ) ) {
		$product = ( $object_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $object_id ) : null;

		if ( $product ) {
			$detail = $product->get_name();
			$url    = get_permalink( $object_id );
			$price  = wp_strip_all_tags( $product->get_price_html() );
		}

		// Nom retenu à l'époque : c'est lui qui reste quand le produit
		// a disparu du catalogue.
		if ( '' === $detail ) {
			$detail = (string) $stored;
		}
	}

	if ( 'search' === $kind ) {
		$detail = (string) $stored;
	}

	if ( 'purchase' === $kind && $value ) {
		$detail = cbaz_money( $value );
	}

	return [
		'label'  => $meta[0],
		'tone'   => $meta[1],
		'detail' => $detail,
		'url'    => $url,
		'price'  => $price,
	];
}

// ══════════════════════════════════════════════════════════════
//  FORMATAGE
// ══════════════════════════════════════════════════════════════

function cbaz_int( $n ) {
	return number_format_i18n( (float) $n, 0 );
}

function cbaz_money( $n ) {
	return function_exists( 'wc_price' )
		? wp_strip_all_tags( wc_price( (float) $n ) )
		: number_format_i18n( (float) $n, 2 ) . ' €';
}

function cbaz_pct( $n, $decimals = 2 ) {
	return number_format_i18n( (float) $n, $decimals ) . ' %';
}

function cbaz_duration( $seconds ) {
	$seconds = max( 0, (int) $seconds );

	/* translators: 1: number of minutes, 2: number of seconds. */
	return sprintf( __( '%1$d min %2$02d s', 'shop-analytics-for-woocommerce' ), intdiv( $seconds, 60 ), $seconds % 60 );
}

function cbaz_format( $value, $format ) {
	if ( 'money' === $format ) {
		return cbaz_money( $value );
	}

	if ( 'pct' === $format ) {
		return cbaz_pct( $value );
	}

	return cbaz_int( $value );
}

// ══════════════════════════════════════════════════════════════
//  MINI-COURBES
// ══════════════════════════════════════════════════════════════

/**
 * Courbe de quelques pixels, tracée dans une tuile d'indicateur.
 *
 * Un nombre seul dit où l'on en est ; la petite courbe dit comment on
 * y est arrivé — trois pics ou une montée régulière ne se pilotent pas
 * de la même façon.
 */
function cbaz_sparkline( array $values, $tone = 'ink' ) {
	$values = array_values( array_map( 'floatval', $values ) );
	$n      = count( $values );

	if ( $n < 2 ) {
		return '';
	}

	$max  = max( $values );
	$min  = min( $values );
	$span = ( $max - $min ) ?: 1;
	$w    = 200;
	$h    = 40;
	$step = $w / ( $n - 1 );

	$d = '';

	foreach ( $values as $i => $v ) {
		$x  = $i * $step;
		$y  = $h - 3 - ( ( $v - $min ) / $span ) * ( $h - 6 );
		$d .= ( 0 === $i ? 'M' : 'L' ) . round( $x, 1 ) . ' ' . round( $y, 1 ) . ' ';
	}

	$area = 'M0 ' . $h . ' ' . substr( trim( $d ), 1 ) . ' L' . $w . ' ' . $h . ' Z';

	return '<svg class="cbaz-spark cbaz-spark--' . esc_attr( $tone ) . '" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
		. '<path class="cbaz-spark__area" d="' . esc_attr( $area ) . '"></path>'
		. '<path class="cbaz-spark__line" d="' . esc_attr( trim( $d ) ) . '"></path>'
		. '</svg>';
}

// ══════════════════════════════════════════════════════════════
//  DONNÉES ISSUES DE LA COLLECTE ÉLARGIE
// ══════════════════════════════════════════════════════════════

/**
 * Performance produit par produit.
 *
 * Les vues et les ajouts au panier viennent de la mesure, les ventes
 * de WooCommerce. C'est le rapprochement des deux qui donne le taux
 * d'ajout au panier — le chiffre qui dit si un produit intéresse mais
 * ne convainc pas.
 */
function cbaz_product_funnel( array $range ) {
	global $wpdb;

	$e = cbaz_table( 'events' );
	$s = cbaz_table( 'sessions' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.object_id AS product_id,
			SUM(CASE WHEN e.name = 'view_item' THEN 1 ELSE 0 END) AS views,
			COUNT(DISTINCT CASE WHEN e.name = 'view_item' THEN e.session_id END) AS visitors,
			SUM(CASE WHEN e.name = 'add_to_cart' THEN 1 ELSE 0 END) AS carts
		FROM {$e} e
		INNER JOIN {$s} s ON s.id = e.session_id
		WHERE e.object_id > 0 AND e.name IN ('view_item','add_to_cart')
			AND e.created_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "
		GROUP BY e.object_id",
		$range['from'],
		$range['to']
	), OBJECT_K );

	return $rows ? $rows : [];
}

/** Temps moyen passé sur chaque page, en secondes. */
function cbaz_page_times( array $range ) {
	global $wpdb;

	$e = cbaz_table( 'events' );
	$s = cbaz_table( 'sessions' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.label AS path, AVG(e.value) AS seconds, COUNT(*) AS samples
		FROM {$e} e
		INNER JOIN {$s} s ON s.id = e.session_id
		WHERE e.name = 'page_time' AND e.label <> ''
			AND e.created_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "
		GROUP BY e.label",
		$range['from'],
		$range['to']
	), OBJECT_K );

	return $rows ? $rows : [];
}

/**
 * Taux de rebond page par page.
 *
 * Une page d'entrée dont la visite s'est arrêtée là : c'est la seule
 * définition qui ait un sens par page, celle du site entier ne dit
 * rien de la responsabilité de chacune.
 */
function cbaz_page_bounces( array $range ) {
	global $wpdb;

	$s = cbaz_table( 'sessions' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT entry_path AS path,
			COUNT(*) AS entries,
			SUM(CASE WHEN pageviews = 1 THEN 1 ELSE 0 END) AS bounces
		FROM {$s}
		WHERE entry_path <> '' AND started_at BETWEEN %s AND %s" . cbaz_filter_where() . "
		GROUP BY entry_path",
		$range['from'],
		$range['to']
	), OBJECT_K );

	return $rows ? $rows : [];
}

/** Recherches internes : ce qu'on cherche, avec ou sans succès. */
function cbaz_searches( array $range, $limit = 20 ) {
	global $wpdb;

	$e = cbaz_table( 'events' );
	$s = cbaz_table( 'sessions' );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT e.label AS label, COUNT(*) AS sessions, 0 AS revenue, 0 AS orders
		FROM {$e} e
		INNER JOIN {$s} s ON s.id = e.session_id
		WHERE e.name = 'search' AND e.label <> ''
			AND e.created_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "
		GROUP BY e.label ORDER BY sessions DESC LIMIT %d",
		$range['from'],
		$range['to'],
		$limit
	) );
}

/** Résolutions d'écran et langues, telles que déclarées par le navigateur. */
function cbaz_screens( array $range, $limit = 8 ) {
	return cbaz_group( 'screen', $range, $limit, "AND screen <> ''" );
}

function cbaz_langs( array $range, $limit = 8 ) {
	return cbaz_group( 'lang', $range, $limit, "AND lang <> ''" );
}

/**
 * Parcours de navigation, agrégés par étape.
 *
 * Lister les parcours entiers ne tient pas : cent visites donnent
 * facilement quatre-vingts chemins différents, tous à une ou deux
 * occurrences, et le tableau devient illisible au moment précis où il
 * aurait quelque chose à dire.
 *
 * On agrège donc par POSITION : les pages d'arrivée d'un côté, les
 * deuxièmes pages de l'autre, les troisièmes ensuite. Trois colonnes
 * courtes remplacent une liste interminable, et le passage de l'une à
 * l'autre se lit d'un coup d'œil.
 *
 * $start restreint l'analyse aux visites parties d'une page donnée :
 * c'est ce qui permet de descendre dans le détail sans tout afficher
 * d'emblée.
 */
function cbaz_journey_flow( array $range, $start = '', $depth = 3, $top = 6 ) {
	global $wpdb;

	$v = cbaz_table( 'views' );
	$s = cbaz_table( 'sessions' );

	$total_rows = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$v} v INNER JOIN {$s} s ON s.id = v.session_id
		WHERE v.viewed_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ),
		$range['from'],
		$range['to']
	) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT sample.session_id, sample.path FROM (
			SELECT v.session_id, v.path, v.viewed_at, v.id
			FROM {$v} v
			INNER JOIN {$s} s ON s.id = v.session_id
			WHERE v.viewed_at BETWEEN %s AND %s" . cbaz_filter_where( 's' ) . "
			ORDER BY v.viewed_at DESC, v.id DESC LIMIT 30000
		) sample
		ORDER BY sample.session_id ASC, sample.viewed_at ASC, sample.id ASC",
		$range['from'],
		$range['to']
	) );

	$sequences = [];

	foreach ( $rows as $row ) {
		$last = $sequences[ $row->session_id ] ? end( $sequences[ $row->session_id ] ) : null;

		// Une page rechargée ou revisitée d'affilée n'est pas une étape.
		if ( $last !== $row->path ) {
			$sequences[ $row->session_id ][] = $row->path;
		}
	}

	if ( $start ) {
		$sequences = array_filter( $sequences, fn( $seq ) => ( $seq[0] ?? '' ) === $start );
	}

	$steps    = array_fill( 0, $depth, [] );
	$stopped  = array_fill( 0, $depth, [] );
	$distinct = [];

	foreach ( $sequences as $seq ) {
		$distinct[ implode( '>', array_slice( $seq, 0, $depth ) ) ] = true;

		for ( $i = 0; $i < $depth; $i++ ) {
			if ( ! isset( $seq[ $i ] ) ) {
				break;
			}

			$path = $seq[ $i ];

			$steps[ $i ][ $path ] = ( $steps[ $i ][ $path ] ?? 0 ) + 1;

			// La visite s'arrête ici : c'est le renoncement, et c'est
			// l'information qu'on cherche vraiment.
			if ( ! isset( $seq[ $i + 1 ] ) ) {
				$stopped[ $i ][ $path ] = ( $stopped[ $i ][ $path ] ?? 0 ) + 1;
			}
		}
	}

	$out = [];

	foreach ( $steps as $i => $pages ) {
		arsort( $pages );

		$total = array_sum( $pages );
		$kept  = array_slice( $pages, 0, $top, true );
		$rest  = $total - array_sum( $kept );

		$list = [];

		foreach ( $kept as $path => $count ) {
			$list[] = [
				'path'    => $path,
				'count'   => $count,
				'stopped' => $stopped[ $i ][ $path ] ?? 0,
				'share'   => $total ? ( $count / $total ) * 100 : 0,
			];
		}

		if ( $rest > 0 ) {
			$list[] = [
				'path'    => '',
				'count'   => $rest,
				'stopped' => 0,
				'share'   => $total ? ( $rest / $total ) * 100 : 0,
				'other'   => count( $pages ) - count( $kept ),
			];
		}

		$out[] = [ 'pages' => $list, 'total' => $total ];
	}

	return [
		'steps'    => $out,
		'visits'   => count( $sequences ),
		'distinct' => count( $distinct ),
		'sampled'  => $total_rows > 30000,
		'rows'     => min( $total_rows, 30000 ),
	];
}

// ══════════════════════════════════════════════════════════════
//  VISITES INDIVIDUELLES
//
//  Une visite reste anonyme : elle porte une empreinte non
//  réversible, qui change chaque nuit, et aucune adresse IP. Ce que
//  l'on regarde ici, c'est un enchaînement de pages — pas quelqu'un.
// ══════════════════════════════════════════════════════════════

function cbaz_sessions_list( array $range, $limit = 60, $only = '', $offset = 0, $search = '' ) {
	global $wpdb;

	/*
	 * La limite est arbitrée ici, au plus près de la base : sans le module
	 * Pro, les parcours au-delà du dixième ne sont jamais lus. Les filtrer
	 * après coup reviendrait à les charger pour les cacher, ce qui n'est ni
	 * honnête ni économe.
	 */
	$limit = cbaz_journeys_limit( $limit );

	$s     = cbaz_table( 'sessions' );
	$where = '';
	$args  = [];

	// Les segments sont une fonctionnalité Pro : sans elle, aucun filtre
	// de segmentation ne peut être appliqué, même via l'URL.
	if ( ! cbaz_can( 'journeys_filters' ) ) {
		/*
		 * Free expose les dix derniers parcours GLOBAUX. Une période ou un
		 * filtre arbitraire ne doit pas servir de pagination détournée pour
		 * parcourir tout l'historique par lots de dix.
		 */
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, started_at, last_seen, pageviews, engaged_seconds, entry_path, exit_path,
				source, medium, campaign, country, device, browser, is_new, order_id, revenue,
				engaged_seconds AS duration
			FROM {$s} ORDER BY started_at DESC, id DESC LIMIT %d",
			CBAZ_FREE_JOURNEYS
		) );
	}

	if ( 'converties' === $only ) {
		$where = ' AND order_id > 0';
	} elseif ( 'rebonds' === $only ) {
		$where = ' AND pageviews = 1';
	} elseif ( 'longues' === $only ) {
		$where = ' AND pageviews >= 4';
	} elseif ( 'non-acheteurs' === $only ) {
		$where = ' AND order_id = 0';
	} elseif ( 'abandons' === $only ) {
		$events = cbaz_table( 'events' );
		$where  = " AND order_id = 0 AND EXISTS (SELECT 1 FROM {$events} je WHERE je.session_id = {$s}.id AND je.name = 'add_to_cart')";
	}

	$search = substr( sanitize_text_field( (string) $search ), 0, 120 );
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= $wpdb->prepare( ' AND (entry_path LIKE %s OR exit_path LIKE %s OR source LIKE %s OR campaign LIKE %s OR country LIKE %s OR device LIKE %s)', $like, $like, $like, $like, $like, $like );
	}

	$offset = max( 0, (int) $offset );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT id, started_at, last_seen, pageviews, engaged_seconds, entry_path, exit_path,
			source, medium, campaign, country, device, browser, is_new, order_id, revenue,
			engaged_seconds AS duration
		FROM {$s}
		WHERE started_at BETWEEN %s AND %s{$where}" . cbaz_filter_where() . "
		ORDER BY started_at DESC, id DESC LIMIT %d OFFSET %d",
		$range['from'],
		$range['to'],
		$limit,
		$offset
	) );
}

/** Nombre total de parcours Pro correspondant au segment courant. */
function cbaz_sessions_count( array $range, $only = '', $search = '' ) {
	if ( ! cbaz_can( 'journeys_full' ) ) {
		return CBAZ_FREE_JOURNEYS;
	}

	global $wpdb;
	$s      = cbaz_table( 'sessions' );
	$where  = '';

	if ( 'converties' === $only ) { $where = ' AND order_id > 0'; }
	elseif ( 'rebonds' === $only ) { $where = ' AND pageviews = 1'; }
	elseif ( 'longues' === $only ) { $where = ' AND pageviews >= 4'; }
	elseif ( 'non-acheteurs' === $only ) { $where = ' AND order_id = 0'; }
	elseif ( 'abandons' === $only ) { $events = cbaz_table( 'events' ); $where = " AND order_id = 0 AND EXISTS (SELECT 1 FROM {$events} je WHERE je.session_id = {$s}.id AND je.name = 'add_to_cart')"; }

	$search = substr( sanitize_text_field( (string) $search ), 0, 120 );
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= $wpdb->prepare( ' AND (entry_path LIKE %s OR exit_path LIKE %s OR source LIKE %s OR campaign LIKE %s OR country LIKE %s OR device LIKE %s)', $like, $like, $like, $like, $like, $like );
	}

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$s} WHERE started_at BETWEEN %s AND %s{$where}" . cbaz_filter_where(),
		$range['from'],
		$range['to']
	) );
}

/** Vérifie la barrière backend des dix parcours Free. */
function cbaz_journey_accessible( $id ) {
	if ( cbaz_can( 'journeys_full' ) ) {
		return true;
	}

	global $wpdb;
	$s = cbaz_table( 'sessions' );

	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$s} WHERE id = %d AND id IN (SELECT id FROM (SELECT id FROM {$s} ORDER BY started_at DESC, id DESC LIMIT %d) recent)",
		(int) $id,
		CBAZ_FREE_JOURNEYS
	) );
}

/**
 * Déroulé de PLUSIEURS visites en une requête.
 *
 * Afficher quarante parcours en appelant quarante fois la fonction
 * ci-dessous ferait quarante requêtes : on lit tout d'un coup, puis
 * on regroupe en mémoire.
 *
 * @return array session_id => étapes, dans l'ordre chronologique.
 */
/** Une page lisible plutôt qu'un chemin brut. */
function cbaz_pretty_path( $path ) {
	if ( '' === $path ) {
		return '';
	}

	return '/' === $path ? __( 'Accueil', 'shop-analytics-for-woocommerce' ) : trim( $path, '/' );
}

/** Une étape de parcours : son intitulé, sa nature et sa couleur. */
function cbaz_trail_step( $step ) {
	$ev = cbaz_event_label( $step->kind, $step->object_id, $step->value, $step->path );

	if ( 'view' === $step->kind ) {
		$nom = $step->title ? $step->title : cbaz_pretty_path( $step->path );
	} else {
		$nom = $ev['detail'] ? $ev['detail'] : ( $step->path ? $step->path : $ev['label'] );
	}

	return [
		'nom'  => $nom ? $nom : $ev['label'],
		'quoi' => 'view' === $step->kind ? '' : $ev['label'],
		'tone' => $ev['tone'],
		'at'   => strtotime( $step->at ),
	];
}

function cbaz_sessions_trails( array $ids ) {
	global $wpdb;

	$ids = array_filter( array_map( 'intval', $ids ) );
	if ( ! cbaz_can( 'journeys_full' ) ) {
		$ids = array_values( array_filter( $ids, 'cbaz_journey_accessible' ) );
	}

	if ( ! $ids ) {
		return [];
	}

	$v = cbaz_table( 'views' );
	$e = cbaz_table( 'events' );

	/*
	 * Les identifiants sont déjà passés par intval, donc inoffensifs.
	 * On les fait quand même transiter par prepare() : une requête
	 * assemblée à la main reste une requête assemblée à la main, et
	 * c'est le genre de raccourci qui survit à une relecture puis se
	 * fait copier ailleurs, là où l'entrée n'est plus filtrée.
	 */
	$places = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM (
			SELECT session_id, 'view' AS kind, path, title, 0 AS value, 0 AS object_id, viewed_at AS at
			FROM {$v} WHERE session_id IN ({$places})
			UNION ALL
			SELECT session_id, name AS kind, label AS path, '' AS title, value, object_id, created_at AS at
			FROM {$e} WHERE session_id IN ({$places}) AND name <> 'page_time'
		) AS flux ORDER BY at ASC",
		array_merge( array_values( $ids ), array_values( $ids ) )
	) );

	$out = [];

	foreach ( $rows as $r ) {
		$out[ (int) $r->session_id ][] = $r;
	}

	return $out;
}

function cbaz_session( $id ) {
	global $wpdb;
	if ( ! cbaz_journey_accessible( $id ) ) {
		return null;
	}

	$s = cbaz_table( 'sessions' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$s} WHERE id = %d", (int) $id ) );
}

/**
 * Déroulé d'une visite : pages et évènements, dans l'ordre.
 *
 * Les deux tables sont réunies plutôt que présentées séparément —
 * l'intérêt d'un parcours, c'est justement de voir l'ajout au panier
 * tomber entre deux pages précises.
 */
function cbaz_session_timeline( $id ) {
	global $wpdb;
	if ( ! cbaz_journey_accessible( $id ) ) {
		return [];
	}

	$v = cbaz_table( 'views' );
	$e = cbaz_table( 'events' );

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM (
			SELECT 'view' AS kind, path, title, 0 AS value, 0 AS object_id, viewed_at AS at
			FROM {$v} WHERE session_id = %d
			UNION ALL
			SELECT name AS kind, label AS path, '' AS title, value, object_id, created_at AS at
			FROM {$e} WHERE session_id = %d AND name <> 'page_time'
		) AS flux ORDER BY at ASC",
		$id,
		$id
	) );
}
