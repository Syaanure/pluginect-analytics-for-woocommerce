<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/**
 * Géographie.
 *
 * Le globe est dessiné en SVG à partir d'un maillage calculé : ni
 * bibliothèque 3D, ni fond de carte à télécharger. Les fenêtres
 * d'information sont posées à côté et non par-dessus, pour ne jamais
 * masquer ce qu'on essaie de lire.
 */

defined( 'ABSPATH' ) || exit;

$rows   = cbaz_countries( $range, 60 );
$prev_country = cbaz_group_previous( 'country', $range, "AND country <> ''" );
$points = cbaz_map_points( $rows );

/*
 * Le faisceau converge sur la BOUTIQUE, pas sur le premier pays du
 * classement. C'est la lecture qu'on attend d'une telle carte : d'où
 * vient-on vers moi. WooCommerce connaît l'adresse du magasin ; à
 * défaut, on retombe sur la France.
 */
$home = 'FR';

if ( function_exists( 'WC' ) && WC()->countries ) {
	$base = WC()->countries->get_base_country();
	$home = $base ? strtoupper( $base ) : 'FR';
}

$home_point = cbaz_home_point( $home );
$total  = array_sum( array_map( fn( $r ) => (int) $r->sessions, $rows ) );
$max    = $rows ? max( array_map( fn( $r ) => (int) $r->sessions, $rows ) ) : 1;
?>

<?php if ( ! $rows ) : ?>
	<div class="cbaz-notice cbaz-notice--info">
		<strong><?php echo esc_html__( 'The country is not yet known.', 'pluginect-analytics-for-woocommerce' ); ?></strong>
		<?php echo esc_html__( "It is read in a header provided by the host or by Cloudflare, and failing that in the billing address of an order.\n\t\tNo GeoIP database is on board: it would weigh several megabytes and would require a monthly update.\n\t\tThe orders will therefore inform the countries as they arise.", 'pluginect-analytics-for-woocommerce' ); ?>
	</div>
<?php endif; ?>

<?php cbaz_insights_box( $range, 'geographie' ); ?>

<div class="cbaz-grid cbaz-grid--globe">
	<section class="cbaz-card cbaz-card--globe">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Location of visitors', 'pluginect-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Hover over a country to view its details.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
			<button type="button" class="cbaz-ctrl cbaz-ctrl--mini" data-cbaz-spin><?php echo esc_html__( 'Pause rotation', 'pluginect-analytics-for-woocommerce' ); ?></button>
		</header>

		<div class="cbaz-globe" data-cbaz-globe
		     data-metric="sessions"
		     data-home="<?php echo esc_attr( wp_json_encode( $home_point ) ); ?>"
		     data-points="<?php echo esc_attr( wp_json_encode( $points ) ); ?>">
			<svg viewBox="-152 -152 304 304" role="img" aria-label="<?php echo esc_attr__( 'Globe of visits by country', 'pluginect-analytics-for-woocommerce' ); ?>"></svg>

			<div class="cbaz-globe__tools">
				<button type="button" data-cbaz-zoom="+" aria-label="<?php echo esc_attr__( 'Zoom', 'pluginect-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Zoom', 'pluginect-analytics-for-woocommerce' ); ?>">+</button>
				<button type="button" data-cbaz-zoom="-" aria-label="<?php echo esc_attr__( 'Zoom out', 'pluginect-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Zoom out', 'pluginect-analytics-for-woocommerce' ); ?>">−</button>
				<button type="button" data-cbaz-reset aria-label="<?php echo esc_attr__( 'Return to initial view', 'pluginect-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Refocus', 'pluginect-analytics-for-woocommerce' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="6"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>
				</button>
			</div>
		</div>

		<div class="cbaz-floats">
			<div class="cbaz-float">
				<p class="cbaz-float__label"><?php echo esc_html__( 'Visitors currently present', 'pluginect-analytics-for-woocommerce' ); ?></p>
				<p class="cbaz-float__value"><?php echo esc_html( cbaz_int( cbaz_realtime()['online'] ) ); ?></p>
			</div>

			<div class="cbaz-float">
				<p class="cbaz-float__label"><?php echo esc_html__( 'Total sales over the period', 'pluginect-analytics-for-woocommerce' ); ?></p>
				<p class="cbaz-float__value"><?php echo esc_html( cbaz_money( array_sum( array_map( fn( $r ) => (float) $r->revenue, $rows ) ) ) ); ?></p>
			</div>

			<div class="cbaz-float cbaz-float--wide">
				<p class="cbaz-float__title"><?php echo esc_html__( 'Main locations', 'pluginect-analytics-for-woocommerce' ); ?></p>
				<?php foreach ( array_slice( $rows, 0, 3 ) as $r ) : ?>
					<div class="cbaz-float__row">
						<span><?php echo cbaz_country_flag( $r->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $r->label ) ); ?></span>
						<?php echo cbaz_bar( $r->sessions, $max ); // phpcs:ignore ?>
						<em><?php echo esc_html( cbaz_int( $r->sessions ) ); ?></em>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><p class="cbaz-empty"><?php echo esc_html__( 'No country identified.', 'pluginect-analytics-for-woocommerce' ); ?></p><?php endif; ?>
			</div>
		</div>
		<p class="cbaz-note">
			<?php echo esc_html__( 'Drag to rotate, hover over a country for its detail.', 'pluginect-analytics-for-woocommerce' ); ?>
			<?php if ( $rows ) : ?>
				<?php /* translators: 1: maximum visit count, 2: country name. */ printf( esc_html__( 'The size of a point follows its number of visits — %1$s at most, for %2$s.', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $max ) ), esc_html( cbaz_country_name( $rows[0]->label ) ) ); ?>
			<?php endif; ?>
		</p>
	</section>

	<div class="cbaz-stack">
		<section class="cbaz-card cbaz-card--readout">
			<header class="cbaz-card__head"><?php echo cbaz_icon( 'page', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Detail', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>

			<div class="cbaz-readout" data-cbaz-readout>
				<p class="cbaz-readout__hint"><?php echo esc_html__( 'Hover over a country on the globe.', 'pluginect-analytics-for-woocommerce' ); ?></p>
			</div>
		</section>

		<section class="cbaz-card">
			<header class="cbaz-card__head"><?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top countries', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'countries' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Ranking by visitors over the period.', 'pluginect-analytics-for-woocommerce' ); ?></p></div></header>
			<ul class="cbaz-list">
				<?php foreach ( $rows as $r ) : ?>
					<li data-cbaz-country="<?php echo esc_attr( strtoupper( $r->label ) ); ?>">
						<div class="cbaz-list__row">
							<span><?php echo cbaz_country_flag( $r->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $r->label ) ); ?></span>
							<span class="cbaz-num">
								<span class="cbaz-list__delta"><?php echo cbaz_delta_badge( cbaz_row_delta( $prev_country, $r->label, $r->sessions ) ); // phpcs:ignore ?></span>
								<?php echo esc_html( cbaz_int( $r->sessions ) ); ?>
							</span>
						</div>
						<?php echo cbaz_bar( $r->sessions, $max ); // phpcs:ignore ?>
						<p class="cbaz-list__note"><?php /* translators: 1: share of visits, 2: revenue. */ printf( esc_html__( '%1$s of visits · %2$s', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_pct( $total ? ( $r->sessions / $total ) * 100 : 0, 1 ) ), esc_html( cbaz_money( $r->revenue ) ) ); ?></p>
					</li>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'No country known during the period.', 'pluginect-analytics-for-woocommerce' ); ?></li><?php endif; ?>
			</ul>
		</section>
	</div>
</div>
