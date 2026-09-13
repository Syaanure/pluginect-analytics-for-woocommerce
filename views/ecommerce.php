<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/**
 * E-commerce.
 *
 * Deux chiffres cohabitent volontairement : ce que WooCommerce a
 * réellement encaissé, et ce que la mesure a su rattacher à une visite.
 * L'écart entre les deux est une information — il révèle les commandes
 * passées hors mesure (par téléphone, depuis un lien exclu, ou par une
 * personne dont la visite avait expiré).
 */

defined( 'ABSPATH' ) || exit;

$totals = cbaz_totals( $range['from'], $range['to'] );
$shop   = cbaz_shop_totals( $range );
$series = cbaz_series( $range );
$funnel = cbaz_funnel( $range );
$funnel_prev = cbaz_funnel( cbaz_prev_range( $range ) );
$srcs   = cbaz_sources( $range, 12 );
$prev   = cbaz_totals( $range['prev_from'], $range['prev_to'] );

// Part du chiffre d'affaires qu'une visite mesurée explique. Le reste
// vient de commandes antérieures à la mesure, ou passées hors ligne.
$coverage = $shop['revenue'] ? ( $totals['attributed'] / $shop['revenue'] ) * 100 : 0;
?>

<div class="cbaz-kpis">
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA WooCommerce' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'WooCommerce revenue', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'revenue' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $shop['revenue'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'orders' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $shop['orders'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Panier moyen' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Average basket', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'aov' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $shop['orders'] ? $shop['revenue'] / $shop['orders'] : 0 ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Taux de conversion' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Conversion rate', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'cr' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_pct( $totals['cr'] ) ); ?></p><?php echo cbaz_delta_badge( cbaz_delta( $totals['cr'], $prev['cr'] ) ); // phpcs:ignore ?></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Valeur par visite' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Value per visit', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'per_session' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $totals['per_session'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA rattaché' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Attributed revenue', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'attributed' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_pct( $coverage, 0 ) ); ?></p><p class="cbaz-kpi__note"><?php echo esc_html__( 'of revenue linked to a visit', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
</div>

<?php cbaz_insights_box( $range, 'ecommerce' ); ?>

<section class="cbaz-card">
	<header class="cbaz-card__head"><?php echo cbaz_icon( 'chart', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Revenue and visits', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>
	<?php echo cbaz_chart( $series ); // phpcs:ignore ?>
</section>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div>
				<h2><?php echo esc_html__( 'Conversion funnel', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'funnel' ); // phpcs:ignore ?></h2>
				<p><?php echo esc_html__( 'Visits → Add to cart → Order started → Purchase', 'pluginect-analytics-for-woocommerce' ); ?></p>
			</div>
		</header>

		<?php echo cbaz_funnel_block( $funnel, $funnel_prev ); // phpcs:ignore ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Vanishing points', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'leaks' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Losses between each stage.', 'pluginect-analytics-for-woocommerce' ); ?></p></div></header>
		<?php echo cbaz_leaks_block( $funnel, $funnel_prev ); // phpcs:ignore ?>
		<p class="cbaz-note"><?php echo esc_html__( 'The difference between WooCommerce revenue and attributed revenue represents orders that cannot be linked to a measured visit.', 'pluginect-analytics-for-woocommerce' ); ?></p>
	</section>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Profitability by source', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'channel_revenue' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'What each channel actually earns, visit by visit.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
	</header>

	<table class="cbaz-table">
		<thead><tr><th><?php echo esc_html__( 'Source', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Visits', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Conversion', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html_x( 'Revenue', 'revenue abbreviation', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Per visit', 'pluginect-analytics-for-woocommerce' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $srcs as $i => $s ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $s->source ); ?></strong>
					<span class="cbaz-pill"><?php echo esc_html( $s->medium ); ?></span>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $s->revenue, max( 1, max( array_map( fn( $x ) => (float) $x->revenue, $srcs ) ) ) ) ); // phpcs:ignore ?>
				</td>
				<td class="num"><?php echo esc_html( cbaz_int( $s->sessions ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_int( $s->orders ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_pct( $s->sessions ? ( $s->orders / $s->sessions ) * 100 : 0 ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $s->revenue ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $s->sessions ? $s->revenue / $s->sessions : 0 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $srcs ) : ?><tr><td colspan="6" class="cbaz-empty"><?php echo esc_html__( 'No visits during the period.', 'pluginect-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
</section>
