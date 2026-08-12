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
	'enregistree' => [ 'ok', 'Campagne enregistrée.' ],
	'supprimee'   => [ 'ok', 'Fiche supprimée. Les commandes gardent leur attribution.' ],
	'incomplete'  => [ 'ko', 'Il faut au minimum une source et un nom de campagne.' ],
];

$sous_onglets = [ 'liste' => 'Mes campagnes' ];

if ( $stats_on ) {
	$sous_onglets['performances'] = 'Performances';
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
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'CA des campagnes' ); // phpcs:ignore ?><p class="cbaz-kpi__label">CA des campagnes</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $revenue ) ); ?></p><p class="cbaz-kpi__note">remboursements déduits</p></div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Investi' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Investi</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_money( $cost ) ); ?></p></div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Retour global' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Retour global</p>
		<p class="cbaz-kpi__value"><?php echo $cost > 0 ? esc_html( number_format_i18n( $revenue / $cost, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></p>
		<p class="cbaz-kpi__note">euros rentrés par euro dépensé</p>
	</div>
	<div class="cbaz-kpi"><?php echo cbaz_kpi_icon( 'Commandes' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Commandes</p><p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $orders ) ); ?></p><p class="cbaz-kpi__note"><?php echo esc_html( cbaz_int( $visits ) ); ?> visites</p></div>
</div>
<?php endif; ?>

<?php if ( 'performances' === $onglet && $stats_on ) : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'money', 2 ); // phpcs:ignore ?>
			<div><h2>Rentabilité par campagne</h2><p>Chiffre d’affaires net lu sur les commandes. Toute visite balisée apparaît, fiche créée ou non.</p></div>
		</header>

		<?php echo cbaz_table_tools( 'Rechercher une campagne…', 'campagnes' ); // phpcs:ignore ?>

		<table class="cbaz-table">
			<thead>
				<tr>
					<th>Campagne</th><th>Source</th>
					<th class="num">Visiteurs</th><th class="num">Visites</th>
					<th class="num">Commandes</th><th class="num">Conv.</th>
					<th class="num">CA net</th><th class="num">Retour</th>
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
			<?php if ( ! $report ) : ?><tr><td colspan="8" class="cbaz-empty">Aucune campagne mesurée sur la période.</td></tr><?php endif; ?>
			</tbody>
		</table>

		<?php echo cbaz_table_count( count( $report ), count( $report ), 'campagnes' ); // phpcs:ignore ?>
	</section>

<?php else : ?>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'tag', 3 ); // phpcs:ignore ?>
			<div><h2>Mes campagnes</h2><p>Les opérations en place, leur lien à diffuser et ce qu’elles rapportent.</p></div>
			<a class="cbaz-ctrl cbaz-ctrl--primary" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				Créer une campagne
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
						<span><em>Visites</em><?php echo esc_html( cbaz_int( $st ? $st->sessions : 0 ) ); ?></span>
						<span><em>Commandes</em><?php echo esc_html( cbaz_int( $st ? $st->orders : 0 ) ); ?></span>
						<span><em>CA net</em><?php echo esc_html( cbaz_money( $st ? $st->revenue : 0 ) ); ?></span>
						<span><em>Retour</em><?php echo ( $st && null !== $st->roas ) ? esc_html( number_format_i18n( $st->roas, 2 ) . ' ×' ) : '<span class="cbaz-faint">—</span>'; ?></span>
					</div>
					<?php endif; ?>

					<div class="cbaz-copy">
						<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url( $c ) ); ?>">
						<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
					</div>

					<div class="cbaz-campaign__actions">
						<a href="<?php echo esc_url( cbaz_url( [ 'id' => $c->id ] ) ); ?>">Voir le détail</a>
						<a href="<?php echo esc_url( cbaz_url( [ 'edit' => $c->id ] ) ); ?>">Modifier</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Supprimer cette fiche ? Les commandes déjà attribuées seront conservées.');">
							<input type="hidden" name="action" value="cbaz_campaign">
							<?php wp_nonce_field( 'cbaz_campaign' ); ?>
							<input type="hidden" name="delete" value="<?php echo (int) $c->id; ?>">
							<button type="submit" class="cbaz-linkish">Supprimer</button>
						</form>
					</div>
				</li>
			<?php endforeach; ?>

			<?php if ( ! $sheets ) : ?>
				<li class="cbaz-blank">
					<p class="cbaz-blank__title">Aucune campagne pour l’instant</p>
					<p>Crée ta première opération : tu obtiendras un lien court à mettre en story ou en bio, et tu sauras exactement ce qu’il a rapporté.</p>
					<a class="cbaz-btn" href="<?php echo esc_url( cbaz_url( [ 'nouvelle' => 1 ] ) ); ?>">Créer une campagne</a>
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
			<div><h2>Campagnes mesurées sans fiche</h2><p>Des visites balisées sont arrivées sous ces noms, sans qu’une fiche existe.</p></div>
			</header>

			<ul class="cbaz-list">
				<?php foreach ( $sans_fiche as $r ) : ?>
					<li class="cbaz-list__row">
						<span><strong><?php echo esc_html( $r->campaign ); ?></strong> <em><?php echo esc_html( $r->source ); ?></em></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $r->sessions ) ); ?> visites · <?php echo esc_html( cbaz_money( $r->revenue ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="cbaz-note">Créer la fiche correspondante permet d’y renseigner un budget, donc de calculer un retour. Les visites déjà mesurées la rejoindront.</p>
		</section>
	<?php endif; ?>

<?php endif; ?>

<?php if ( ! $stats_on ) : ?>
	<p class="cbaz-note">
		Création de campagnes disponible gratuitement. Analyse détaillée des performances disponible avec Pro.
	</p>

	<?php
	cbaz_pro_notice(
		'Performances des campagnes',
		'Pro mesure les visites, les commandes, le chiffre d’affaires, le taux de conversion et le retour sur investissement de chaque campagne.'
	);
	?>
<?php endif; ?>
