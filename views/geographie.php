<?php
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
		<strong><?php echo esc_html__( 'Le pays n’est pas encore renseigné.', 'shop-analytics-for-woocommerce' ); ?></strong>
		<?php echo esc_html__( 'Il est lu dans un en-tête fourni par l’hébergeur ou par Cloudflare, et à défaut dans l’adresse de facturation d’une commande.
		Aucune base GeoIP n’est embarquée : elle pèserait plusieurs mégaoctets et demanderait une mise à jour mensuelle.
		Les commandes renseigneront donc les pays au fil de l’eau.', 'shop-analytics-for-woocommerce' ); ?>
	</div>
<?php endif; ?>

<div class="cbaz-grid cbaz-grid--globe">
	<section class="cbaz-card cbaz-card--globe">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Localisation des visiteurs', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Survolez un pays pour afficher son détail.', 'shop-analytics-for-woocommerce' ); ?></p></div>
			<button type="button" class="cbaz-ctrl cbaz-ctrl--mini" data-cbaz-spin><?php echo esc_html__( 'Pause rotation', 'shop-analytics-for-woocommerce' ); ?></button>
		</header>

		<div class="cbaz-globe" data-cbaz-globe
		     data-metric="sessions"
		     data-home="<?php echo esc_attr( wp_json_encode( $home_point ) ); ?>"
		     data-points="<?php echo esc_attr( wp_json_encode( $points ) ); ?>">
			<svg viewBox="-152 -152 304 304" role="img" aria-label="<?php echo esc_attr__( 'Globe des visites par pays', 'shop-analytics-for-woocommerce' ); ?>"></svg>

			<div class="cbaz-globe__tools">
				<button type="button" data-cbaz-zoom="+" aria-label="<?php echo esc_attr__( 'Zoomer', 'shop-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Zoomer', 'shop-analytics-for-woocommerce' ); ?>">+</button>
				<button type="button" data-cbaz-zoom="-" aria-label="<?php echo esc_attr__( 'Dézoomer', 'shop-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Dézoomer', 'shop-analytics-for-woocommerce' ); ?>">−</button>
				<button type="button" data-cbaz-reset aria-label="<?php echo esc_attr__( 'Revenir à la vue initiale', 'shop-analytics-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'Recentrer', 'shop-analytics-for-woocommerce' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="6"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>
				</button>
			</div>
		</div>

		<div class="cbaz-floats">
			<div class="cbaz-float">
				<p class="cbaz-float__label"><?php echo esc_html__( 'Visiteurs présents actuellement', 'shop-analytics-for-woocommerce' ); ?></p>
				<p class="cbaz-float__value"><?php echo esc_html( cbaz_int( cbaz_realtime()['online'] ) ); ?></p>
			</div>

			<div class="cbaz-float">
				<p class="cbaz-float__label"><?php echo esc_html__( 'Ventes totales sur la période', 'shop-analytics-for-woocommerce' ); ?></p>
				<p class="cbaz-float__value"><?php echo esc_html( cbaz_money( array_sum( array_map( fn( $r ) => (float) $r->revenue, $rows ) ) ) ); ?></p>
			</div>

			<div class="cbaz-float cbaz-float--wide">
				<p class="cbaz-float__title"><?php echo esc_html__( 'Principaux emplacements', 'shop-analytics-for-woocommerce' ); ?></p>
				<?php foreach ( array_slice( $rows, 0, 3 ) as $r ) : ?>
					<div class="cbaz-float__row">
						<span><?php echo cbaz_country_flag( $r->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $r->label ) ); ?></span>
						<?php echo cbaz_bar( $r->sessions, $max ); // phpcs:ignore ?>
						<em><?php echo esc_html( cbaz_int( $r->sessions ) ); ?></em>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><p class="cbaz-empty"><?php echo esc_html__( 'Aucun pays identifié.', 'shop-analytics-for-woocommerce' ); ?></p><?php endif; ?>
			</div>
		</div>
		<p class="cbaz-note">
			<?php echo esc_html__( 'Fais glisser pour faire pivoter, survole un pays pour son détail.', 'shop-analytics-for-woocommerce' ); ?>
			<?php if ( $rows ) : ?>
				<?php /* translators: 1: maximum visit count, 2: country name. */ printf( esc_html__( 'La taille d’un point suit son nombre de visites — %1$s au maximum, pour %2$s.', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $max ) ), esc_html( cbaz_country_name( $rows[0]->label ) ) ); ?>
			<?php endif; ?>
		</p>
	</section>

	<div class="cbaz-stack">
		<section class="cbaz-card cbaz-card--readout">
			<header class="cbaz-card__head"><?php echo cbaz_icon( 'page', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Détail', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>

			<div class="cbaz-readout" data-cbaz-readout>
				<p class="cbaz-readout__hint"><?php echo esc_html__( 'Survole un pays sur le globe.', 'shop-analytics-for-woocommerce' ); ?></p>
			</div>
		</section>

		<section class="cbaz-card">
			<header class="cbaz-card__head"><?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top pays', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Classement par visiteurs sur la période.', 'shop-analytics-for-woocommerce' ); ?></p></div></header>
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
						<p class="cbaz-list__note"><?php /* translators: 1: share of visits, 2: revenue. */ printf( esc_html__( '%1$s des visites · %2$s', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_pct( $total ? ( $r->sessions / $total ) * 100 : 0, 1 ) ), esc_html( cbaz_money( $r->revenue ) ) ); ?></p>
					</li>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucun pays connu sur la période.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
			</ul>
		</section>
	</div>
</div>
