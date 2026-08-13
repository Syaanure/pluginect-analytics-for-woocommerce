<?php
/** Vue d'ensemble. */

defined( 'ABSPATH' ) || exit;

$kpis    = cbaz_kpis( $range );
$series  = cbaz_series( $range );
$funnel  = cbaz_funnel( $range );
$funnel_prev  = cbaz_funnel( cbaz_prev_range( $range ) );
$sources = cbaz_sources( $range, 5 );
$prods   = cbaz_top_products( $range, 5 );
$geo     = cbaz_countries( $range, 5 );
$now     = $kpis['now'];
$then    = $kpis['then'];
?>

<?php
/*
 * Bandeau d'état.
 *
 * Il annonce la configuration réelle, pas une intention : le « sans
 * cookie » d'origine est resté affiché des semaines après l'ajout de
 * la mémoire d'attribution, qui en dépose un. Un indicateur qui ment
 * est pire que pas d'indicateur.
 */
$jours = (int) cbaz_opt( 'attribution_days' );
$mois  = (int) cbaz_opt( 'retention_months' );
?>

<div class="cbaz-status">
	<span class="cbaz-dot<?php echo cbaz_opt( 'enabled' ) ? ' is-on' : ''; ?>"></span>

	<?php if ( ! cbaz_opt( 'enabled' ) ) : ?>
		<?php echo esc_html__( 'Mesure désactivée — aucune visite n’est enregistrée.', 'shop-analytics-for-woocommerce' ); ?>
	<?php else : ?>
		<?php if ( $jours ) : ?>
			<?php /* translators: 1: attribution duration in days, 2: detailed-data retention in months. */ printf( esc_html__( 'Mesure active · un cookie de provenance, %1$d jours · conservation %2$d mois · données hébergées sur ton serveur', 'shop-analytics-for-woocommerce' ), $jours, $mois ); ?>
		<?php else : ?>
			<?php /* translators: %1$d: detailed-data retention in months. */ printf( esc_html__( 'Mesure active · sans aucun cookie · conservation %1$d mois · données hébergées sur ton serveur', 'shop-analytics-for-woocommerce' ), $mois ); ?>
		<?php endif; ?>
	<?php endif; ?>

	<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'parametres' ) ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Paramètres', 'shop-analytics-for-woocommerce' ); ?></a>
</div>

<?php
$spark = [
	'visitors'  => wp_list_pluck( $series, 'sessions' ),
	'sessions'  => wp_list_pluck( $series, 'sessions' ),
	'pageviews' => wp_list_pluck( $series, 'pageviews' ),
	'revenue'   => wp_list_pluck( $series, 'revenue' ),
	'cr'        => wp_list_pluck( $series, 'orders' ),
	'aov'       => wp_list_pluck( $series, 'revenue' ),
];
?>

<div class="cbaz-kpis">
	<?php foreach ( $kpis['cards'] as $k ) : ?>
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( $k['key'] ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html( $k['label'] ); ?></p>
			<p class="cbaz-kpi__value"><?php echo esc_html( cbaz_format( $k['value'], $k['format'] ) ); ?></p>
			<p class="cbaz-kpi__delta">
				<?php echo cbaz_delta_badge( $k['delta'] ); // phpcs:ignore ?>
				<span class="cbaz-kpi__vs"><?php echo esc_html__( 'vs période préc.', 'shop-analytics-for-woocommerce' ); ?></span>
			</p>
			<?php echo cbaz_sparkline( $spark[ $k['key'] ] ?? [] ); // phpcs:ignore ?>
		</div>
	<?php endforeach; ?>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div>
			<h2><?php echo esc_html__( 'Performances de la boutique', 'shop-analytics-for-woocommerce' ); ?></h2>
			<p><?php /* translators: 1: start date, 2: end date, 3: number of previous days. */ printf( esc_html__( '%1$s – %2$s · comparé aux %3$d jours précédents', 'shop-analytics-for-woocommerce' ), esc_html( wp_date( 'j M', strtotime( $range['from'] ) ) ), esc_html( wp_date( 'j M Y', strtotime( $range['to'] ) ) ), (int) $range['days'] ); ?></p>
		</div>
		<span class="cbaz-pill"><?php /* translators: %1$s: site timezone abbreviation. */ printf( esc_html__( 'Données horodatées %1$s', 'shop-analytics-for-woocommerce' ), esc_html( wp_date( 'T' ) ) ); ?></span>
	</header>
	<?php echo cbaz_chart( $series ); // phpcs:ignore ?>
</section>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Entonnoir de conversion', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'De la visite à la commande, sur la période sélectionnée.', 'shop-analytics-for-woocommerce' ); ?></p></div></header>

		<?php echo cbaz_funnel_block( $funnel, $funnel_prev ); // phpcs:ignore ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Synthèse conversion', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<dl class="cbaz-stats">
			<?php
			$stats = [
				[ __( 'Taux de conversion', 'shop-analytics-for-woocommerce' ), cbaz_pct( $now['cr'] ), cbaz_delta( $now['cr'], $then['cr'] ), false ],
				[ __( 'Taux de rebond', 'shop-analytics-for-woocommerce' ), cbaz_pct( $now['bounce_rate'], 1 ), cbaz_delta( $now['bounce_rate'], $then['bounce_rate'] ), true ],
				[ __( 'Valeur par visite', 'shop-analytics-for-woocommerce' ), cbaz_money( $now['per_session'] ), cbaz_delta( $now['per_session'], $then['per_session'] ), false ],
				[ __( 'Durée moyenne', 'shop-analytics-for-woocommerce' ), cbaz_duration( $now['duration'] ), cbaz_delta( $now['duration'], $then['duration'] ), false ],
				[ __( 'Commandes', 'shop-analytics-for-woocommerce' ), cbaz_int( $now['orders'] ), cbaz_delta( $now['orders'], $then['orders'] ), false ],
			];

			foreach ( $stats as list( $label, $value, $delta, $invert ) ) :
				?>
				<div>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $value ); ?> <?php echo cbaz_delta_badge( $delta, $invert ); // phpcs:ignore ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>
</div>

<div class="cbaz-grid cbaz-grid--3">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top sources', 'shop-analytics-for-woocommerce' ); ?></h2></div>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'acquisition' ), 'periode' => $range['preset'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Tout voir', 'shop-analytics-for-woocommerce' ); ?></a>
		</header>
		<?php $max = $sources ? max( array_map( fn( $s ) => (int) $s->sessions, $sources ) ) : 1; ?>
		<ul class="cbaz-list">
			<?php foreach ( $sources as $s ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo esc_html( $s->source ); ?> <em>/ <?php echo esc_html( $s->medium ); ?></em></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $s->sessions ) ); ?></span>
					</div>
					<?php echo cbaz_bar( $s->sessions, $max ); // phpcs:ignore ?>
					<p class="cbaz-list__note"><?php /* translators: 1: revenue, 2: order count. */ printf( esc_html__( '%1$s · %2$s commandes', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_money( $s->revenue ) ), esc_html( cbaz_int( $s->orders ) ) ); ?></p>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $sources ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucune visite sur la période.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'box', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top produits', 'shop-analytics-for-woocommerce' ); ?></h2></div>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'produits' ), 'periode' => $range['preset'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Tout voir', 'shop-analytics-for-woocommerce' ); ?></a>
		</header>
		<?php $maxp = $prods ? max( array_map( fn( $p ) => (float) $p->revenue, $prods ) ) : 1; ?>
		<ul class="cbaz-list">
			<?php foreach ( $prods as $p ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo esc_html( $p->label ); ?></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_money( $p->revenue ) ); ?></span>
					</div>
					<?php echo cbaz_bar( $p->revenue, $maxp ); // phpcs:ignore ?>
					<p class="cbaz-list__note"><?php /* translators: 1: units sold, 2: order count. */ printf( esc_html__( '%1$s vendus · %2$s commandes', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $p->qty ) ), esc_html( cbaz_int( $p->orders ) ) ); ?></p>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $prods ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucune vente sur la période.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Top pays', 'shop-analytics-for-woocommerce' ); ?></h2></div>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'geographie' ), 'periode' => $range['preset'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Tout voir', 'shop-analytics-for-woocommerce' ); ?></a>
		</header>
		<?php $maxg = $geo ? max( array_map( fn( $g ) => (int) $g->sessions, $geo ) ) : 1; ?>
		<ul class="cbaz-list">
			<?php foreach ( $geo as $g ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo cbaz_country_flag( $g->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $g->label ) ); ?></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $g->sessions ) ); ?></span>
					</div>
					<?php echo cbaz_bar( $g->sessions, $maxg ); // phpcs:ignore ?>
					<p class="cbaz-list__note"><?php echo esc_html( cbaz_money( $g->revenue ) ); ?></p>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $geo ) : ?>
				<li class="cbaz-empty"><?php echo esc_html__( 'Le pays n’est pas encore renseigné — voir Géographie pour l’explication.', 'shop-analytics-for-woocommerce' ); ?></li>
			<?php endif; ?>
		</ul>
	</section>
</div>
