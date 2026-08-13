<?php
/** Acquisition : d'où viennent les visites. */

defined( 'ABSPATH' ) || exit;

$sources  = cbaz_sources( $range, 30 );
$mediums  = cbaz_group( 'medium', $range, 12 );
$referrers = cbaz_group( 'referrer_host', $range, 10, "AND referrer_host <> ''" );

// L'état de la période précédente, ligne à ligne : un classement sans
// point de comparaison ne dit pas ce qui monte ni ce qui s'effondre.
$prev_source   = cbaz_group_previous( 'source', $range );
$prev_medium   = cbaz_group_previous( 'medium', $range );
$prev_referrer = cbaz_group_previous( 'referrer_host', $range, "AND referrer_host <> ''" );
$max      = $sources ? max( array_map( fn( $s ) => (int) $s->sessions, $sources ) ) : 1;
?>

<?php
$totals = cbaz_totals( $range['from'], $range['to'] );
$prev   = cbaz_totals( $range['prev_from'], $range['prev_to'] );
$series = cbaz_series( $range );

$cards = [
	[ __( 'Visiteurs', 'shop-analytics-for-woocommerce' ), cbaz_int( $totals['visitors'] ), cbaz_delta( $totals['visitors'], $prev['visitors'] ), wp_list_pluck( $series, 'sessions' ) ],
	[ __( 'Visites', 'shop-analytics-for-woocommerce' ), cbaz_int( $totals['sessions'] ), cbaz_delta( $totals['sessions'], $prev['sessions'] ), wp_list_pluck( $series, 'sessions' ) ],
	[ __( 'Commandes', 'shop-analytics-for-woocommerce' ), cbaz_int( $totals['orders'] ), cbaz_delta( $totals['orders'], $prev['orders'] ), wp_list_pluck( $series, 'orders' ) ],
	[ __( "Chiffre d'affaires", 'shop-analytics-for-woocommerce' ), cbaz_money( $totals['revenue'] ), cbaz_delta( $totals['revenue'], $prev['revenue'] ), wp_list_pluck( $series, 'revenue' ) ],
	[ __( 'Taux de conversion', 'shop-analytics-for-woocommerce' ), cbaz_pct( $totals['cr'] ), cbaz_delta( $totals['cr'], $prev['cr'] ), wp_list_pluck( $series, 'orders' ) ],
	[ __( 'Panier moyen', 'shop-analytics-for-woocommerce' ), cbaz_money( $totals['aov'] ), cbaz_delta( $totals['aov'], $prev['aov'] ), wp_list_pluck( $series, 'revenue' ) ],
];
?>

<div class="cbaz-kpis">
	<?php foreach ( $cards as list( $label, $value, $delta, $spark ) ) : ?>
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( $label ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html( $label ); ?></p>
			<p class="cbaz-kpi__value"><?php echo esc_html( $value ); ?></p>
			<p class="cbaz-kpi__delta"><?php echo cbaz_delta_badge( $delta ); // phpcs:ignore ?><span class="cbaz-kpi__vs"><?php echo esc_html__( 'vs période préc.', 'shop-analytics-for-woocommerce' ); ?></span></p>
			<?php echo cbaz_sparkline( $spark ); // phpcs:ignore ?>
		</div>
	<?php endforeach; ?>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Sources de trafic', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Cherchez, filtrez et exportez la performance de chaque source.', 'shop-analytics-for-woocommerce' ); ?></p></div>
	</header>

	<?php echo cbaz_table_tools( __( 'Rechercher une source…', 'shop-analytics-for-woocommerce' ), 'sources' ); // phpcs:ignore ?>

	<table class="cbaz-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Source', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html_x( 'Support', 'traffic medium', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Visites', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Évolution', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Conversion', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html_x( 'CA', 'revenue abbreviation', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Panier moyen', 'shop-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $sources as $i => $s ) : ?>
			<tr>
				<td>
					<?php echo cbaz_source_brand( $s->source ); // phpcs:ignore ?>
					<strong><?php echo esc_html( $s->source ); ?></strong>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $s->sessions, $max ) ); // phpcs:ignore ?>
				</td>
				<td><span class="cbaz-pill"><?php echo esc_html( $s->medium ); ?></span></td>
				<td class="num"><?php echo esc_html( cbaz_int( $s->sessions ) ); ?></td>
				<td class="num"><?php echo cbaz_delta_badge( cbaz_row_delta( $prev_source, $s->source, $s->sessions ) ); // phpcs:ignore ?></td>
				<td class="num"><?php echo esc_html( cbaz_int( $s->orders ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_pct( $s->sessions ? ( $s->orders / $s->sessions ) * 100 : 0 ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $s->revenue ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $s->orders ? $s->revenue / $s->orders : 0 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $sources ) : ?><tr><td colspan="8" class="cbaz-empty"><?php echo esc_html__( 'Aucune visite sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>

	<?php echo cbaz_table_count( count( $sources ), count( $sources ), __( 'sources', 'shop-analytics-for-woocommerce' ) ); // phpcs:ignore ?>
</section>

<div class="cbaz-grid cbaz-grid--2">
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Chiffre d’affaires par canal', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php
		echo cbaz_bars_chart( array_map(
			fn( $m ) => [ 'label' => $m->label ? $m->label : '(direct)', 'value' => (float) $m->revenue ],
			array_slice( $mediums, 0, 6 )
		) ); // phpcs:ignore
		?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'chart', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Conversion par canal', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php
		echo cbaz_hbars( array_map(
			fn( $m ) => [
				'label' => $m->label ? $m->label : '(direct)',
				'value' => $m->sessions ? ( $m->orders / $m->sessions ) * 100 : 0,
			],
			array_slice( $mediums, 0, 8 )
		), 'pct' ); // phpcs:ignore
		?>
	</section>
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'path', 4 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Sites référents', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Les sites qui envoient du monde chez toi.', 'shop-analytics-for-woocommerce' ); ?></p></div></header>
		<ul class="cbaz-list">
			<?php $maxr = $referrers ? max( array_map( fn( $r ) => (int) $r->sessions, $referrers ) ) : 1; ?>
			<?php foreach ( $referrers as $i => $r ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo cbaz_source_brand( $r->label ); // phpcs:ignore ?><?php echo esc_html( $r->label ); ?></span>
						<span class="cbaz-num">
							<span class="cbaz-list__delta"><?php echo cbaz_delta_badge( cbaz_row_delta( $prev_referrer, $r->label, $r->sessions ) ); // phpcs:ignore ?></span>
							<?php echo esc_html( cbaz_int( $r->sessions ) ); ?>
						</span>
					</div>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $r->sessions, $maxr ) ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $referrers ) : ?>
				<li class="cbaz-empty"><?php echo esc_html__( 'Aucun site référent : les visites arrivent en direct ou par des liens sans référent.', 'shop-analytics-for-woocommerce' ); ?></li>
			<?php endif; ?>
		</ul>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'users', 4 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Répartition des visites', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php echo cbaz_donut( array_slice( $mediums, 0, 5 ), max( 1, (int) $totals['sessions'] ) ); // phpcs:ignore ?>
	</section>
</div>
