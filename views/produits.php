<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/** Produits : ce qui se vend. */

defined( 'ABSPATH' ) || exit;

$products = cbaz_top_products( $range, 40 );

// Les mêmes produits sur la période précédente, indexés par
// identifiant : c'est ce qui permet de dire lequel décolle et lequel
// s'essouffle, au lieu d'un simple classement du moment.
$prev_products = [];

foreach ( cbaz_top_products( cbaz_prev_range( $range ), 200 ) as $old ) {
	$prev_products[ (int) $old->product_id ] = $old;
}
$funnel   = cbaz_product_funnel( $range );

// Les ventes portent maintenant l'identifiant du produit : le
// rapprochement avec la mesure se fait dessus, et non plus sur le nom
// — un article renommé cassait le lien.
$products_cache = [];

foreach ( $products as $p ) {
	if ( $p->product_id && function_exists( 'wc_get_product' ) ) {
		$products_cache[ $p->product_id ] = wc_get_product( $p->product_id );
	}
}
$max      = $products ? max( array_map( fn( $p ) => (float) $p->revenue, $products ) ) : 1;
$qty      = array_sum( array_map( fn( $p ) => (int) $p->qty, $products ) );
$rev      = array_sum( array_map( fn( $p ) => (float) $p->revenue, $products ) );
?>

<div class="cbaz-kpis cbaz-kpis--4">
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Références vendues' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'References sold', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'references_sold' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( count( $products ) ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Articles vendus' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Items sold', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'items_sold' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $qty ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA produits' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'CA products', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'products_revenue' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $rev ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Prix moyen' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Average price', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'average_price' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $qty ? $rev / $qty : 0 ) ); ?></p></div>
</div>

<?php cbaz_insights_box( $range, 'produits' ); ?>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'box', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top performing products', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'product_views' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Completed, in progress and pending orders. The amounts come from WooCommerce.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
		<?php
		$tri = cbaz_subtabs( 'tri', [
			'vues'     => __( 'Most viewed', 'pluginect-analytics-for-woocommerce' ),
			'paniers'  => __( 'More added to cart', 'pluginect-analytics-for-woocommerce' ),
			'quantite' => __( 'Most sold', 'pluginect-analytics-for-woocommerce' ),
			'ca'       => __( 'Highest revenue', 'pluginect-analytics-for-woocommerce' ),
			'conv'     => __( 'Better conversion', 'pluginect-analytics-for-woocommerce' ),
		], 'ca' );

		$vues = fn( $p ) => isset( $funnel[ $p->product_id ] ) ? (int) $funnel[ $p->product_id ]->views : 0;
		$pan  = fn( $p ) => isset( $funnel[ $p->product_id ] ) ? (int) $funnel[ $p->product_id ]->carts : 0;

		if ( 'quantite' === $tri ) {
			usort( $products, fn( $a, $b ) => $b->qty <=> $a->qty );
		} elseif ( 'vues' === $tri ) {
			usort( $products, fn( $a, $b ) => $vues( $b ) <=> $vues( $a ) );
		} elseif ( 'paniers' === $tri ) {
			usort( $products, fn( $a, $b ) => $pan( $b ) <=> $pan( $a ) );
		} elseif ( 'conv' === $tri ) {
			// À vues égales, c'est le rapport commandes / vues qui
			// départage : un produit vu dix fois et vendu deux fois vaut
			// mieux qu'un produit vu mille fois et vendu dix.
			$taux = fn( $p ) => $vues( $p ) ? $p->orders / $vues( $p ) : 0;
			usort( $products, fn( $a, $b ) => $taux( $b ) <=> $taux( $a ) );
		}
		?>
	</header>

	<ul class="cbaz-rank">
		<?php foreach ( array_slice( $products, 0, 5 ) as $i => $p ) : ?>
			<?php
			$vignette = $products_cache[ $p->product_id ] ?? null;
			$photo    = $vignette ? wp_get_attachment_image_url( $vignette->get_image_id(), 'thumbnail' ) : '';
			?>
			<li>
				<span class="cbaz-rank__n"><?php echo (int) ( $i + 1 ); ?></span>
				<?php if ( $photo ) : ?>
					<img class="cbaz-thumb" src="<?php echo esc_url( $photo ); ?>" alt="" loading="lazy">
				<?php else : ?>
					<span class="cbaz-thumb"></span>
				<?php endif; ?>
				<span class="cbaz-rank__name"><?php echo esc_html( $p->label ); ?></span>
				<span class="cbaz-rank__bar"><?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $p->revenue, $max ) ); // phpcs:ignore ?></span>
				<span class="cbaz-rank__value"><?php echo esc_html( cbaz_money( $p->revenue ) ); ?></span>
			</li>
		<?php endforeach; ?>
		<?php if ( ! $products ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'No sales during the period.', 'pluginect-analytics-for-woocommerce' ); ?></li><?php endif; ?>
	</ul>
</section>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'box', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'All products', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'add_rate' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Quantities, orders and share of revenue.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
	</header>

	<?php echo cbaz_table_tools( __( 'Search for a product…', 'pluginect-analytics-for-woocommerce' ), 'produits' ); // phpcs:ignore ?>

	<table class="cbaz-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Product', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Views', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Visitors', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Adds to cart', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Addition rate', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Conversion', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Quantities', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html_x( 'Revenue', 'revenue abbreviation', 'pluginect-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Evolution', 'pluginect-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $products as $i => $p ) : ?>
			<?php
			$f       = $funnel[ $p->product_id ] ?? null;
			$views   = $f ? (int) $f->views : 0;
			$carts   = $f ? (int) $f->carts : 0;
			$product = $products_cache[ $p->product_id ] ?? null;
			$image   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
			?>
			<tr>
				<td>
					<span class="cbaz-prod">
						<?php if ( $image ) : ?>
							<img class="cbaz-thumb" src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy">
						<?php else : ?>
							<span class="cbaz-thumb"></span>
						<?php endif; ?>
						<span>
							<span class="cbaz-prod__name"><?php echo esc_html( $p->label ); ?></span>
							<?php if ( $product ) : ?>
								<span class="cbaz-prod__price"><?php echo esc_html( wp_strip_all_tags( $product->get_price_html() ) ); ?></span>
							<?php endif; ?>
						</span>
					</span>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $p->revenue, $max ) ); // phpcs:ignore ?>
				</td>
				<td class="num"><?php echo $views ? esc_html( cbaz_int( $views ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
				<td class="num"><?php echo $f ? esc_html( cbaz_int( $f->visitors ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
				<td class="num"><?php echo $carts ? esc_html( cbaz_int( $carts ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
				<td class="num"><?php echo $views ? esc_html( cbaz_pct( ( $carts / $views ) * 100 ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
				<td class="num"><?php echo esc_html( cbaz_int( $p->orders ) ); ?></td>
				<td class="num"><?php echo $views ? esc_html( cbaz_pct( ( $p->orders / $views ) * 100 ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
				<td class="num"><?php echo esc_html( cbaz_int( $p->qty ) ); ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $p->revenue ) ); ?></td>
				<td class="num">
					<?php
					$avant = $prev_products[ (int) $p->product_id ] ?? null;
					echo cbaz_delta_badge( cbaz_delta( (float) $p->revenue, $avant ? (float) $avant->revenue : 0 ) ); // phpcs:ignore
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $products ) : ?><tr><td colspan="10" class="cbaz-empty"><?php echo esc_html__( 'No sales during the period.', 'pluginect-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>

	<?php echo cbaz_table_count( count( $products ), count( $products ), __( 'products', 'pluginect-analytics-for-woocommerce' ) ); // phpcs:ignore ?>

	<p class="cbaz-note">
		<?php echo esc_html__( "A dash indicates a product for which no views have yet been measured — views are not recorded\n\t\tsince installing this version.", 'pluginect-analytics-for-woocommerce' ); ?>
	</p>
</section>
