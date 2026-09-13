<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/**
 * Campagnes.
 *
 * L'écran s'ouvre sur la GESTION — la liste de ce qui tourne, et le
 * bouton pour en lancer une. Le rapport détaillé existe toujours, mais
 * en second onglet : on vient ici pour agir, pas pour contempler un
 * tableau de bord de plus.
 */

defined( 'ABSPATH' ) || exit;

// Une campagne demandée nommément bascule sur sa fiche détaillée.
if ( ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, navigation
	include CBAZ_DIR . 'views/campagne.php';

	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule, navigation
$edit    = ! empty( $_GET['edit'] ) ? cbaz_campaign( absint( wp_unslash( $_GET['edit'] ) ) ) : null;
$nouveau = isset( $_GET['nouvelle'] ) || $edit;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( $nouveau ) {
	include CBAZ_DIR . 'views/campagne-form.php';

	return;
}

$stats_on = cbaz_can( 'campaign_stats' );
$report   = $stats_on ? cbaz_campaign_report( $range ) : [];
$sheets = cbaz_campaigns_unique();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- simple drapeau d'affichage après redirection
$msg    = sanitize_key( $_GET['msg'] ?? '' );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$revenue = array_sum( array_map( fn( $r ) => $r->revenue, $report ) );
$cost    = array_sum( array_map( fn( $r ) => $r->cost, $report ) );
$orders  = array_sum( array_map( fn( $r ) => $r->orders, $report ) );
$visits  = array_sum( array_map( fn( $r ) => $r->sessions, $report ) );

// Chiffres par campagne, pour les afficher sur chaque fiche.
$stats = [];

foreach ( $report as $r ) {
	$stats[ $r->campaign ] = $r;
}

$notices = [
	'enregistree' => [ 'ok', __( 'Registered campaign.', 'pluginect-analytics-for-woocommerce' ) ],
	'supprimee'   => [ 'ok', __( 'File deleted. Orders retain their attribution.', 'pluginect-analytics-for-woocommerce' ) ],
	'incomplete'  => [ 'ko', __( 'You need at least one source and a campaign name.', 'pluginect-analytics-for-woocommerce' ) ],
];

$sous_onglets = [ 'liste' => __( 'My campaigns', 'pluginect-analytics-for-woocommerce' ) ];

if ( $stats_on ) {
	$sous_onglets['performances'] = __( 'Performance', 'pluginect-analytics-for-woocommerce' );
}

$onglet = count( $sous_onglets ) > 1
	? cbaz_subtabs( 'onglet', $sous_onglets, 'liste' )
	: 'liste';
?>

<?php if ( isset( $notices[ $msg ] ) ) : ?>
	<div class="cbaz-notice cbaz-notice--<?php echo esc_attr( $notices[ $msg ][0] ); ?>"><?php echo esc_html( $notices[ $msg ][1] ); ?></div>
<?php endif; ?>

<?php if ( $stats_on ) : ?>
<div class="cbaz-kpis cbaz-kpis--4">
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA des campagnes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Campaign revenue', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'campaign_revenue' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $revenue ) ); ?></p><p class="cbaz-kpi__note"><?php echo esc_html__( 'refunds deducted', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Investi' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Invested', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'invested' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $cost ) ); ?></p></div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Retour global' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Overall return', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'roas' ); // phpcs:ignore ?></p>
		<p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( number_format_i18n( $revenue / $cost, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></p>
		<p class="cbaz-kpi__note"><?php echo esc_html__( 'euros earned per euro spent', 'pluginect-analytics-for-woocommerce' ); ?></p>
	</div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'campaign_orders' ); // phpcs:ignore ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $orders ) ); ?></p><p class="cbaz-kpi__note"><?php /* translators: %1$s: number of visits. */ printf( esc_html__( '%1$s visits', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $visits ) ) ); ?></p></div>
</div>
<?php cbaz_insights_box( $range, 'campagnes' ); ?>
<?php endif; ?>

<?php if ( 'performances' === $onglet && $stats_on ) : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Profitability per campaign', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'roas' ); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Net revenue read from orders. Every tagged visit appears, whether or not a campaign record exists.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
		</header>

		<?php echo cbaz_table_tools( __( 'Search for a campaign…', 'pluginect-analytics-for-woocommerce' ), 'campagnes' ); // phpcs:ignore ?>

		<table class="cbaz-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Campaign', 'pluginect-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Source', 'pluginect-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Visitors', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Visits', 'pluginect-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Conv.', 'pluginect-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Net turnover', 'pluginect-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html_x( 'Return', 'return on ad spend', 'pluginect-analytics-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php $max = $report ? max( array_map( fn( $r ) => max( $r->revenue, 1 ), $report ) ) : 1; ?>
			<?php foreach ( $report as $i => $r ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $r->campaign ); ?></strong>
						<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $r->revenue, $max ) ); // phpcs:ignore ?>
					</td>
					<td><?php echo cbaz_source_brand( $r->source ); // phpcs:ignore ?><?php echo esc_html( $r->source ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $r->visitors ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $r->sessions ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_int( $r->orders ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_pct( $r->cr ) ); ?></td>
					<td class="num"><?php echo esc_html( cbaz_money( $r->revenue ) ); ?></td>
					<td class="num">
						<?php if ( null === $r->roas ) : ?>
							<span class="cbaz-faint">—</span>
						<?php else : ?>
							<span class="cbaz-delta cbaz-delta--<?php echo $r->roas >= 1 ? 'up' : 'down'; ?>"><?php echo esc_html( number_format_i18n( $r->roas, 2 ) ); ?> ×</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $report ) : ?><tr><td colspan="8" class="cbaz-empty"><?php echo esc_html__( 'No campaign measured over the period.', 'pluginect-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

		<?php echo cbaz_table_count( count( $report ), count( $report ), __( 'campaigns', 'pluginect-analytics-for-woocommerce' ) ); // phpcs:ignore ?>
	</section>

<?php else : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'tag', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'My campaigns', 'pluginect-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'The operations in place, their link to be broadcast and what they bring in.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
			<a class="cbaz-ctrl cbaz-ctrl--primary" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				<?php echo esc_html__( 'Create a campaign', 'pluginect-analytics-for-woocommerce' ); ?>
			</a>
		</header>

		<ul class="cbaz-campaigns">
			<?php foreach ( $sheets as $c ) : ?>
				<?php $st = $stats[ $c->campaign ] ?? null; ?>
				<li>
					<div class="cbaz-campaign__head">
						<div>
							<a class="cbaz-campaign__name" href="<?php echo esc_url( cbaz_url( [ 'id' => $c->id ] ) ); ?>">
								<?php echo esc_html( $c->label ? $c->label : $c->campaign ); ?>
							</a>
							<span class="cbaz-path"><?php echo esc_html( $c->source ); ?> / <?php echo esc_html( $c->medium ); ?> · <?php echo esc_html( $c->campaign ); ?></span>
						</div>
						<span class="cbaz-status-pill cbaz-status-pill--<?php echo esc_attr( $c->status ); ?>"><?php echo esc_html( cbaz_statuses()[ $c->status ] ?? $c->status ); ?></span>
					</div>

					<?php if ( $stats_on ) : ?>
					<div class="cbaz-campaign__stats">
						<span><em><?php echo esc_html__( 'Visits', 'pluginect-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_int( $st ? $st->sessions : 0 ) ); ?></span>
						<span><em><?php echo esc_html__( 'Orders', 'pluginect-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_int( $st ? $st->orders : 0 ) ); ?></span>
						<span><em><?php echo esc_html__( 'Net turnover', 'pluginect-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_money( $st ? $st->revenue : 0 ) ); ?></span>
						<span><em><?php echo esc_html_x( 'Return', 'return on ad spend', 'pluginect-analytics-for-woocommerce' ); ?></em><?php echo ( $st && null !== $st->roas ) ? esc_html( number_format_i18n( $st->roas, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></span>
					</div>
					<?php endif; ?>

					<div class="cbaz-copy">
						<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url( $c ) ); ?>">
						<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copy', 'pluginect-analytics-for-woocommerce' ); ?></button>
					</div>

					<div class="cbaz-campaign__actions">
						<a href="<?php echo esc_url( cbaz_url( [ 'id' => $c->id ] ) ); ?>"><?php echo esc_html__( 'See details', 'pluginect-analytics-for-woocommerce' ); ?></a>
						<a href="<?php echo esc_url( cbaz_url( [ 'edit' => $c->id ] ) ); ?>"><?php echo esc_html__( 'To modify', 'pluginect-analytics-for-woocommerce' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this record? Orders already assigned will be retained.', 'pluginect-analytics-for-woocommerce' ) ); ?>');">
							<input type="hidden" name="action" value="cbaz_campaign">
							<?php wp_nonce_field( 'cbaz_campaign' ); ?>
							<input type="hidden" name="delete" value="<?php echo (int) $c->id; ?>">
							<button type="submit" class="cbaz-linkish"><?php echo esc_html__( 'Delete', 'pluginect-analytics-for-woocommerce' ); ?></button>
						</form>
					</div>
				</li>
			<?php endforeach; ?>

			<?php if ( ! $sheets ) : ?>
				<li class="cbaz-blank">
					<p class="cbaz-blank__title"><?php echo esc_html__( 'No campaign yet', 'pluginect-analytics-for-woocommerce' ); ?></p>
					<p><?php echo esc_html__( 'Create your first campaign to get a short link for a story or bio and track exactly how much revenue it generated.', 'pluginect-analytics-for-woocommerce' ); ?></p>
					<a class="cbaz-btn" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>"><?php echo esc_html__( 'Create a campaign', 'pluginect-analytics-for-woocommerce' ); ?></a>
				</li>
			<?php endif; ?>
		</ul>
	</section>

	<?php
	$sans_fiche = array_filter( $report, fn( $r ) => ! $r->sheet );

	if ( $sans_fiche ) :
		?>
		<section class="cbaz-card">
			<header class="cbaz-card__head">
				<?php echo cbaz_icon( 'tag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Measured campaigns without a record', 'pluginect-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Marked visits have arrived under these names, without a file existing.', 'pluginect-analytics-for-woocommerce' ); ?></p></div>
			</header>

			<ul class="cbaz-list">
				<?php foreach ( $sans_fiche as $r ) : ?>
					<li class="cbaz-list__row">
						<span><strong><?php echo esc_html( $r->campaign ); ?></strong> <em><?php echo esc_html( $r->source ); ?></em></span>
						<span class="cbaz-num"><?php /* translators: 1: visit count, 2: revenue. */ printf( esc_html__( '%1$s visits · %2$s', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $r->sessions ) ), esc_html( cbaz_money( $r->revenue ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="cbaz-note"><?php echo esc_html__( 'Creating the corresponding sheet allows you to enter a budget, therefore calculating a return. Visits already measured will join it.', 'pluginect-analytics-for-woocommerce' ); ?></p>
		</section>
	<?php endif; ?>

<?php endif; ?>

<?php if ( ! $stats_on ) : ?>
	<p class="cbaz-note">
		<?php echo esc_html__( 'Campaign creation available for free. Detailed performance analysis available with Pro.', 'pluginect-analytics-for-woocommerce' ); ?>
	</p>

	<?php
	cbaz_pro_notice(
		__( 'Campaign performance', 'pluginect-analytics-for-woocommerce' ),
		__( 'Pro measures visits, orders, revenue, conversion rate and ROI of each campaign.', 'pluginect-analytics-for-woocommerce' ),
		'campaigns'
	);
	?>
<?php endif; ?>
