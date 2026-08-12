<?php
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
	[ 'Visiteurs uniques', cbaz_int( $totals['visitors'] ), cbaz_delta( $totals['visitors'], $prev['visitors'] ), false ],
	[ 'Visites par visiteur', number_format_i18n( $totals['visitors'] ? $totals['sessions'] / $totals['visitors'] : 0, 2 ), cbaz_delta( $totals['sessions'], $prev['sessions'] ), false ],
	[ 'Durée moyenne', cbaz_duration( $totals['duration'] ), cbaz_delta( $totals['duration'], $prev['duration'] ), false ],
	[ 'Pages par visite', number_format_i18n( $totals['sessions'] ? $totals['pageviews'] / $totals['sessions'] : 0, 1 ), cbaz_delta( $totals['pageviews'], $prev['pageviews'] ), false ],
	[ 'Taux de rebond', cbaz_pct( $totals['bounce_rate'], 1 ), cbaz_delta( $totals['bounce_rate'], $prev['bounce_rate'] ), true ],
];
?>

<div class="cbaz-kpis">
	<?php foreach ( $kpis as list( $label, $value, $delta, $invert ) ) : ?>
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( $label ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html( $label ); ?></p>
			<p class="cbaz-kpi__value"><?php echo esc_html( $value ); ?></p>
			<p class="cbaz-kpi__delta">
				<?php echo cbaz_delta_badge( $delta, $invert ); // phpcs:ignore ?>
				<span class="cbaz-kpi__vs">vs période préc.</span>
			</p>
		</div>
	<?php endforeach; ?>
</div>

<div class="cbaz-grid cbaz-grid--3">
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'device', 1 ); // phpcs:ignore ?>
			<div><h2>Appareils</h2><p>Répartition des visites.</p></div></header>
		<?php echo cbaz_donut( $devices, $sessions_total ); // phpcs:ignore ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'os', 2 ); // phpcs:ignore ?>
			<div><h2>Systèmes d’exploitation</h2></div></header>
		<?php cbaz_meter_list( $systems, $sessions_total, $prev_os ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'browser', 3 ); // phpcs:ignore ?>
			<div><h2>Navigateurs</h2></div></header>
		<?php cbaz_meter_list( $browsers, $sessions_total, $prev_browser ); ?>
	</section>
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'users', 4 ); // phpcs:ignore ?>
			<div><h2>Nouveaux ou récurrents</h2></div></header>
		<ul class="cbaz-list">
			<?php foreach ( $loyalty as $l ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo $l->label ? 'Nouveaux visiteurs' : 'Visiteurs récurrents'; ?></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $l->sessions ) ); ?> · <?php echo esc_html( cbaz_pct( ( $l->sessions / $sessions_total ) * 100, 1 ) ); ?></span>
					</div>
					<?php echo cbaz_bar( $l->sessions, $sessions_total ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $loyalty ) : ?><li class="cbaz-empty">Aucune visite sur la période.</li><?php endif; ?>
		</ul>
		<p class="cbaz-note">
			« Récurrent » signifie ici : revenu au cours de la même journée. L’empreinte changeant chaque nuit,
			un visiteur qui revient demain sera compté comme nouveau — c’est le prix de l’absence d’identifiant.
		</p>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2>Conversion par pays</h2></div></header>
		<table class="cbaz-table">
			<thead><tr><th>Pays</th><th class="num">Visites</th><th class="num">Conv.</th><th class="num">CA</th></tr></thead>
			<tbody>
			<?php foreach ( $countries as $c ) : ?>
				<tr>
					<td><?php echo cbaz_country_flag( $c->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $c->label ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $c->sessions ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_pct( $c->sessions ? ( $c->orders / $c->sessions ) * 100 : 0 ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_money( $c->revenue ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $countries ) : ?><tr><td colspan="4" class="cbaz-empty">Pays non renseignés — voir Géographie.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'screen', 5 ); // phpcs:ignore ?>
			<div><h2>Résolutions d’écran</h2><p>Sur quoi tester la boutique en priorité.</p></div></header>
		<?php cbaz_meter_list( $screens, $sessions_total, $prev_screen ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'language', 2 ); // phpcs:ignore ?>
			<div><h2>Langues</h2></div></header>
		<?php cbaz_meter_list( $langs, $sessions_total, $prev_lang ); ?>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'loyalty', 3 ); // phpcs:ignore ?>
			<div><h2>Fidélité</h2></div></header>
		<dl class="cbaz-stats">
			<div><dt>Visites</dt><dd><?php echo esc_html( cbaz_int( $totals['sessions'] ) ); ?></dd></div>
			<div><dt>Visiteurs uniques</dt><dd><?php echo esc_html( cbaz_int( $totals['visitors'] ) ); ?></dd></div>
			<div><dt>Pages vues</dt><dd><?php echo esc_html( cbaz_int( $totals['pageviews'] ) ); ?></dd></div>
			<div><dt>Commandes</dt><dd><?php echo esc_html( cbaz_int( $totals['orders'] ) ); ?></dd></div>
		</dl>
	</section>
</div>
