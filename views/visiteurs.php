<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/** Visiteurs : appareils, technologies, fidélité. */

defined( 'ABSPATH' ) || exit;

$totals   = cbaz_totals( $range['from'], $range['to'] );
$prev     = cbaz_totals( $range['prev_from'], $range['prev_to'] );
$devices  = cbaz_group( 'device', $range, 5, "AND device <> ''" );
$systems  = cbaz_group( 'os', $range, 6, "AND os <> ''" );
$browsers = cbaz_group( 'browser', $range, 6, "AND browser <> ''" );
$countries = cbaz_countries( $range, 8 );
$screens   = cbaz_screens( $range, 6 );
$langs     = cbaz_langs( $range, 6 );

$loyalty = cbaz_group( 'is_new', $range, 2 );

// Chaque liste connaît son état de la période précédente : c'est ce qui
// permet d'afficher une variation ligne à ligne plutôt qu'un classement
// muet, où l'on ignore si une valeur monte ou s'effondre.
$prev_os      = cbaz_group_previous( 'os', $range, "AND os <> ''" );
$prev_browser = cbaz_group_previous( 'browser', $range, "AND browser <> ''" );
$prev_screen  = cbaz_group_previous( 'screen', $range, "AND screen <> ''" );
$prev_lang    = cbaz_group_previous( 'lang', $range, "AND lang <> ''" );
$prev_new     = cbaz_group_previous( 'is_new', $range );
$sessions_total = max( 1, (int) $totals['sessions'] );

$kpis = [
	[ __( 'Unique visitors', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $totals['visitors'] ), cbaz_delta( $totals['visitors'], $prev['visitors'] ), false, 'visitors' ],
	[ __( 'Visits per visitor', 'pluginect-analytics-for-woocommerce' ), number_format_i18n( $totals['visitors'] ? $totals['sessions'] / $totals['visitors'] : 0, 2 ), cbaz_delta( $totals['sessions'], $prev['sessions'] ), false, 'visits_per_visitor' ],
	[ __( 'Average duration', 'pluginect-analytics-for-woocommerce' ), cbaz_duration( $totals['duration'] ), cbaz_delta( $totals['duration'], $prev['duration'] ), false, 'duration' ],
	[ __( 'Pages per visit', 'pluginect-analytics-for-woocommerce' ), number_format_i18n( $totals['sessions'] ? $totals['pageviews'] / $totals['sessions'] : 0, 1 ), cbaz_delta( $totals['pageviews'], $prev['pageviews'] ), false, 'pages_per_visit' ],
	[ __( 'Bounce rate', 'pluginect-analytics-for-woocommerce' ), cbaz_pct( $totals['bounce_rate'], 1 ), cbaz_delta( $totals['bounce_rate'], $prev['bounce_rate'] ), true, 'bounce' ],
];
?>

<div class="cbaz-kpis">
	<?php foreach ( $kpis as list( $label, $value, $delta, $invert, $help ) ) : ?>
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( $label ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html( $label ); ?><?php echo cbaz_tip( $help ); // phpcs:ignore ?></p>
			<p class="cbaz-kpi__value"><?php echo esc_html( $value ); ?></p>
			<p class="cbaz-kpi__delta">
				<?php echo cbaz_delta_badge( $delta, $invert ); // phpcs:ignore ?>
				<span class="cbaz-kpi__vs"><?php echo esc_html__( 'vs previous period', 'pluginect-analytics-for-woocommerce' ); ?></span>
			</p>
		</div>
	<?php endforeach; ?>
</div>

<?php cbaz_insights_box( $range, 'visiteurs' ); ?>

<div class="cbaz-grid cbaz-grid--3">
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'device', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Devices', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'devices' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Distribution of visits.', 'pluginect-analytics-for-woocommerce' ); ?></p></div></header>
		<?php echo cbaz_donut( $devices, $sessions_total ); // phpcs:ignore ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'os', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Operating systems', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php cbaz_meter_list( $systems, $sessions_total, $prev_os ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'browser', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Browsers', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php cbaz_meter_list( $browsers, $sessions_total, $prev_browser ); ?>
	</section>
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'users', 4 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'New or recurring', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'new_returning' ); // phpcs:ignore ?></h2></div></header>
		<ul class="cbaz-list">
			<?php foreach ( $loyalty as $l ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo esc_html( $l->label ? __( 'New visitors', 'pluginect-analytics-for-woocommerce' ) : __( 'Returning visitors', 'pluginect-analytics-for-woocommerce' ) ); ?></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $l->sessions ) ); ?> · <?php echo esc_html( cbaz_pct( ( $l->sessions / $sessions_total ) * 100, 1 ) ); ?></span>
					</div>
					<?php echo cbaz_bar( $l->sessions, $sessions_total ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $loyalty ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'No visits during the period.', 'pluginect-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>
		<p class="cbaz-note">
			<?php echo esc_html__( "“Recurring” here means: income during the same day. The imprint changing every night,\n\t\t\ta visitor who returns tomorrow will be counted as new — this is the price of not having an identifier.", 'pluginect-analytics-for-woocommerce' ); ?>
		</p>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Conversion by country', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'countries' ); // phpcs:ignore ?></h2></div></header>
		<table class="cbaz-table">
			<thead><tr><th><?php echo esc_html__( 'Country', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Visits', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Conv.', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html_x( 'Revenue', 'revenue abbreviation', 'pluginect-analytics-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $countries as $c ) : ?>
				<tr>
					<td><?php echo cbaz_country_flag( $c->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $c->label ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $c->sessions ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_pct( $c->sessions ? ( $c->orders / $c->sessions ) * 100 : 0 ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_money( $c->revenue ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $countries ) : ?><tr><td colspan="4" class="cbaz-empty"><?php echo esc_html__( 'Countries not indicated — see Geography.', 'pluginect-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'screen', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Screen resolutions', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'screens' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'What to test the store on first?', 'pluginect-analytics-for-woocommerce' ); ?></p></div></header>
		<?php cbaz_meter_list( $screens, $sessions_total, $prev_screen ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'language', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Languages', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php cbaz_meter_list( $langs, $sessions_total, $prev_lang ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'loyalty', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Loyalty', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'new_returning' ); // phpcs:ignore ?></h2></div></header>
		<dl class="cbaz-stats">
			<div><dt><?php echo esc_html__( 'Visits', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $totals['sessions'] ) ); ?></dd></div>
			<div><dt><?php echo esc_html__( 'Unique visitors', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $totals['visitors'] ) ); ?></dd></div>
			<div><dt><?php echo esc_html__( 'Page views', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $totals['pageviews'] ) ); ?></dd></div>
			<div><dt><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $totals['orders'] ) ); ?></dd></div>
		</dl>
	</section>
</div>
