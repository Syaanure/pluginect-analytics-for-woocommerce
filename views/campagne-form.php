<?php
/** Création ou modification d'une campagne, sur son propre écran. */

defined( 'ABSPATH' ) || exit;

$presets = [
	__( 'Story Instagram', 'shop-analytics-for-woocommerce' )   => [ 'instagram', 'social', 'story' ],
	__( 'Bio Instagram', 'shop-analytics-for-woocommerce' )     => [ 'instagram', 'social', 'bio' ],
	__( 'Post Instagram', 'shop-analytics-for-woocommerce' )    => [ 'instagram', 'social', 'post' ],
	__( 'TikTok', 'shop-analytics-for-woocommerce' )            => [ 'tiktok', 'social', 'video' ],
	__( 'Newsletter', 'shop-analytics-for-woocommerce' )        => [ 'newsletter', 'email', '' ],
	__( 'Influenceuse', 'shop-analytics-for-woocommerce' )      => [ 'influence', 'partenariat', '' ],
	__( 'Flyer ou carte', 'shop-analytics-for-woocommerce' )    => [ 'flyer', 'print', '' ],
	__( 'Publicité payante', 'shop-analytics-for-woocommerce' ) => [ 'meta', 'cpc', '' ],
];
?>

<p class="cbaz-back">
	<a href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>"><?php echo esc_html__( '← Toutes les campagnes', 'shop-analytics-for-woocommerce' ); ?></a>
</p>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<div>
				<h2><?php echo esc_html( $edit ? __( 'Modifier la campagne', 'shop-analytics-for-woocommerce' ) : __( 'Nouvelle campagne', 'shop-analytics-for-woocommerce' ) ); ?></h2>
				<p><?php echo esc_html__( 'Seuls la source et le nom de campagne sont obligatoires. Le reste affine le rapport.', 'shop-analytics-for-woocommerce' ); ?></p>
			</div>
		</header>

		<?php if ( ! $edit ) : ?>
			<p class="cbaz-fieldlabel"><?php echo esc_html__( 'Partir d’un modèle', 'shop-analytics-for-woocommerce' ); ?></p>
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

			<label><?php echo esc_html__( 'Nom interne', 'shop-analytics-for-woocommerce' ); ?>
				<input type="text" name="label" value="<?php echo esc_attr( $edit->label ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Story Instagram rentrée', 'shop-analytics-for-woocommerce' ); ?>">
			</label>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Nom de campagne *', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="campaign" value="<?php echo esc_attr( $edit->campaign ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'rentree-2026', 'shop-analytics-for-woocommerce' ); ?>" required>
				</label>
				<label><?php echo esc_html__( 'Statut', 'shop-analytics-for-woocommerce' ); ?>
					<select name="status">
						<?php foreach ( cbaz_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit->status ?? 'active', $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Source *', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="source" value="<?php echo esc_attr( $edit->source ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'instagram', 'shop-analytics-for-woocommerce' ); ?>" required>
				</label>
				<label><?php echo esc_html_x( 'Support', 'traffic medium', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="medium" value="<?php echo esc_attr( $edit->medium ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'social', 'shop-analytics-for-woocommerce' ); ?>">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Contenu', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="content" value="<?php echo esc_attr( $edit->content ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'story-1', 'shop-analytics-for-woocommerce' ); ?>">
				</label>
				<label><?php echo esc_html__( 'Terme', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="term" value="<?php echo esc_attr( $edit->term ?? '' ); ?>">
				</label>
			</div>

			<label><?php echo esc_html__( 'Page de destination', 'shop-analytics-for-woocommerce' ); ?>
				<input type="url" name="target_url" value="<?php echo esc_attr( $edit->target_url ?? home_url( '/' ) ); ?>">
			</label>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Budget investi (€)', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="cost" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->cost, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="150">
				</label>
				<label><?php echo esc_html__( 'Objectif de CA (€)', 'shop-analytics-for-woocommerce' ); ?>
					<input type="text" name="goal_revenue" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->goal_revenue, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="600">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Début', 'shop-analytics-for-woocommerce' ); ?>
					<input type="date" name="starts_on" value="<?php echo esc_attr( $edit->starts_on ?? '' ); ?>">
				</label>
				<label><?php echo esc_html__( 'Fin', 'shop-analytics-for-woocommerce' ); ?>
					<input type="date" name="ends_on" value="<?php echo esc_attr( $edit->ends_on ?? '' ); ?>">
				</label>
			</div>

			<label><?php echo esc_html__( 'Notes', 'shop-analytics-for-woocommerce' ); ?>
				<textarea name="notes" rows="3"><?php echo esc_textarea( $edit->notes ?? '' ); ?></textarea>
			</label>

			<div class="cbaz-preview" data-cbaz-preview>
				<span><?php echo esc_html__( 'Lien à diffuser', 'shop-analytics-for-woocommerce' ); ?></span>
				<code data-cbaz-preview-url><?php echo esc_html( $edit ? cbaz_campaign_url( $edit ) : home_url( '/' ) ); ?></code>
			</div>

			<p class="cbaz-form__actions">
				<button type="submit" class="cbaz-btn"><?php echo esc_html( $edit ? __( 'Enregistrer', 'shop-analytics-for-woocommerce' ) : __( 'Créer la campagne', 'shop-analytics-for-woocommerce' ) ); ?></button>
				<a class="cbaz-btn cbaz-btn--ghost" href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>"><?php echo esc_html__( 'Annuler', 'shop-analytics-for-woocommerce' ); ?></a>
			</p>
		</form>
	</section>

	<div class="cbaz-stack">
		<section class="cbaz-card">
			<header class="cbaz-card__head"><div><h2><?php echo esc_html__( 'Comment ça marche', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>

			<ul class="cbaz-facts">
				<li><?php echo wp_kses_post( __( 'Le lien ne porte <strong>qu’un seul paramètre</strong>. La source, le support et le contenu sont lus sur cette fiche au moment où la visite arrive.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Tu peux donc <strong>corriger la source après coup</strong> sans réémettre les liens déjà diffusés.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Le paramètre <strong>disparaît de la barre d’adresse</strong> une fois la visite comptée : la page reste partageable telle quelle.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo esc_html__( 'Les valeurs sont mises en minuscules et sans accent : « Instagram » et « instagram » comptent pour un seul canal.', 'shop-analytics-for-woocommerce' ); ?></li>
				<li><?php echo wp_kses_post( __( 'Sans budget, tout fonctionne — mais la colonne <strong>Retour</strong> restera vide, faute de savoir ce que l’opération a coûté.', 'shop-analytics-for-woocommerce' ) ); ?></li>
			</ul>
		</section>

		<?php if ( $edit ) : ?>
			<section class="cbaz-card">
				<header class="cbaz-card__head"><div><h2><?php echo esc_html__( 'Autres formats', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>

				<p class="cbaz-linklabel"><?php echo esc_html__( 'Sans point d’interrogation, pour un QR code', 'shop-analytics-for-woocommerce' ); ?></p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_short_url( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
				</div>

				<p class="cbaz-linklabel"><?php echo esc_html__( 'Format UTM complet, pour les régies', 'shop-analytics-for-woocommerce' ); ?></p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url_long( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>
