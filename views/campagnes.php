<?php
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
if ( ! empty( $_GET['id'] ) ) {
	include CBAZ_DIR . 'views/campagne.php';

	return;
}

$edit    = ! empty( $_GET['edit'] ) ? cbaz_campaign( (int) $_GET['edit'] ) : null;
$nouveau = isset( $_GET['nouvelle'] ) || $edit;

if ( $nouveau ) {
	include CBAZ_DIR . 'views/campagne-form.php';

	return;
}

$stats_on = cbaz_can( 'campaign_stats' );
$report   = $stats_on ? cbaz_campaign_report( $range ) : [];
$sheets = cbaz_campaigns_unique();
$msg    = sanitize_key( $_GET['msg'] ?? '' );

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
	'enregistree' => [ 'ok', __( 'Campagne enregistrée.', 'shop-analytics-for-woocommerce' ) ],
	'supprimee'   => [ 'ok', __( 'Fiche supprimée. Les commandes gardent leur attribution.', 'shop-analytics-for-woocommerce' ) ],
	'incomplete'  => [ 'ko', __( 'Il faut au minimum une source et un nom de campagne.', 'shop-analytics-for-woocommerce' ) ],
];

$sous_onglets = [ 'liste' => __( 'Mes campagnes', 'shop-analytics-for-woocommerce' ) ];

if ( $stats_on ) {
	$sous_onglets['performances'] = __( 'Performances', 'shop-analytics-for-woocommerce' );
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
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA des campagnes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'CA des campagnes', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $revenue ) ); ?></p><p class="cbaz-kpi__note"><?php echo esc_html__( 'remboursements déduits', 'shop-analytics-for-woocommerce' ); ?></p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Investi' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Investi', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $cost ) ); ?></p></div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Retour global' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Retour global', 'shop-analytics-for-woocommerce' ); ?></p>
		<p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( number_format_i18n( $revenue / $cost, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></p>
		<p class="cbaz-kpi__note"><?php echo esc_html__( 'euros rentrés par euro dépensé', 'shop-analytics-for-woocommerce' ); ?></p>
	</div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label"><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $orders ) ); ?></p><p class="cbaz-kpi__note"><?php /* translators: %1$s: number of visits. */ printf( esc_html__( '%1$s visites', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $visits ) ) ); ?></p></div>
</div>
<?php endif; ?>

<?php if ( 'performances' === $onglet && $stats_on ) : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Rentabilité par campagne', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Chiffre d’affaires net lu sur les commandes. Toute visite balisée apparaît, fiche créée ou non.', 'shop-analytics-for-woocommerce' ); ?></p></div>
		</header>

		<?php echo cbaz_table_tools( __( 'Rechercher une campagne…', 'shop-analytics-for-woocommerce' ), 'campagnes' ); // phpcs:ignore ?>

		<table class="cbaz-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Campagne', 'shop-analytics-for-woocommerce' ); ?></th><th><?php echo esc_html__( 'Source', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Visiteurs', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Visites', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html__( 'Conv.', 'shop-analytics-for-woocommerce' ); ?></th>
					<th class="num"><?php echo esc_html__( 'CA net', 'shop-analytics-for-woocommerce' ); ?></th><th class="num"><?php echo esc_html_x( 'Retour', 'return on ad spend', 'shop-analytics-for-woocommerce' ); ?></th>
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
			<?php if ( ! $report ) : ?><tr><td colspan="8" class="cbaz-empty"><?php echo esc_html__( 'Aucune campagne mesurée sur la période.', 'shop-analytics-for-woocommerce' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

		<?php echo cbaz_table_count( count( $report ), count( $report ), __( 'campagnes', 'shop-analytics-for-woocommerce' ) ); // phpcs:ignore ?>
	</section>

<?php else : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'tag', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Mes campagnes', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Les opérations en place, leur lien à diffuser et ce qu’elles rapportent.', 'shop-analytics-for-woocommerce' ); ?></p></div>
			<a class="cbaz-ctrl cbaz-ctrl--primary" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				<?php echo esc_html__( 'Créer une campagne', 'shop-analytics-for-woocommerce' ); ?>
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
						<span><em><?php echo esc_html__( 'Visites', 'shop-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_int( $st ? $st->sessions : 0 ) ); ?></span>
						<span><em><?php echo esc_html__( 'Commandes', 'shop-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_int( $st ? $st->orders : 0 ) ); ?></span>
						<span><em><?php echo esc_html__( 'CA net', 'shop-analytics-for-woocommerce' ); ?></em><?php echo esc_html( cbaz_money( $st ? $st->revenue : 0 ) ); ?></span>
						<span><em><?php echo esc_html_x( 'Retour', 'return on ad spend', 'shop-analytics-for-woocommerce' ); ?></em><?php echo ( $st && null !== $st->roas ) ? esc_html( number_format_i18n( $st->roas, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></span>
					</div>
					<?php endif; ?>

					<div class="cbaz-copy">
						<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url( $c ) ); ?>">
						<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
					</div>

					<div class="cbaz-campaign__actions">
						<a href="<?php echo esc_url( cbaz_url( [ 'id' => $c->id ] ) ); ?>"><?php echo esc_html__( 'Voir le détail', 'shop-analytics-for-woocommerce' ); ?></a>
						<a href="<?php echo esc_url( cbaz_url( [ 'edit' => $c->id ] ) ); ?>"><?php echo esc_html__( 'Modifier', 'shop-analytics-for-woocommerce' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer cette fiche ? Les commandes déjà attribuées seront conservées.', 'shop-analytics-for-woocommerce' ) ); ?>');">
							<input type="hidden" name="action" value="cbaz_campaign">
							<?php wp_nonce_field( 'cbaz_campaign' ); ?>
							<input type="hidden" name="delete" value="<?php echo (int) $c->id; ?>">
							<button type="submit" class="cbaz-linkish"><?php echo esc_html__( 'Supprimer', 'shop-analytics-for-woocommerce' ); ?></button>
						</form>
					</div>
				</li>
			<?php endforeach; ?>

			<?php if ( ! $sheets ) : ?>
				<li class="cbaz-blank">
					<p class="cbaz-blank__title"><?php echo esc_html__( 'Aucune campagne pour l’instant', 'shop-analytics-for-woocommerce' ); ?></p>
					<p><?php echo esc_html__( 'Crée ta première opération : tu obtiendras un lien court à mettre en story ou en bio, et tu sauras exactement ce qu’il a rapporté.', 'shop-analytics-for-woocommerce' ); ?></p>
					<a class="cbaz-btn" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>"><?php echo esc_html__( 'Créer une campagne', 'shop-analytics-for-woocommerce' ); ?></a>
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
			<div><h2><?php echo esc_html__( 'Campagnes mesurées sans fiche', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Des visites balisées sont arrivées sous ces noms, sans qu’une fiche existe.', 'shop-analytics-for-woocommerce' ); ?></p></div>
			</header>

			<ul class="cbaz-list">
				<?php foreach ( $sans_fiche as $r ) : ?>
					<li class="cbaz-list__row">
						<span><strong><?php echo esc_html( $r->campaign ); ?></strong> <em><?php echo esc_html( $r->source ); ?></em></span>
						<span class="cbaz-num"><?php /* translators: 1: visit count, 2: revenue. */ printf( esc_html__( '%1$s visites · %2$s', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $r->sessions ) ), esc_html( cbaz_money( $r->revenue ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="cbaz-note"><?php echo esc_html__( 'Créer la fiche correspondante permet d’y renseigner un budget, donc de calculer un retour. Les visites déjà mesurées la rejoindront.', 'shop-analytics-for-woocommerce' ); ?></p>
		</section>
	<?php endif; ?>

<?php endif; ?>

<?php if ( ! $stats_on ) : ?>
	<p class="cbaz-note">
		<?php echo esc_html__( 'Création de campagnes disponible gratuitement. Analyse détaillée des performances disponible avec Pro.', 'shop-analytics-for-woocommerce' ); ?>
	</p>

	<?php
	cbaz_pro_notice(
		__( 'Performances des campagnes', 'shop-analytics-for-woocommerce' ),
		__( 'Pro mesure les visites, les commandes, le chiffre d’affaires, le taux de conversion et le retour sur investissement de chaque campagne.', 'shop-analytics-for-woocommerce' )
	);
	?>
<?php endif; ?>
