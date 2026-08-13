<?php
/** Comportement : ce que les visiteurs consultent et comment ils naviguent. */

defined( 'ABSPATH' ) || exit;

$totals = cbaz_totals( $range['from'], $range['to'] );
$prev   = cbaz_totals( $range['prev_from'], $range['prev_to'] );

$views = [
	'pages'      => __( 'Pages vues', 'shop-analytics-for-woocommerce' ),
	'produits'   => __( 'Produits consultés', 'shop-analytics-for-woocommerce' ),
	'entrees'    => __( 'Pages d’entrée', 'shop-analytics-for-woocommerce' ),
	'sorties'    => __( 'Pages de sortie', 'shop-analytics-for-woocommerce' ),
	'recherches' => __( 'Recherches internes', 'shop-analytics-for-woocommerce' ),
	'parcours'   => __( 'Parcours visiteurs', 'shop-analytics-for-woocommerce' ),
	'appareils'  => __( 'Appareils', 'shop-analytics-for-woocommerce' ),
];
?>

<div class="cbaz-kpis cbaz-kpis--4">
	<?php
	$cards = [
		[ __( 'Pages vues', 'shop-analytics-for-woocommerce' ), cbaz_int( $totals['pageviews'] ), cbaz_delta( $totals['pageviews'], $prev['pageviews'] ), false ],
		[ __( 'Pages par visite', 'shop-analytics-for-woocommerce' ), number_format_i18n( $totals['sessions'] ? $totals['pageviews'] / $totals['sessions'] : 0, 1 ), cbaz_delta( $totals['pageviews'], $prev['pageviews'] ), false ],
		[ __( 'Durée moyenne', 'shop-analytics-for-woocommerce' ), cbaz_duration( $totals['duration'] ), cbaz_delta( $totals['duration'], $prev['duration'] ), false ],
		[ __( 'Taux de rebond', 'shop-analytics-for-woocommerce' ), cbaz_pct( $totals['bounce_rate'], 1 ), cbaz_delta( $totals['bounce_rate'], $prev['bounce_rate'] ), true ],
	];

	foreach ( $cards as list( $label, $value, $delta, $invert ) ) :
		?>
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( $label ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html( $label ); ?></p>
			<p class="cbaz-kpi__value"><?php echo esc_html( $value ); ?></p>
			<p class="cbaz-kpi__delta"><?php echo cbaz_delta_badge( $delta, $invert ); // phpcs:ignore ?><span class="cbaz-kpi__vs"><?php echo esc_html__( 'vs période préc.', 'shop-analytics-for-woocommerce' ); ?></span></p>
		</div>
	<?php endforeach; ?>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'path', 4 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Navigation', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Ce que l’on regarde, par où l’on entre, par où l’on part.', 'shop-analytics-for-woocommerce' ); ?></p></div>
		<?php $vue = cbaz_subtabs( 'vue', $views, 'pages' ); ?>
	</header>

	<?php if ( 'appareils' === $vue ) : ?>
		<?php
		$devices  = cbaz_group( 'device', $range, 5, "AND device <> ''" );
		$browsers = cbaz_group( 'browser', $range, 8, "AND browser <> ''" );
		$systems  = cbaz_group( 'os', $range, 8, "AND os <> ''" );
		$prev_os      = cbaz_group_previous( 'os', $range, "AND os <> ''" );
		$prev_browser = cbaz_group_previous( 'browser', $range, "AND browser <> ''" );
		$base     = max( 1, (int) $totals['sessions'] );
		?>
		<div class="cbaz-grid cbaz-grid--3">
			<div><h3 class="cbaz-h3"><?php echo esc_html__( 'Appareils', 'shop-analytics-for-woocommerce' ); ?></h3><?php echo cbaz_donut( $devices, $base ); // phpcs:ignore ?></div>
			<div><h3 class="cbaz-h3"><?php echo esc_html__( 'Systèmes', 'shop-analytics-for-woocommerce' ); ?></h3><?php cbaz_meter_list( $systems, $base, $prev_os ); ?></div>
			<div><h3 class="cbaz-h3"><?php echo esc_html__( 'Navigateurs', 'shop-analytics-for-woocommerce' ); ?></h3><?php cbaz_meter_list( $browsers, $base, $prev_browser ); ?></div>
		</div>

	<?php elseif ( 'parcours' === $vue ) : ?>
		<?php
		$depart = sanitize_text_field( wp_unslash( $_GET['depart'] ?? '' ) );
		$flow   = cbaz_journey_flow( $range, $depart, 3, 6 );
		$titres = [ __( 'Page d’arrivée', 'shop-analytics-for-woocommerce' ), __( 'Puis', 'shop-analytics-for-woocommerce' ), __( 'Puis', 'shop-analytics-for-woocommerce' ) ];

		/** Une page lisible plutôt qu'un chemin brut. */
		$joli = function ( $path ) {
			if ( '' === $path ) {
				return '';
			}

			return '/' === $path ? __( 'Accueil', 'shop-analytics-for-woocommerce' ) : trim( $path, '/' );
		};
		?>

		<p class="cbaz-flowintro">
			<?php if ( $flow['sampled'] ) : ?>
				<?php /* translators: 1: analyzed visits, 2: distinct journeys, 3: sampled page count. */ printf( esc_html__( '%1$s visites analysées · %2$s parcours différents · échantillon récent de %3$s pages', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $flow['visits'] ) ), esc_html( cbaz_int( $flow['distinct'] ) ), esc_html( cbaz_int( $flow['rows'] ) ) ); ?>
			<?php else : ?>
				<?php /* translators: 1: analyzed visits, 2: distinct journeys. */ printf( esc_html__( '%1$s visites analysées · %2$s parcours différents', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $flow['visits'] ) ), esc_html( cbaz_int( $flow['distinct'] ) ) ); ?>
			<?php endif; ?>
			<?php if ( $depart ) : ?>
				· <span class="cbaz-chip"><?php echo esc_html( $joli( $depart ) ); ?>
					<a href="<?php echo esc_url( cbaz_url( [], [ 'depart' ] ) ); ?>" aria-label="<?php echo esc_attr__( 'Retirer ce départ', 'shop-analytics-for-woocommerce' ); ?>"><?php echo esc_html__( '×', 'shop-analytics-for-woocommerce' ); ?></a>
				</span>
			<?php endif; ?>
		</p>

		<div class="cbaz-flow">
			<?php foreach ( $flow['steps'] as $i => $step ) : ?>
				<?php if ( $i ) : ?><span class="cbaz-flow__arrow" aria-hidden="true">→</span><?php endif; ?>

				<div class="cbaz-flow__col">
					<p class="cbaz-flow__title"><?php echo esc_html( $titres[ $i ] ); ?></p>

					<?php if ( ! $step['pages'] ) : ?>
						<p class="cbaz-empty"><?php echo esc_html__( 'Aucune visite ne va jusque-là.', 'shop-analytics-for-woocommerce' ); ?></p>
					<?php endif; ?>

					<?php foreach ( $step['pages'] as $k => $page ) : ?>
						<?php
						$autre  = '' === $page['path'];
						$active = ! $autre && 0 === $i && $depart === $page['path'];
						?>

						<?php if ( 0 === $i && ! $autre ) : ?>
							<a class="cbaz-flow__page<?php echo $active ? ' is-active' : ''; ?>"
							   href="<?php echo esc_url( cbaz_url( [ 'depart' => $page['path'] ] ) ); ?>"
							   title="<?php echo esc_attr__( 'Ne garder que les visites parties d’ici', 'shop-analytics-for-woocommerce' ); ?>">
						<?php else : ?>
							<div class="cbaz-flow__page<?php echo $autre ? ' is-other' : ''; ?>">
						<?php endif; ?>

							<span class="cbaz-flow__name">
								<?php echo $autre
									/* translators: %1$d: number of additional pages grouped together. */
									? esc_html( sprintf( _n( '%1$d autre page', '%1$d autres pages', (int) $page['other'], 'shop-analytics-for-woocommerce' ), (int) $page['other'] ) )
									: esc_html( $joli( $page['path'] ) ); ?>
							</span>

							<span class="cbaz-flow__bar cbaz-flow__bar--<?php echo (int) ( $k % 6 ); ?>">
								<span style="width:<?php echo esc_attr( max( 2, $page['share'] ) ); ?>%"></span>
							</span>

							<span class="cbaz-flow__meta">
								<?php echo esc_html( cbaz_int( $page['count'] ) ); ?>
								<?php if ( $page['stopped'] ) : ?>
									<em><?php /* translators: %1$s: percentage of visitors who stop. */ printf( esc_html__( '%1$s s’arrêtent', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_pct( ( $page['stopped'] / $page['count'] ) * 100, 0 ) ) ); ?></em>
								<?php endif; ?>
							</span>

						<?php echo ( 0 === $i && ! $autre ) ? '</a>' : '</div>'; ?>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="cbaz-note">
			<?php echo wp_kses_post( __( 'Chaque colonne regroupe les pages par <strong>position dans la visite</strong> : où l’on arrive, puis où l’on va.
			Lister les parcours entiers serait illisible — cent visites donnent facilement quatre-vingts chemins
			différents, tous à une ou deux occurrences.', 'shop-analytics-for-woocommerce' ) ); ?>
			<br><br>
			<?php echo esc_html__( 'Clique une page d’arrivée pour ne garder que les visites parties de là. « S’arrêtent » désigne celles qui
            n’ont rien vu au-delà : c’est le point de renoncement.', 'shop-analytics-for-woocommerce' ); ?>
		</p>

	<?php elseif ( 'recherches' === $vue ) : ?>
		<?php $rows = cbaz_searches( $range, 30 ); ?>
		<table class="cbaz-table">
			<thead><tr><th><?php echo esc_html__( 'Terme recherché', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Recherches', 'shop-analytics-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
			<?php $maxs = $rows ? max( array_map( fn( $r ) => (int) $r->sessions, $rows ) ) : 1; ?>
			<?php foreach ( $rows as $i => $r ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $r->label ); ?></strong>
						<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $r->sessions, $maxs ) ); // phpcs:ignore ?>
					</td>
					<td class="num"><?php echo esc_html( cbaz_int( $r->sessions ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?><tr><td colspan="2" class="cbaz-empty"><?php echo esc_html__( 'Aucune recherche interne sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<p class="cbaz-note"><?php echo esc_html__( 'Ce que l’on cherche sans trouver vaut souvent plus qu’un classement de pages vues : c’est une demande à laquelle le catalogue ne répond pas encore.', 'shop-analytics-for-woocommerce' ); ?></p>

	<?php elseif ( 'produits' === $vue ) : ?>
		<?php
		$funnel = cbaz_product_funnel( $range );
		uasort( $funnel, fn( $a, $b ) => $b->views <=> $a->views );
		$maxv = $funnel ? max( array_map( fn( $f ) => (int) $f->views, $funnel ) ) : 1;
		?>
		<table class="cbaz-table">
			<thead><tr><th><?php echo esc_html__( 'Produit', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Vues', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Visiteurs', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Ajouts panier', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Taux d’ajout', 'shop-analytics-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
			<?php $i = 0; ?>
			<?php foreach ( array_slice( $funnel, 0, 30, true ) as $pid => $f ) : ?>
				<?php $product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null; ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $product ? $product->get_name() : '#' . $pid ); ?></strong>
						<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $f->views, $maxv ) ); // phpcs:ignore ?>
					</td>
					<td class="num"><?php echo esc_html( cbaz_int( $f->views ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $f->visitors ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $f->carts ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_pct( $f->views ? ( $f->carts / $f->views ) * 100 : 0 ) ); ?></td>
				</tr>
				<?php $i++; ?>
			<?php endforeach; ?>
			<?php if ( ! $funnel ) : ?><tr><td colspan="5" class="cbaz-empty"><?php echo esc_html__( 'Aucune consultation produit mesurée sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

	<?php else : ?>
		<?php
		$times   = cbaz_page_times( $range );
		$bounces = cbaz_page_bounces( $range );

		if ( 'pages' === $vue ) {
			$rows  = cbaz_pages( $range, 25 );
			$value = fn( $r ) => (int) $r->views;
			$head  = [ __( 'Page', 'shop-analytics-for-woocommerce' ), __( 'Vues', 'shop-analytics-for-woocommerce' ), __( 'Visites', 'shop-analytics-for-woocommerce' ) ];
		} else {
			$column = 'entrees' === $vue ? 'entry_path' : 'exit_path';
			$rows   = cbaz_group( $column, $range, 25, "AND {$column} <> ''" );
			$value  = fn( $r ) => (int) $r->sessions;
			$head   = [ __( 'Page', 'shop-analytics-for-woocommerce' ), __( 'Visites', 'shop-analytics-for-woocommerce' ), __( 'Commandes', 'shop-analytics-for-woocommerce' ) ];
		}

		$max = $rows ? max( array_map( $value, $rows ) ) : 1;

		/*
		 * Les mêmes pages, la période d'avant. Une page qui perd la
		 * moitié de son trafic ne se voit pas dans un classement seul.
		 *
		 * Attention à ce qu'on compare : l'onglet « Pages » compte des
		 * PAGES VUES, lues dans la table des vues, quand les onglets
		 * d'entrée et de sortie comptent des VISITES. Rapprocher les
		 * deux donnerait des variations sans aucun sens.
		 */
		$prev_pages = [];

		if ( 'pages' === $vue ) {
			foreach ( cbaz_pages( cbaz_prev_range( $range ), 500 ) as $old ) {
				$prev_pages[ (string) $old->label ] = (object) [ 'sessions' => (int) $old->views ];
			}
		} else {
			$prev_pages = cbaz_group_previous( $column, $range, "AND {$column} <> ''" );
		}
		?>

		<table class="cbaz-table">
			<thead>
				<tr>
					<th><?php echo esc_html( $head[0] ); ?></th>
					<th class="num"><?php echo esc_html( $head[1] ); ?></th>
					<th class="num"><?php echo esc_html__( 'Temps moyen', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Rebond', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Évolution', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Part', 'shop-analytics-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $i => $r ) : ?>
				<?php
				$v = $value( $r );
				$t = $times[ $r->label ] ?? null;
				$b = $bounces[ $r->label ] ?? null;
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( isset( $r->title ) && $r->title ? $r->title : $r->label ); ?></strong>
						<span class="cbaz-path"><?php echo esc_html( $r->label ); ?></span>
						<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $v, $max ) ); // phpcs:ignore ?>
					</td>
					<td class="num"><?php echo esc_html( cbaz_int( $v ) ); ?></td>
					<td class="num"><?php echo $t ? esc_html( cbaz_duration( $t->seconds ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
					<td class="num"><?php echo $b && $b->entries ? esc_html( cbaz_pct( ( $b->bounces / $b->entries ) * 100, 1 ) ) : '<span class="cbaz-faint">—</span>'; ?></td>
					<td class="num"><?php echo cbaz_delta_badge( cbaz_row_delta( $prev_pages, $r->label, $v ) ); // phpcs:ignore ?></td>
					<td class="num"><?php echo esc_html( cbaz_pct( ( $v / max( 1, array_sum( array_map( $value, $rows ) ) ) ) * 100, 1 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?><tr><td colspan="6" class="cbaz-empty"><?php echo esc_html__( 'Aucune page mesurée sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

		<p class="cbaz-note">
			<?php echo esc_html__( 'Le temps moyen ne compte que lorsque l’onglet est visible : une page laissée ouverte en arrière-plan
			toute la nuit ne vaut pas huit heures de lecture. Le rebond se lit par page d’entrée — une visite
			arrivée là et repartie sans rien voir d’autre.', 'shop-analytics-for-woocommerce' ); ?>
		</p>
	<?php endif; ?>
</section>
