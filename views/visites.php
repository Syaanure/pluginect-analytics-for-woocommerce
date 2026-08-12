<?php
/**
 * Visites, une par une.
 *
 * L'onglet « Parcours visiteurs » de Comportement montre les chemins
 * AGRÉGÉS : où l'on arrive, puis où l'on va, toutes visites confondues.
 * Ici c'est l'inverse — chaque ligne est une visite réelle, déroulée de
 * bout en bout, et l'on voit à quelle étape précise elle s'est arrêtée.
 *
 * Les deux se répondent : l'un dit la tendance, l'autre donne le cas.
 */

defined( 'ABSPATH' ) || exit;

// ══════════════════════════════════════════════════════════════
//  UNE VISITE EN PARTICULIER
// ══════════════════════════════════════════════════════════════

if ( ! empty( $_GET['visite'] ) ) {
	$session = cbaz_session( (int) $_GET['visite'] );

	if ( ! $session ) {
		echo '<div class="cbaz-notice cbaz-notice--ko">Cette visite n’existe plus — elle a peut-être été purgée.</div>';

		return;
	}

	$timeline = cbaz_session_timeline( $session->id );
	$duree    = (int) $session->engaged_seconds;
	?>

	<p class="cbaz-back">
		<a href="<?php echo esc_url( cbaz_url( [], [ 'visite' ] ) ); ?>">← Tous les parcours</a>
	</p>

	<div class="cbaz-kpis cbaz-kpis--4">
		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( 'Provenance' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Provenance</p>
			<p class="cbaz-kpi__value cbaz-kpi__value--sm">
				<?php echo cbaz_source_brand( $session->source ); // phpcs:ignore ?>
				<?php echo esc_html( $session->source ); ?>
			</p>
			<p class="cbaz-kpi__note">
				<?php echo esc_html( $session->medium ); ?>
				<?php if ( $session->campaign ) : ?> · campagne <?php echo esc_html( $session->campaign ); ?><?php endif; ?>
			</p>
		</div>

		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( 'Durée' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Durée</p>
			<p class="cbaz-kpi__value"><?php echo esc_html( cbaz_duration( $duree ) ); ?></p>
			<p class="cbaz-kpi__note"><?php echo esc_html( cbaz_int( $session->pageviews ) ); ?> pages vues</p>
		</div>

		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( 'Contexte' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Contexte</p>
			<p class="cbaz-kpi__value cbaz-kpi__value--sm">
				<?php echo cbaz_country_flag( $session->country ); // phpcs:ignore ?>
				<?php echo esc_html( cbaz_country_name( $session->country ) ); ?>
			</p>
			<p class="cbaz-kpi__note"><?php echo esc_html( ucfirst( $session->device ) ); ?> · <?php echo esc_html( $session->browser ); ?></p>
		</div>

		<div class="cbaz-kpi">
			<?php echo cbaz_kpi_icon( 'Issue' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Issue</p>
			<p class="cbaz-kpi__value cbaz-kpi__value--sm">
				<?php echo $session->order_id ? esc_html( cbaz_money( $session->revenue ) ) : 'Sans achat'; ?>
			</p>
			<?php if ( $session->order_id ) : ?>
				<p class="cbaz-kpi__note">
					<a href="<?php echo esc_url( cbaz_order_edit_url( (int) $session->order_id ) ); ?>">Commande #<?php echo (int) $session->order_id; ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'path', 4 ); // phpcs:ignore ?>
			<div>
				<h2>Déroulé de la visite</h2>
				<p><?php echo esc_html( wp_date( 'l j F Y, H:i', cbaz_ts( $session->started_at ) ) ); ?></p>
			</div>
		</header>

		<ul class="cbaz-timeline">
			<?php foreach ( $timeline as $k => $step ) : ?>
				<?php $ev = cbaz_event_label( $step->kind, $step->object_id, $step->value, $step->path ); ?>
				<li class="cbaz-timeline--<?php echo esc_attr( $ev['tone'] ); ?>">
					<div class="cbaz-timeline__top">
						<span class="cbaz-timeline__time"><?php echo esc_html( wp_date( 'H:i:s', cbaz_ts( $step->at ) ) ); ?></span>
						<span class="cbaz-badge cbaz-badge--<?php echo esc_attr( $ev['tone'] ); ?>"><?php echo esc_html( $ev['label'] ); ?></span>
						<?php if ( $k > 0 ) : ?>
							<span class="cbaz-timeline__gap">
								+ <?php echo esc_html( cbaz_duration( strtotime( $step->at ) - strtotime( $timeline[ $k - 1 ]->at ) ) ); ?>
							</span>
						<?php endif; ?>
					</div>
					<?php $quoi = $ev['detail'] ? $ev['detail'] : ( $step->title ? $step->title : $step->path ); ?>

					<p class="cbaz-timeline__what">
						<?php if ( $ev['url'] ) : ?>
							<?php // Le produit est cliquable : de la statistique, on veut aller à la fiche. ?>
							<a href="<?php echo esc_url( $ev['url'] ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $quoi ? $quoi : $ev['label'] ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $quoi ? $quoi : $ev['label'] ); ?>
						<?php endif; ?>
					</p>

					<?php if ( $ev['price'] ) : ?>
						<p class="cbaz-timeline__who"><?php echo esc_html( $ev['price'] ); ?></p>
					<?php elseif ( $step->path && ! $ev['detail'] ) : ?>
						<p class="cbaz-timeline__who"><?php echo esc_html( $step->path ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>

			<?php if ( ! $session->order_id && $timeline ) : ?>
				<li class="cbaz-timeline--stop">
					<div class="cbaz-timeline__top">
						<span class="cbaz-timeline__time"><?php echo esc_html( wp_date( 'H:i:s', cbaz_ts( $session->last_seen ) ) ); ?></span>
						<span class="cbaz-badge cbaz-badge--stop">Fin de visite</span>
					</div>
					<p class="cbaz-timeline__what">S’est arrêtée sur <?php echo esc_html( cbaz_pretty_path( $session->exit_path ) ); ?></p>
				</li>
			<?php endif; ?>

			<?php if ( ! $timeline ) : ?><li class="cbaz-empty">Aucune étape enregistrée pour cette visite.</li><?php endif; ?>
		</ul>

		<p class="cbaz-note">
			Cette visite est anonyme : elle porte une empreinte non réversible, renouvelée chaque nuit, et aucune
			adresse IP. Ce qu’on lit ici est un enchaînement de pages, pas une personne.
		</p>
	</section>

	<?php
	return;
}

// ══════════════════════════════════════════════════════════════
//  TOUS LES PARCOURS
// ══════════════════════════════════════════════════════════════

$filtre = 'toutes';

if ( cbaz_can( 'journeys_filters' ) ) {
	$filtre = cbaz_subtabs( 'lot', [
		'toutes'     => 'Tous les parcours',
		'converties' => 'Ceux qui achètent',
		'non-acheteurs' => 'Non acheteurs',
		'abandons'   => 'Paniers abandonnés',
		'longues'    => 'Les longs',
		'rebonds'    => 'Les rebonds',
	], 'toutes' );
}

$journey_search = cbaz_can( 'journeys_filters' ) ? sanitize_text_field( wp_unslash( $_GET['parcours_q'] ?? '' ) ) : '';
$journey_page   = cbaz_can( 'journeys_full' ) ? max( 1, (int) ( $_GET['parcours_page'] ?? 1 ) ) : 1;
$journey_limit  = cbaz_can( 'journeys_full' ) ? 40 : CBAZ_FREE_JOURNEYS;
$journey_only   = 'toutes' === $filtre ? '' : $filtre;
$sessions       = cbaz_sessions_list( $range, $journey_limit, $journey_only, ( $journey_page - 1 ) * $journey_limit, $journey_search );
$journey_total  = cbaz_sessions_count( $range, $journey_only, $journey_search );
$trails   = cbaz_sessions_trails( wp_list_pluck( $sessions, 'id' ) );

// Moyennes et points d'arrêt lus sur le lot affiché : aucune requête
// de plus, et les chiffres décrivent exactement ce qu'on a sous les yeux.
$nb      = count( $sessions );
$pages   = $nb ? array_sum( wp_list_pluck( $sessions, 'pageviews' ) ) / $nb : 0;
$temps   = $nb ? array_sum( wp_list_pluck( $sessions, 'duration' ) ) / $nb : 0;
$achats  = count( array_filter( $sessions, fn( $v ) => $v->order_id > 0 ) );
$arrets  = [];

foreach ( $sessions as $v ) {
	if ( $v->order_id ) {
		continue;
	}

	$cle = cbaz_pretty_path( $v->exit_path );

	if ( '' === $cle ) {
		continue;
	}

	$arrets[ $cle ] = ( $arrets[ $cle ] ?? 0 ) + 1;
}

arsort( $arrets );
$arrets   = array_slice( $arrets, 0, 6, true );
$max_arret = $arrets ? max( $arrets ) : 1;
?>

<?php if ( cbaz_can( 'journeys_stats' ) ) : ?>
<div class="cbaz-kpis cbaz-kpis--4">
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Parcours affichés' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Parcours affichés</p>
		<p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $nb ) ); ?></p>
		<p class="cbaz-kpi__note">les plus récents de la période</p>
	</div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Pages par visite' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Pages par visite</p>
		<p class="cbaz-kpi__value"><?php echo esc_html( number_format_i18n( $pages, 1 ) ); ?></p>
	</div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Durée moyenne' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Durée moyenne</p>
		<p class="cbaz-kpi__value"><?php echo esc_html( cbaz_duration( (int) $temps ) ); ?></p>
	</div>
	<div class="cbaz-kpi">
		<?php echo cbaz_kpi_icon( 'Vont jusqu’à l’achat' ); // phpcs:ignore ?><p class="cbaz-kpi__label">Vont jusqu’à l’achat</p>
		<p class="cbaz-kpi__value"><?php echo esc_html( cbaz_int( $achats ) ); ?></p>
		<p class="cbaz-kpi__note"><?php echo esc_html( cbaz_pct( $nb ? ( $achats / $nb ) * 100 : 0, 1 ) ); ?> du lot</p>
	</div>
</div>

<?php endif; ?>

<?php if ( $arrets && cbaz_can( 'journeys_stats' ) ) : ?>
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2>Où ces visites s’arrêtent</h2><p>La dernière page vue, quand la visite n’a pas abouti à une commande.</p></div>
		</header>

		<ul class="cbaz-list">
			<?php $i = 0; ?>
			<?php foreach ( $arrets as $page => $n ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><strong><?php echo esc_html( $page ); ?></strong></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $n ) ); ?> arrêts</span>
					</div>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $n, $max_arret ) ); // phpcs:ignore ?>
				</li>
				<?php $i++; ?>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'path', 4 ); // phpcs:ignore ?>
			<div>
			<h2>Parcours, un par un</h2>
			<p>Chaque ligne est une visite réelle, lue de gauche à droite. Le dernier bloc dit où elle s’est arrêtée.</p>
		</div>
	</header>

	<?php if ( cbaz_can( 'journeys_filters' ) ) : ?>
		<form class="cbaz-tabletools" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="cbaz-visites">
			<input type="hidden" name="periode" value="<?php echo esc_attr( $range['preset'] ); ?>">
			<input type="hidden" name="lot" value="<?php echo esc_attr( $filtre ); ?>">
			<?php if ( 'perso' === $range['preset'] ) : ?><input type="hidden" name="du" value="<?php echo esc_attr( $range['du'] ); ?>"><input type="hidden" name="au" value="<?php echo esc_attr( $range['au'] ); ?>"><?php endif; ?>
			<label><span class="screen-reader-text">Rechercher un parcours</span><input type="search" name="parcours_q" value="<?php echo esc_attr( $journey_search ); ?>" placeholder="Page, source, campagne, pays…"></label>
			<button class="cbaz-ctrl cbaz-ctrl--mini">Rechercher</button>
		</form>
	<?php endif; ?>

	<ul class="cbaz-trails">
		<?php foreach ( $sessions as $v ) : ?>
			<?php
			$etapes = $trails[ (int) $v->id ] ?? [];
			$total  = count( $etapes );

			// Au-delà d'une dizaine d'étapes la ligne devient illisible :
			// on garde le début et la fin, qui sont ce qui se lit.
			$coupe = $total > 10;
			$avant = $coupe ? array_slice( $etapes, 0, 5 ) : $etapes;
			$apres = $coupe ? array_slice( $etapes, -3 ) : [];
			?>
			<li class="cbaz-trip<?php echo $v->order_id ? ' is-won' : ''; ?>">
				<div class="cbaz-trip__head">
					<a class="cbaz-trip__when" href="<?php echo esc_url( cbaz_url( [ 'visite' => $v->id ] ) ); ?>">
						<?php echo esc_html( wp_date( 'j M · H:i', cbaz_ts( $v->started_at ) ) ); ?>
					</a>

					<span class="cbaz-trip__src">
						<?php echo cbaz_source_brand( $v->source ); // phpcs:ignore ?>
						<?php echo esc_html( $v->source ); ?>
						<?php if ( $v->campaign ) : ?><em>· <?php echo esc_html( $v->campaign ); ?></em><?php endif; ?>
					</span>

					<span class="cbaz-trip__ctx">
						<?php echo cbaz_country_flag( $v->country ); // phpcs:ignore ?>
						<?php echo esc_html( ucfirst( $v->device ) ); ?>
						· <?php echo $v->is_new ? 'nouvelle' : 'déjà venue'; ?>
					</span>

					<span class="cbaz-trip__meta">
						<span><?php echo esc_html( cbaz_int( $v->pageviews ) ); ?> pages</span>
						<span><?php echo esc_html( cbaz_duration( $v->duration ) ); ?></span>
						<?php if ( $v->order_id ) : ?>
							<span class="cbaz-delta cbaz-delta--up"><?php echo esc_html( cbaz_money( $v->revenue ) ); ?></span>
						<?php endif; ?>
					</span>
				</div>

				<div class="cbaz-trail">
					<?php $precedent = null; ?>

					<?php foreach ( $avant as $s ) : ?>
						<?php $e = cbaz_trail_step( $s ); ?>
						<span class="cbaz-trail__step cbaz-trail__step--<?php echo esc_attr( $e['tone'] ); ?>">
							<b title="<?php echo esc_attr( $e['nom'] ); ?>"><?php echo esc_html( $e['nom'] ); ?></b>
							<em><?php echo esc_html( null === $precedent ? 'arrivée' : '+ ' . cbaz_duration( $e['at'] - $precedent ) ); ?><?php if ( $e['quoi'] ) : ?> · <?php echo esc_html( $e['quoi'] ); endif; ?></em>
						</span>
						<?php $precedent = $e['at']; ?>
					<?php endforeach; ?>

					<?php if ( $coupe ) : ?>
						<a class="cbaz-trail__more" href="<?php echo esc_url( cbaz_url( [ 'visite' => $v->id ] ) ); ?>">
							+ <?php echo esc_html( cbaz_int( $total - 8 ) ); ?> étapes
						</a>
						<?php $precedent = null; ?>
						<?php foreach ( $apres as $s ) : ?>
							<?php $e = cbaz_trail_step( $s ); ?>
							<span class="cbaz-trail__step cbaz-trail__step--<?php echo esc_attr( $e['tone'] ); ?>">
								<b title="<?php echo esc_attr( $e['nom'] ); ?>"><?php echo esc_html( $e['nom'] ); ?></b>
								<em><?php echo esc_html( null === $precedent ? '…' : '+ ' . cbaz_duration( $e['at'] - $precedent ) ); ?><?php if ( $e['quoi'] ) : ?> · <?php echo esc_html( $e['quoi'] ); endif; ?></em>
							</span>
							<?php $precedent = $e['at']; ?>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( ! $etapes ) : ?>
						<span class="cbaz-trail__step">
							<b><?php echo esc_html( cbaz_pretty_path( $v->entry_path ) ); ?></b>
							<em>arrivée</em>
						</span>
					<?php endif; ?>

					<?php if ( $v->order_id ) : ?>
						<span class="cbaz-trail__end cbaz-trail__end--won">
							<b>Commande</b>
							<em><?php echo esc_html( cbaz_money( $v->revenue ) ); ?></em>
						</span>
					<?php else : ?>
						<span class="cbaz-trail__end cbaz-trail__end--stop">
							<b>S’arrête ici</b>
							<em><?php echo esc_html( cbaz_pretty_path( $v->exit_path ) ); ?></em>
						</span>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>

		<?php if ( ! $sessions ) : ?>
			<li class="cbaz-empty">Aucune visite sur la période.</li>
		<?php endif; ?>
	</ul>

	<p class="cbaz-note">
		L’onglet <strong>Parcours visiteurs</strong> de Comportement montre les chemins agrégés — la tendance.
		Cette page montre les visites réelles, une par une : c’est là qu’on voit à quelle étape précise
		un panier a été abandonné. Clique l’horodatage pour le déroulé complet, à la seconde près.
	</p>
</section>

<?php if ( cbaz_can( 'journeys_full' ) && $journey_total > $journey_limit ) : ?>
	<nav class="tablenav-pages" aria-label="Pagination des parcours">
		<?php echo wp_kses_post( paginate_links( [ 'base' => cbaz_url( [ 'parcours_page' => '%#%', 'parcours_q' => $journey_search, 'lot' => $filtre ] ), 'current' => $journey_page, 'total' => (int) ceil( $journey_total / $journey_limit ) ] ) ); ?>
	</nav>
<?php endif; ?>

<?php if ( ! cbaz_can( 'journeys_full' ) ) : ?>
	<p class="cbaz-note">
		Vous consultez les <?php echo esc_html( (string) CBAZ_FREE_JOURNEYS ); ?> parcours les plus récents.
	</p>

	<?php
	cbaz_pro_notice(
		'Historique complet des parcours',
		'Pro donne accès à l’historique complet, aux filtres avancés, aux acheteurs, aux paniers abandonnés, aux campagnes et aux chemins de conversion.'
	);
	?>
<?php endif; ?>
