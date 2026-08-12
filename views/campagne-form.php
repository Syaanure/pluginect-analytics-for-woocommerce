<?php
/** Création ou modification d'une campagne, sur son propre écran. */

defined( 'ABSPATH' ) || exit;

$presets = [
	'Story Instagram'   => [ 'instagram', 'social', 'story' ],
	'Bio Instagram'     => [ 'instagram', 'social', 'bio' ],
	'Post Instagram'    => [ 'instagram', 'social', 'post' ],
	'TikTok'            => [ 'tiktok', 'social', 'video' ],
	'Newsletter'        => [ 'newsletter', 'email', '' ],
	'Influenceuse'      => [ 'influence', 'partenariat', '' ],
	'Flyer ou carte'    => [ 'flyer', 'print', '' ],
	'Publicité payante' => [ 'meta', 'cpc', '' ],
];
?>

<p class="cbaz-back">
	<a href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>">← Toutes les campagnes</a>
</p>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<div>
				<h2><?php echo $edit ? 'Modifier la campagne' : 'Nouvelle campagne'; ?></h2>
				<p>Seuls la source et le nom de campagne sont obligatoires. Le reste affine le rapport.</p>
			</div>
		</header>

		<?php if ( ! $edit ) : ?>
			<p class="cbaz-fieldlabel">Partir d’un modèle</p>
			<div class="cbaz-presets">
				<?php foreach ( $presets as $name => $values ) : ?>
					<button type="button" class="cbaz-preset" data-cbaz-preset
					        data-source="<?php echo esc_attr( $values[0] ); ?>"
					        data-medium="<?php echo esc_attr( $values[1] ); ?>"
					        data-content="<?php echo esc_attr( $values[2] ); ?>"><?php echo esc_html( $name ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbaz-form" data-cbaz-builder>
			<input type="hidden" name="action" value="cbaz_campaign">
			<?php wp_nonce_field( 'cbaz_campaign' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) ( $edit->id ?? 0 ); ?>">

			<label>Nom interne
				<input type="text" name="label" value="<?php echo esc_attr( $edit->label ?? '' ); ?>" placeholder="Story Instagram rentrée">
			</label>

			<div class="cbaz-form__row">
				<label>Nom de campagne *
					<input type="text" name="campaign" value="<?php echo esc_attr( $edit->campaign ?? '' ); ?>" placeholder="rentree-2026" required>
				</label>
				<label>Statut
					<select name="status">
						<?php foreach ( cbaz_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit->status ?? 'active', $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="cbaz-form__row">
				<label>Source *
					<input type="text" name="source" value="<?php echo esc_attr( $edit->source ?? '' ); ?>" placeholder="instagram" required>
				</label>
				<label>Support
					<input type="text" name="medium" value="<?php echo esc_attr( $edit->medium ?? '' ); ?>" placeholder="social">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label>Contenu
					<input type="text" name="content" value="<?php echo esc_attr( $edit->content ?? '' ); ?>" placeholder="story-1">
				</label>
				<label>Terme
					<input type="text" name="term" value="<?php echo esc_attr( $edit->term ?? '' ); ?>">
				</label>
			</div>

			<label>Page de destination
				<input type="url" name="target_url" value="<?php echo esc_attr( $edit->target_url ?? home_url( '/' ) ); ?>">
			</label>

			<div class="cbaz-form__row">
				<label>Budget investi (€)
					<input type="text" name="cost" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->cost, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="150">
				</label>
				<label>Objectif de CA (€)
					<input type="text" name="goal_revenue" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->goal_revenue, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="600">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label>Début
					<input type="date" name="starts_on" value="<?php echo esc_attr( $edit->starts_on ?? '' ); ?>">
				</label>
				<label>Fin
					<input type="date" name="ends_on" value="<?php echo esc_attr( $edit->ends_on ?? '' ); ?>">
				</label>
			</div>

			<label>Notes
				<textarea name="notes" rows="3"><?php echo esc_textarea( $edit->notes ?? '' ); ?></textarea>
			</label>

			<div class="cbaz-preview" data-cbaz-preview>
				<span>Lien à diffuser</span>
				<code data-cbaz-preview-url><?php echo esc_html( $edit ? cbaz_campaign_url( $edit ) : home_url( '/' ) ); ?></code>
			</div>

			<p class="cbaz-form__actions">
				<button type="submit" class="cbaz-btn"><?php echo $edit ? 'Enregistrer' : 'Créer la campagne'; ?></button>
				<a class="cbaz-btn cbaz-btn--ghost" href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>">Annuler</a>
			</p>
		</form>
	</section>

	<div class="cbaz-stack">
		<section class="cbaz-card">
			<header class="cbaz-card__head"><div><h2>Comment ça marche</h2></div></header>

			<ul class="cbaz-facts">
				<li>Le lien ne porte <strong>qu’un seul paramètre</strong>. La source, le support et le contenu sont lus sur cette fiche au moment où la visite arrive.</li>
				<li>Tu peux donc <strong>corriger la source après coup</strong> sans réémettre les liens déjà diffusés.</li>
				<li>Le paramètre <strong>disparaît de la barre d’adresse</strong> une fois la visite comptée : la page reste partageable telle quelle.</li>
				<li>Les valeurs sont mises en minuscules et sans accent : « Instagram » et « instagram » comptent pour un seul canal.</li>
				<li>Sans budget, tout fonctionne — mais la colonne <strong>Retour</strong> restera vide, faute de savoir ce que l’opération a coûté.</li>
			</ul>
		</section>

		<?php if ( $edit ) : ?>
			<section class="cbaz-card">
				<header class="cbaz-card__head"><div><h2>Autres formats</h2></div></header>

				<p class="cbaz-linklabel">Sans point d’interrogation, pour un QR code</p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_short_url( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
				</div>

				<p class="cbaz-linklabel">Format UTM complet, pour les régies</p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url_long( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>
