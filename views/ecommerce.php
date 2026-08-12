<?php
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
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA WooCommerce' ); // phpcs:ignore ?><p class="cbaz-kpi__label">CA WooCommerce</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $shop['revenue'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Commandes</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $shop['orders'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Panier moyen' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Panier moyen</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $shop['orders'] ? $shop['revenue'] / $shop['orders'] : 0 ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Taux de conversion' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Taux de conversion</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_pct( $totals['cr'] ) ); ?></p><?php echo cbaz_delta_badge( cbaz_delta( $totals['cr'], $prev['cr'] ) ); // phpcs:ignore ?></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Valeur par visite' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Valeur par visite</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $totals['per_session'] ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA rattaché' ); // phpcs:ignore ?><p class="cbaz-kpi__label">CA rattaché</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_pct( $coverage, 0 ) ); ?></p><p class="cbaz-kpi__note">du CA relié à une visite</p></div>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head"><?php echo cbaz_icon( 'chart', 1 ); // phpcs:ignore ?>
			<div><h2>Chiffre d’affaires et visites</h2></div></header>
	<?php echo cbaz_chart( $series ); // phpcs:ignore ?>
</section>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div>
				<h2>Tunnel de conversion</h2>
				<p>Visites → Ajout au panier → Commande entamée → Achat</p>
			</div>
		</header>

		<?php echo cbaz_funnel_block( $funnel, $funnel_prev ); // phpcs:ignore ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2>Points de fuite</h2><p>Pertes entre chaque étape.</p></div></header>
		<?php echo cbaz_leaks_block( $funnel, $funnel_prev ); // phpcs:ignore ?>
		<p class="cbaz-note">L’écart entre le CA WooCommerce et le CA rattaché correspond aux commandes qu’aucune visite mesurée n’explique.</p>
	</section>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2>Rentabilité par source</h2><p>Ce que chaque canal rapporte réellement, visite par visite.</p></div>
	</header>

	<table class="cbaz-table">
		<thead><tr><th>Source</th><th class="num">Visites</th><th class="num">Commandes</th><th class="num">Conversion</th><th class="num">CA</th><th class="num">Par visite</th></tr></thead>
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
		<?php if ( ! $srcs ) : ?><tr><td colspan="6" class="cbaz-empty">Aucune visite sur la période.</td></tr><?php endif; ?>
		</tbody>
	</table>
</section>
