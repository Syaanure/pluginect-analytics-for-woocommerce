<?php
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
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Références vendues' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Références vendues', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( count( $products ) ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Articles vendus' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Articles vendus', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $qty ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA produits' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'CA produits', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $rev ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Prix moyen' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Prix moyen', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $qty ? $rev / $qty : 0 ) ); ?></p></div>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'box', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Produits les plus performants', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Commandes terminées, en cours et en attente. Les montants viennent de WooCommerce.', 'shop-analytics-for-woocommerce' ); ?></p></div>
		<?php
		$tri = cbaz_subtabs( 'tri', [
			'vues'     => __( 'Plus vus', 'shop-analytics-for-woocommerce' ),
			'paniers'  => __( 'Plus ajoutés au panier', 'shop-analytics-for-woocommerce' ),
			'quantite' => __( 'Plus vendus', 'shop-analytics-for-woocommerce' ),
			'ca'       => __( 'Plus gros CA', 'shop-analytics-for-woocommerce' ),
			'conv'     => __( 'Meilleure conversion', 'shop-analytics-for-woocommerce' ),
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
		<?php if ( ! $products ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucune vente sur la période.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
	</ul>
</section>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'box', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Tous les produits', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Quantités, commandes et part du chiffre d’affaires.', 'shop-analytics-for-woocommerce' ); ?></p></div>
	</header>

	<?php echo cbaz_table_tools( __( 'Rechercher un produit…', 'shop-analytics-for-woocommerce' ), 'produits' ); // phpcs:ignore ?>

	<table class="cbaz-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Produit', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Vues', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Visiteurs', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Ajouts panier', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Taux d’ajout', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Conversion', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Quantités', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html_x( 'CA', 'revenue abbreviation', 'shop-analytics-for-woocommerce' ); ?></th>
				<th class="num"><?php echo esc_html__( 'Évolution', 'shop-analytics-for-woocommerce' ); ?></th>
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
		<?php if ( ! $products ) : ?><tr><td colspan="10" class="cbaz-empty"><?php echo esc_html__( 'Aucune vente sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>

	<?php echo cbaz_table_count( count( $products ), count( $products ), __( 'produits', 'shop-analytics-for-woocommerce' ) ); // phpcs:ignore ?>

	<p class="cbaz-note">
		<?php echo esc_html__( 'Un tiret signale un produit dont aucune consultation n’a encore été mesurée — les vues ne sont enregistrées
		que depuis l’installation de cette version.', 'shop-analytics-for-woocommerce' ); ?>
	</p>
</section>
