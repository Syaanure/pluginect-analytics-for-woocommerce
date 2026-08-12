<?php
/** Fiche d'une campagne. */

defined( 'ABSPATH' ) || exit;

$campaign = cbaz_campaign( (int) $_GET['id'] );

if ( ! $campaign ) {
	echo '<div class="cbaz-notice cbaz-notice--ko">Cette campagne n’existe plus.</div>';

	return;
}

if ( ! cbaz_can( 'campaign_stats' ) ) {
	/*
	 * Sans le module Pro, la fiche reste consultable et modifiable — c'est
	 * la création de campagnes qui est gratuite — mais aucune mesure de
	 * performance n'est calculée ni affichée.
	 */
	echo '<p class="cbaz-back"><a href="' . esc_url( cbaz_url( [], [ 'id' ] ) ) . '">← Toutes les campagnes</a></p>';

	cbaz_pro_notice(
		'Performances de cette campagne',
		'Pro mesure les visites, les commandes, le chiffre d’affaires, le taux de conversion et le retour sur investissement de cette campagne.'
	);

	return;
}

$row     = cbaz_campaign_row( $campaign->campaign, $range );
$series  = cbaz_campaign_series( $campaign->campaign, $range );
$orders  = cbaz_campaign_orders( $campaign->campaign, $range, 40 );
$goal    = (float) $campaign->goal_revenue;
$revenue = $row ? $row->revenue : 0;
$cost    = (float) $campaign->cost;
?>

<p class="cbaz-back">
	<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'campagnes' ), 'periode' => $range['preset'] ], admin_url( 'admin.php' ) ) ); ?>">← Toutes les campagnes</a>
</p>

<section class="cbaz-card cbaz-hero">
	<div class="cbaz-hero__main">
		<span class="cbaz-pill"><?php echo esc_html( cbaz_statuses()[ $campaign->status ] ?? $campaign->status ); ?></span>
		<h2><?php echo esc_html( $campaign->label ? $campaign->label : $campaign->campaign ); ?></h2>
		<p class="cbaz-path">
			<?php echo esc_html( $campaign->source ); ?> / <?php echo esc_html( $campaign->medium ); ?> · <?php echo esc_html( $campaign->campaign ); ?>
			<?php if ( $campaign->starts_on || $campaign->ends_on ) : ?>
				· <?php echo esc_html( trim( ( $campaign->starts_on ? wp_date( 'j M Y', strtotime( $campaign->starts_on ) ) : '' ) . ' → ' . ( $campaign->ends_on ? wp_date( 'j M Y', strtotime( $campaign->ends_on ) ) : '' ), ' →' ) ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $campaign->notes ) : ?>
			<p class="cbaz-note"><?php echo esc_html( $campaign->notes ); ?></p>
		<?php endif; ?>
	</div>

	<div class="cbaz-hero__links">
		<p class="cbaz-linklabel">Lien à diffuser</p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
		</div>

		<p class="cbaz-linklabel">Sans point d’interrogation, pour un QR code</p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_short_url( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
		</div>

		<p class="cbaz-linklabel">Format UTM complet, pour les régies</p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url_long( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
		</div>
		<a class="cbaz-btn cbaz-btn--ghost cbaz-btn--mini" href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'campagnes' ), 'edit' => $campaign->id ], admin_url( 'admin.php' ) ) ); ?>">Modifier</a>
	</div>
</section>

<div class="cbaz-kpis">
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA net' ); // phpcs:ignore ?><p class="cbaz-kpi__label">CA net</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $revenue ) ); ?></p><?php if ( $row && $row->refunded > 0 ) : ?><p class="cbaz-kpi__note"><?php echo esc_html( cbaz_money( $row->refunded ) ); ?> remboursés</p><?php endif; ?></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Budget' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Budget</p><p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( cbaz_money( $cost ) ) : '—'; ?></p></div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Retour' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Retour</p>
		<p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( number_format_i18n( $revenue / $cost, 2 ) . ' ×' ) : '—'; ?></p>
		<p class="cbaz-kpi__note">bénéfice <?php echo $cost > 0 ? esc_html( cbaz_money( $revenue - $cost ) ) : '—'; ?></p>
	</div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Visites' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Visites</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $row ? $row->sessions : 0 ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Commandes</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $row ? $row->orders : 0 ) ); ?></p><p class="cbaz-kpi__note">conversion <?php echo esc_html( cbaz_pct( $row ? $row->cr : 0 ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Coût par commande' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Coût par commande</p><p class="cbaz-kpi__value"><?php echo ( $row && null !== $row->cpa ) ? esc_html( cbaz_money( $row->cpa ) ) : '—'; ?></p></div>
</div>

<?php if ( $goal > 0 ) : ?>
	<section class="cbaz-card">
		<header class="cbaz-card__head"><div><h2>Objectif</h2><p><?php echo esc_html( cbaz_money( $revenue ) ); ?> sur <?php echo esc_html( cbaz_money( $goal ) ); ?></p></div></header>
		<?php $pct = min( 100, ( $revenue / $goal ) * 100 ); ?>
		<div class="cbaz-funnel__track">
			<div class="cbaz-funnel__fill" style="width:<?php echo esc_attr( max( 1, $pct ) ); ?>%"></div>
			<span class="cbaz-funnel__pct"><?php echo esc_html( number_format_i18n( $pct, 1 ) ); ?> %</span>
		</div>
	</section>
<?php endif; ?>

<section class="cbaz-card">
	<header class="cbaz-card__head"><?php echo cbaz_icon( 'calendar', 1 ); // phpcs:ignore ?>
			<div><h2>Jour par jour</h2></div></header>
	<?php echo cbaz_chart( $series ); // phpcs:ignore ?>
</section>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div><h2>Commandes attribuées</h2><p>Chaque ligne porte la trace de cette campagne dans WooCommerce.</p></div>
	</header>

	<table class="cbaz-table">
		<thead><tr><th>Commande</th><th>Date</th><th>Statut</th><th>Appareil</th><th>Pays</th><th class="num">Total</th></tr></thead>
		<tbody>
		<?php foreach ( $orders as $o ) : ?>
			<tr>
				<td><a class="cbaz-strong" href="<?php echo esc_url( cbaz_order_edit_url( (int) $o->id ) ); ?>">#<?php echo (int) $o->id; ?></a></td>
				<td><?php echo esc_html( wp_date( 'j M Y, H:i', cbaz_order_ts( $o->created ) ) ); ?></td>
				<td><span class="cbaz-pill"><?php echo esc_html( wc_get_order_status_name( str_replace( 'wc-', '', $o->status ) ) ); ?></span></td>
				<td><?php echo esc_html( $o->device ? $o->device : '—' ); ?></td>
				<td><?php echo $o->country ? cbaz_country_flag( $o->country ) . ' ' . esc_html( cbaz_country_name( $o->country ) ) : '—'; // phpcs:ignore ?></td>
				<td class="num"><?php echo esc_html( cbaz_money( $o->total ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $orders ) : ?>
			<tr><td colspan="6" class="cbaz-empty">Aucune commande attribuée sur la période.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</section>
