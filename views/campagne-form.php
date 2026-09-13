<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/** Création ou modification d'une campagne, sur son propre écran. */

defined( 'ABSPATH' ) || exit;

$presets = [
	__( 'Instagram Story', 'pluginect-analytics-for-woocommerce' )   => [ 'instagram', 'social', 'story' ],
	__( 'Instagram Bio', 'pluginect-analytics-for-woocommerce' )     => [ 'instagram', 'social', 'bio' ],
	__( 'Instagram post', 'pluginect-analytics-for-woocommerce' )    => [ 'instagram', 'social', 'post' ],
	__( 'TikTok', 'pluginect-analytics-for-woocommerce' )            => [ 'tiktok', 'social', 'video' ],
	__( 'Newsletter', 'pluginect-analytics-for-woocommerce' )        => [ 'newsletter', 'email', '' ],
	__( 'Influencer', 'pluginect-analytics-for-woocommerce' )      => [ 'influence', 'partenariat', '' ],
	__( 'Flyer or card', 'pluginect-analytics-for-woocommerce' )    => [ 'flyer', 'print', '' ],
	__( 'Paid advertising', 'pluginect-analytics-for-woocommerce' ) => [ 'meta', 'cpc', '' ],
];
?>

<p class="cbaz-back">
	<a href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>"><?php echo esc_html__( '← All campaigns', 'pluginect-analytics-for-woocommerce' ); ?></a>
</p>

<div class="cbaz-grid cbaz-grid--2-1">
	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<div>
				<h2><?php echo esc_html( $edit ? __( 'Edit campaign', 'pluginect-analytics-for-woocommerce' ) : __( 'New campaign', 'pluginect-analytics-for-woocommerce' ) ); ?></h2>
				<p><?php echo esc_html__( 'Only the source and campaign name are required. The rest refines the report.', 'pluginect-analytics-for-woocommerce' ); ?></p>
			</div>
		</header>

		<?php if ( ! $edit ) : ?>
			<p class="cbaz-fieldlabel"><?php echo esc_html__( 'Starting from a model', 'pluginect-analytics-for-woocommerce' ); ?></p>
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

			<label><?php echo esc_html__( 'Internal name', 'pluginect-analytics-for-woocommerce' ); ?>
				<input type="text" name="label" value="<?php echo esc_attr( $edit->label ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Instagram story back to school', 'pluginect-analytics-for-woocommerce' ); ?>">
			</label>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Campaign name *', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="campaign" value="<?php echo esc_attr( $edit->campaign ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'back-to-school-2026', 'pluginect-analytics-for-woocommerce' ); ?>" required>
				</label>
				<label><?php echo esc_html__( 'Status', 'pluginect-analytics-for-woocommerce' ); ?>
					<select name="status">
						<?php foreach ( cbaz_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit->status ?? 'active', $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Source *', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="source" value="<?php echo esc_attr( $edit->source ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'instagram', 'pluginect-analytics-for-woocommerce' ); ?>" required>
				</label>
				<label><?php echo esc_html_x( 'Medium', 'traffic medium', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="medium" value="<?php echo esc_attr( $edit->medium ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'social', 'pluginect-analytics-for-woocommerce' ); ?>">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Content', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="content" value="<?php echo esc_attr( $edit->content ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'story-1', 'pluginect-analytics-for-woocommerce' ); ?>">
				</label>
				<label><?php echo esc_html__( 'Term', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="term" value="<?php echo esc_attr( $edit->term ?? '' ); ?>">
				</label>
			</div>

			<label><?php echo esc_html__( 'Destination page', 'pluginect-analytics-for-woocommerce' ); ?>
				<input type="url" name="target_url" value="<?php echo esc_attr( $edit->target_url ?? home_url( '/' ) ); ?>">
			</label>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Budget invested (€)', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="cost" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->cost, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="150">
				</label>
				<label><?php echo esc_html__( 'Revenue target (€)', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="text" name="goal_revenue" value="<?php echo esc_attr( $edit ? rtrim( rtrim( number_format( (float) $edit->goal_revenue, 2, '.', '' ), '0' ), '.' ) : '' ); ?>" placeholder="600">
				</label>
			</div>

			<div class="cbaz-form__row">
				<label><?php echo esc_html__( 'Beginning', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="date" name="starts_on" value="<?php echo esc_attr( $edit->starts_on ?? '' ); ?>">
				</label>
				<label><?php echo esc_html__( 'End', 'pluginect-analytics-for-woocommerce' ); ?>
					<input type="date" name="ends_on" value="<?php echo esc_attr( $edit->ends_on ?? '' ); ?>">
				</label>
			</div>

			<label><?php echo esc_html__( 'Notes', 'pluginect-analytics-for-woocommerce' ); ?>
				<textarea name="notes" rows="3"><?php echo esc_textarea( $edit->notes ?? '' ); ?></textarea>
			</label>

			<div class="cbaz-preview" data-cbaz-preview>
				<span><?php echo esc_html__( 'Link to share', 'pluginect-analytics-for-woocommerce' ); ?></span>
				<code data-cbaz-preview-url><?php echo esc_html( $edit ? cbaz_campaign_url( $edit ) : home_url( '/' ) ); ?></code>
			</div>

			<p class="cbaz-form__actions">
				<button type="submit" class="cbaz-btn"><?php echo esc_html( $edit ? __( 'Save', 'pluginect-analytics-for-woocommerce' ) : __( 'Create the campaign', 'pluginect-analytics-for-woocommerce' ) ); ?></button>
				<a class="cbaz-btn cbaz-btn--ghost" href="<?php echo esc_url( cbaz_url( [], [ 'edit', 'nouvelle' ] ) ); ?>"><?php echo esc_html__( 'Cancel', 'pluginect-analytics-for-woocommerce' ); ?></a>
			</p>
		</form>
	</section>

	<div class="cbaz-stack">
		<section class="cbaz-card">
			<header class="cbaz-card__head"><div><h2><?php echo esc_html__( 'How it works', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>

			<ul class="cbaz-facts">
				<li><?php echo wp_kses_post( __( 'The link contains <strong>only one parameter</strong>. Its source, medium and content are read from this campaign when the visit arrives.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'You can therefore <strong>correct the source afterwards</strong> without re-transmitting the links already broadcast.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'The parameter <strong>disappears from the address bar</strong> once the visit is counted, so the page remains shareable as-is.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo esc_html__( 'The values ​​are lowercase and without accent: “Instagram” and “instagram” count as one channel.', 'pluginect-analytics-for-woocommerce' ); ?></li>
				<li><?php echo wp_kses_post( __( 'Without a budget, everything works - but the column <strong>Retour</strong> will remain empty, because we do not know what the operation cost.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
			</ul>
		</section>

		<?php if ( $edit ) : ?>
			<section class="cbaz-card">
				<header class="cbaz-card__head"><div><h2><?php echo esc_html__( 'Other formats', 'pluginect-analytics-for-woocommerce' ); ?></h2></div></header>

				<p class="cbaz-linklabel"><?php echo esc_html__( 'Without question mark, for a QR code', 'pluginect-analytics-for-woocommerce' ); ?></p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_short_url( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copy', 'pluginect-analytics-for-woocommerce' ); ?></button>
				</div>

				<p class="cbaz-linklabel"><?php echo esc_html__( 'Complete UTM format, for advertising agencies', 'pluginect-analytics-for-woocommerce' ); ?></p>
				<div class="cbaz-copy">
					<input type="text" readonly value="<?php echo esc_attr( cbaz_campaign_url_long( $edit ) ); ?>">
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copy', 'pluginect-analytics-for-woocommerce' ); ?></button>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>
