<?php
/** Fiche d'une campagne. */

defined( 'ABSPATH' ) || exit;

$campaign = cbaz_campaign( (int) $_GET['id'] );

if ( ! $campaign ) {
	printf( '<div class="cbaz-notice cbaz-notice--ko">%s</div>', esc_html__( 'Cette campagne n’existe plus.', 'shop-analytics-for-woocommerce' ) );

	return;
}

if ( ! cbaz_can( 'campaign_stats' ) ) {
	/*
	 * Sans le module Pro, la fiche reste consultable et modifiable — c'est
	 * la création de campagnes qui est gratuite — mais aucune mesure de
	 * performance n'est calculée ni affichée.
	 */
	printf( '<p class="cbaz-back"><a href="%1$s">%2$s</a></p>', esc_url( cbaz_url( [], [ 'id' ] ) ), esc_html__( '← Toutes les campagnes', 'shop-analytics-for-woocommerce' ) );

	cbaz_pro_notice(
		__( 'Performances de cette campagne', 'shop-analytics-for-woocommerce' ),
		__( 'Pro mesure les visites, les commandes, le chiffre d’affaires, le taux de conversion et le retour sur investissement de cette campagne.', 'shop-analytics-for-woocommerce' )
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
	<a href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'campagnes' ), 'periode' => $range['preset'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( '← Toutes les campagnes', 'shop-analytics-for-woocommerce' ); ?></a>
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
		<p class="cbaz-linklabel"><?php echo esc_html__( 'Lien à diffuser', 'shop-analytics-for-woocommerce' ); ?></p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
		</div>

		<p class="cbaz-linklabel"><?php echo esc_html__( 'Sans point d’interrogation, pour un QR code', 'shop-analytics-for-woocommerce' ); ?></p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_short_url( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
		</div>

		<p class="cbaz-linklabel"><?php echo esc_html__( 'Format UTM complet, pour les régies', 'shop-analytics-for-woocommerce' ); ?></p>
		<div class="cbaz-copy">
			<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url_long( $campaign ) ); ?>">
			<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
		</div>
		<a class="cbaz-btn cbaz-btn--ghost cbaz-btn--mini" href="<?php echo esc_url( add_query_arg( [ 'page' => cbaz_tab_page( 'campagnes' ), 'edit' => $campaign->id ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Modifier', 'shop-analytics-for-woocommerce' ); ?></a>
	</div>
</section>

<div class="cbaz-kpis">
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA net' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'CA net', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $revenue ) ); ?></p><?php if ( $row && $row->refunded > 0 ) : ?><p class="cbaz-kpi__note"><?php /* translators: %1$s: refunded amount. */ printf( esc_html__( '%1$s remboursés', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_money( $row->refunded ) ) ); ?></p><?php endif; ?></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Budget' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Budget', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( cbaz_money( $cost ) ) : '—'; ?></p></div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Retour' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html_x( 'Retour', 'return on ad spend', 'shop-analytics-for-woocommerce' ); ?></p>
		<p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( number_format_i18n( $revenue / $cost, 2 ) . ' ×' ) : '—'; ?></p>
		<p class="cbaz-kpi__note"><?php /* translators: %1$s: campaign profit. */ printf( esc_html__( 'bénéfice %1$s', 'shop-analytics-for-woocommerce' ), $cost > 0 ? esc_html( cbaz_money( $revenue - $cost ) ) : '—' ); ?></p>
	</div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Visites' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Visites', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $row ? $row->sessions : 0 ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $row ? $row->orders : 0 ) ); ?></p><p class="cbaz-kpi__note"><?php /* translators: %1$s: conversion rate. */ printf( esc_html__( 'conversion %1$s', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_pct( $row ? $row->cr : 0 ) ) ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Coût par commande' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Coût par commande', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo ( $row && null !== $row->cpa ) ? esc_html( cbaz_money( $row->cpa ) ) : '—'; ?></p></div>
</div>

<?php if ( $goal > 0 ) : ?>
	<section class="cbaz-card">
		<header class="cbaz-card__head"><div><h2><?php echo esc_html__( 'Objectif', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php /* translators: 1: current revenue, 2: revenue goal. */ printf( esc_html__( '%1$s sur %2$s', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_money( $revenue ) ), esc_html( cbaz_money( $goal ) ) ); ?></p></div></header>
		<?php $pct = min( 100, ( $revenue / $goal ) * 100 ); ?>
		<div class="cbaz-funnel__track">
			<div class="cbaz-funnel__fill" style="width:<?php echo esc_attr( max( 1, $pct ) ); ?>%"></div>
			<span class="cbaz-funnel__pct"><?php echo esc_html( number_format_i18n( $pct, 1 ) ); ?> %</span>
		</div>
	</section>
<?php endif; ?>

<section class="cbaz-card">
	<header class="cbaz-card__head"><?php echo cbaz_icon( 'calendar', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Jour par jour', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
	<?php echo cbaz_chart( $series ); // phpcs:ignore ?>
</section>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'cart', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Commandes attribuées', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Chaque ligne porte la trace de cette campagne dans WooCommerce.', 'shop-analytics-for-woocommerce' ); ?></p></div>
	</header>

	<table class="cbaz-table">
		<thead><tr><th><?php echo esc_html__( 'Commande', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Date', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Statut', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Appareil', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Pays', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Total', 'shop-analytics-for-woocommerce' ); ?></th></tr></thead>
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
			<tr><td colspan="6" class="cbaz-empty"><?php echo esc_html__( 'Aucune commande attribuée sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</section>
