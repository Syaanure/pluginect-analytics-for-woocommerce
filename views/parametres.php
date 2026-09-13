<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- vue incluse depuis cbaz_render_page() : variables locales à cette fonction, jamais globales
/** Paramètres, en quatre volets. */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ce fichier interroge les tables propres au plugin ({prefix}cbaz_*), pour lesquelles WordPress n'offre aucune API : les noms de tables viennent de cbaz_table(), les valeurs passent par $wpdb->prepare(), et les lectures lourdes sont consolidées par jour (history.php) plutôt que mises en cache objet.

global $wpdb;

$opts  = cbaz_opt();
$roles = get_editable_roles();
$t_sessions = cbaz_table( 'sessions' );
$t_views    = cbaz_table( 'views' );
$rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_sessions}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table préfixée par l'extension
$views = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_views}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table préfixée par l'extension
$saved = get_option( 'cbaz_saved_at' );

$volets_coeur = [
	'general' => __( 'General', 'pluginect-analytics-for-woocommerce' ),
	'rgpd'    => __( 'GDPR & privacy', 'pluginect-analytics-for-woocommerce' ),
	'exclus'  => __( 'Exclusions', 'pluginect-analytics-for-woocommerce' ),
	'donnees' => __( 'Data', 'pluginect-analytics-for-woocommerce' ),
];

/**
 * Volets de la page Paramètres.
 *
 * Permet à une extension d'ajouter son propre panneau — la licence du
 * module Pro, par exemple — sans créer un onglet de premier niveau pour
 * un réglage qui n'en est pas un.
 *
 * @param array $volets clé => intitulé.
 */
$volets = apply_filters( 'cbaz_settings_panels', $volets_coeur );

$volet = cbaz_subtabs( 'volet', $volets, 'general' );

// Un volet venu d'ailleurs a son propre formulaire : celui du cœur ne
// doit pas l'englober, sous peine d'imbriquer deux <form>.
$volet_externe = ! isset( $volets_coeur[ $volet ] );
?>

<?php if ( $volet_externe ) : ?>

	<?php
	/**
	 * Contenu d'un volet ajouté par une extension.
	 *
	 * @param string $volet Volet demandé.
	 */
	do_action( 'cbaz_settings_panel', $volet );
	?>

<?php else : ?>

<?php if ( ! empty( $_GET['ok'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple drapeau d'affichage après redirection ?>
	<div class="cbaz-notice cbaz-notice--ok"><?php echo esc_html__( 'Saved settings.', 'pluginect-analytics-for-woocommerce' ); ?></div>
<?php endif; ?>

<section class="cbaz-card">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cbaz_settings">
		<input type="hidden" name="volet" value="<?php echo esc_attr( $volet ); ?>">
		<?php wp_nonce_field( 'cbaz_settings' ); ?>

		<?php if ( 'general' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Enable tracking', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Records visits and purchase steps on the store.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'enabled', $opts['enabled'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Follow the purchasing steps', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Added to cart and started order — necessary for the conversion funnel.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'track_events', $opts['track_events'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Show leads worth checking', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Simple rules that point at what deserves a look once there are enough visits. They never replace an analysis of your store.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'insights', ! empty( $opts['insights'] ) ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Reporting currency', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'WooCommerce takeover: all amounts come from the store.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Time zone', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Determines the daily limits of the reports. Set in WordPress.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( wp_timezone_string() ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Duration of a visit', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Timeout of inactivity after which a new visit begins.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="30" disabled>
					<span class="cbaz-setting__unit"><?php echo esc_html__( 'minutes', 'pluginect-analytics-for-woocommerce' ); ?></span>
				</div>
			</div>

		<?php elseif ( 'rgpd' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Attribution window', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Period during which a campaign remains credited with a sale. It’s the setting that decides whether your campaigns appear profitable or sterile.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<select name="attribution_days">
						<option value="0" <?php selected( (int) $opts['attribution_days'], 0 ); ?>><?php echo esc_html__( 'None', 'pluginect-analytics-for-woocommerce' ); ?></option>
						<?php foreach ( [ 7, 30, 60, 90 ] as $d ) : ?>
							<option value="<?php echo (int) $d; ?>" <?php selected( (int) $opts['attribution_days'], $d ); ?>><?php /* translators: %1$d: attribution duration in days. */ printf( esc_html__( '%1$d days', 'pluginect-analytics-for-woocommerce' ), (int) $d ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Preservation of detail', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( "The visits one by one, their pages, their events. Thirteen months is the duration retained by\n\t\t\t\t\t\tthe CNIL for an audience measurement exempt from consent; beyond that, the exemption does not\n\t\t\t\t\t\tholds no longer and consent is required.", 'pluginect-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="retention_months">
						<?php foreach ( [ 3 => __( '3 months', 'pluginect-analytics-for-woocommerce' ), 6 => __( '6 months', 'pluginect-analytics-for-woocommerce' ), 13 => __( '13 months', 'pluginect-analytics-for-woocommerce' ), 24 => __( '2 years', 'pluginect-analytics-for-woocommerce' ), 36 => __( '3 years', 'pluginect-analytics-for-woocommerce' ), 60 => __( '5 years', 'pluginect-analytics-for-woocommerce' ), 120 => __( '10 years', 'pluginect-analytics-for-woocommerce' ) ] as $m => $texte ) : ?>
							<option value="<?php echo (int) $m; ?>" <?php selected( (int) $opts['retention_months'], $m ); ?>><?php echo esc_html( $texte ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Consolidated history', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( "A summary per day — visits, visitors, pages, orders, revenue, and their\n\t\t\t\t\t\tbreakdown by source, country and device. No individual visit, therefore no data\n\t\t\t\t\t\tpersonal: it survives the purge of details and compares itself from one year to the next.", 'pluginect-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="history_years">
						<?php foreach ( [ 3, 5, 10, 15, 20 ] as $a ) : ?>
							<option value="<?php echo (int) $a; ?>" <?php selected( (int) $opts['history_years'], $a ); ?>><?php /* translators: %1$d: history retention in years. */ printf( esc_html__( '%1$d years', 'pluginect-analytics-for-woocommerce' ), (int) $a ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php $poids = cbaz_history_weight(); ?>
			<p class="cbaz-note">
				<?php if ( $poids['days'] ) : ?>
					<?php if ( $poids['oldest'] ) : ?>
						<?php /* translators: 1: consolidated days, 2: oldest date, 3: total rows. */ printf( esc_html__( '%1$s days already consolidated from %2$s, or %3$s lines in total. At this rate, ten years of history weighs less than a week of detail.', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $poids['days'] ) ), esc_html( wp_date( 'j F Y', strtotime( $poids['oldest'] ) ) ), esc_html( cbaz_int( $poids['days'] + $poids['rows'] ) ) ); ?>
					<?php else : ?>
						<?php /* translators: 1: consolidated days, 2: total rows. */ printf( esc_html__( '%1$s days already consolidated, or %2$s lines in total. At this rate, ten years of history weighs less than a week of detail.', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $poids['days'] ) ), esc_html( cbaz_int( $poids['days'] + $poids['rows'] ) ) ); ?>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html__( 'The consolidation starts tonight, then catches up with the existing one in installments each time the administration opens. Nothing is erased until it has been summarized.', 'pluginect-analytics-for-woocommerce' ); ?>
				<?php endif; ?>
			</p>

			<p class="cbaz-cookiestate cbaz-cookiestate--<?php echo (int) $opts['attribution_days'] ? 'on' : 'off'; ?>">
				<?php if ( (int) $opts['attribution_days'] ) : ?>
					<?php /* translators: %1$d: attribution duration in days. */ printf( wp_kses_post( __( '<strong>An attribution cookie is set</strong> for %1$d days. It stores only the referral source, never an identifier. Select “none” to disable it.', 'pluginect-analytics-for-woocommerce' ) ), (int) $opts['attribution_days'] ); ?>
				<?php else : ?>
					<?php echo wp_kses_post( __( '<strong>No cookies are placed.</strong> The plugin does not write anything in visitors\' browsers: neither cookies, nor local storage, nor session storage.', 'pluginect-analytics-for-woocommerce' ) ); ?>
				<?php endif; ?>
			</p>

			<ul class="cbaz-facts">
				<li><?php echo wp_kses_post( __( '<strong>The IP address is never recorded.</strong> It is used to calculate a fingerprint, then it is forgotten.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( "<strong>It is even truncated before this calculation</strong> — only the network is retained, never the address\n\t\t\t\t\tcomplete. A fingerprint can therefore no longer be recalculated to find someone's visits\n\t\t\t\t\twhose address we would know: it designates an entire network, not a person.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>The fingerprint changes every day</strong>: impossible to follow someone over time.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>Nothing comes out of your server.</strong> No third-party service, no transfer outside the European Union.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>No profiling</strong>, no cross-checking between sites.', 'pluginect-analytics-for-woocommerce' ) ); ?></li>
			</ul>

			<p class="cbaz-note">
				<?php echo esc_html__( "These points are the conditions set by the CNIL so that an audience measurement is exempt from consent.\n\t\t\t\tNo banner to display for this extension - which does not exempt you from having one for other trackers,\n\t\t\t\tnor mention the measure in your privacy policy.", 'pluginect-analytics-for-woocommerce' ); ?>
			</p>

			<div class="cbaz-legal">
				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'What remains your responsibility', 'pluginect-analytics-for-woocommerce' ); ?></h3>

				<p><?php echo wp_kses_post( __( "The extension measures; it does not put anyone in their place. By installing this plugin,\n\t\t\t\t\tyou are the <strong>data controller</strong> within the meaning of the GDPR, and the obligations\n\t\t\t\t\tfollowing are your responsibility.", 'pluginect-analytics-for-woocommerce' ) ); ?></p>

				<ol class="cbaz-legal__list">
					<li><?php echo wp_kses_post( __( "<strong>Informer.</strong> Mention audience measurement in your policy\n\t\t\t\t\t\tconfidentiality: what is collected, why, for how long, and who to contact.\n\t\t\t\t\t\tThe information obligation does not depend on cookies — it applies as soon as there is\n\t\t\t\t\t\ttreatment.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( "<strong>Supporting a legal basis.</strong> Legitimate interest is appropriate for a measure\n\t\t\t\t\t\tinternal audience. If you activate the attribution memory, a cookie is placed and the\n\t\t\t\t\t\tquestion of consent arises again.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( "<strong>Keep a register.</strong> One line in your treatment register is enough\n\t\t\t\t\t\tfor a store of this size, but it must exist.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( "<strong>Respond to requests.</strong> Right of access, rectification, erasure,\n\t\t\t\t\t\topposition. Anonymous visits are not subject to it; those attached to a\n\t\t\t\t\t\torder, yes.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( "<strong>Secure your hosting.</strong> The data lives in your WordPress database:\n\t\t\t\t\t\tbackups, passwords, updates, access to the database and the server are your responsibility\n\t\t\t\t\t\talone.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( "<strong>Notify a violation.</strong> Any data leak must be reported to the\n\t\t\t\t\t\tCNIL within 72 hours, and to the people concerned if the risk is high.", 'pluginect-analytics-for-woocommerce' ) ); ?></li>
				</ol>

				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'Text to include in your privacy policy', 'pluginect-analytics-for-woocommerce' ); ?></h3>

				<p>
					<?php echo esc_html__( "The bare minimum, written according to the actual configuration of this installation.\n\t\t\t\t\tPaste as is, replacing the contact address.", 'pluginect-analytics-for-woocommerce' ); ?>
				</p>

				<?php
				$mois = max( 1, (int) $opts['retention_months'] );
				if ( $mois >= 24 && 0 === $mois % 12 ) {
					$annees = (int) ( $mois / 12 );
					/* translators: %1$d: retention duration in years. */
					$duree = sprintf( _n( '%1$d year', '%1$d years', $annees, 'pluginect-analytics-for-woocommerce' ), $annees );
				} else {
					/* translators: %1$d: retention duration in months. */
					$duree = sprintf( _n( '%1$d month', '%1$d months', $mois, 'pluginect-analytics-for-woocommerce' ), $mois );
				}
				if ( (int) $opts['attribution_days'] ) {
					/* translators: %1$d: attribution duration in days. */
					$cookie = sprintf( __( 'An attribution cookie is set for %1$d days. It contains no identifier and is used only to link an order to the preceding campaign.', 'pluginect-analytics-for-woocommerce' ), (int) $opts['attribution_days'] );
				} else {
					$cookie = __( 'No cookies are placed, and no information is stored in your browser.', 'pluginect-analytics-for-woocommerce' );
				}
				/* translators: 1: cookie policy according to settings, 2: data retention duration. */
				$texte = sprintf( __( "Audience measurement\n\nThis site measures its traffic using a solution hosted on its own server. The following are recorded: the pages viewed, the origin of the visit, the type of device, the browser, the language and the country. None of this information is transmitted to a third party.\n\n%1\$s\n\nYour IP address is never stored. It is truncated then transformed into a non-reversible technical identifier, renewed every night, which does not allow you to be identified or recognized from one day to the next.\n\nWhen a visit results in an order, it is linked to it in order to measure the effectiveness of commercial operations.\n\nDetailed data is kept %2\$s and then replaced with aggregated statistics that do not relate to any specific person.\n\nLegal basis: legitimate interest (audience measurement and site improvement).\n\nYou can exercise your rights of access, rectification, erasure and opposition by writing to: [your email address].", 'pluginect-analytics-for-woocommerce' ), $cookie, $duree );
				?>

				<div class="cbaz-copyblock">
					<textarea readonly rows="12"><?php echo esc_textarea( $texte ); ?></textarea>
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copy', 'pluginect-analytics-for-woocommerce' ); ?></button>
				</div>

				<p class="cbaz-legal__hint">
					<?php echo esc_html__( "This text follows your settings: the storage period and the mention of the cookie are adjusted\n\t\t\t\t\tall alone. If you change them, come back and copy it.", 'pluginect-analytics-for-woocommerce' ); ?>
				</p>

				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'Limit of liability of the publisher', 'pluginect-analytics-for-woocommerce' ); ?></h3>

				<p><?php echo wp_kses_post( __( "This extension works <strong>entirely on your server</strong>. No data is\n\t\t\t\t\ttransmitted to its publisher, who has no access to it and therefore acts neither as a subcontractor nor as\n\t\t\t\t\trecipient within the meaning of the GDPR.", 'pluginect-analytics-for-woocommerce' ) ); ?></p>

				<p class="cbaz-legal__foot">
					<?php echo esc_html__( "This text describes how the software works and reminds you of common obligations.\n\t\t\t\t\tIt does not constitute legal advice. When in doubt, especially if you are processing data\n\t\t\t\t\tsensitive or if you export data outside the European Union, consult a professional\n\t\t\t\t\tright.", 'pluginect-analytics-for-woocommerce' ); ?>
				</p>
			</div>

		<?php elseif ( 'exclus' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Ignore bots', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( "Content vacuums, monitoring probes, link preview generators:\n\t\t\t\t\t\tthey pass, they never order, and they lower the conversion rate.\n\t\t\t\t\t\tTheir visits are neither measured nor displayed.", 'pluginect-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'exclude_bots', $opts['exclude_bots'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Excluded roles', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'A day of development on the store would swell the product pages without the slightest sale.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
			</div>

			<fieldset class="cbaz-fieldset">
				<?php foreach ( $roles as $key => $role ) : ?>
					<label class="cbaz-check">
						<input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $opts['exclude_roles'], true ) ); ?>>
						<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<label class="cbaz-label"><?php echo esc_html__( 'Excluded paths — one per line, start of address', 'pluginect-analytics-for-woocommerce' ); ?></label>
			<textarea name="exclude_paths" rows="5" class="cbaz-textarea"><?php echo esc_textarea( $opts['exclude_paths'] ); ?></textarea>

		<?php else : ?>
			<div class="cbaz-setting">
				<?php cbaz_toggle( 'delete_on_uninstall', ! empty( $opts['delete_on_uninstall'] ) ); ?>
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Permanently erase data on uninstallation', 'pluginect-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Disabled by default. Enable this choice before removing the extension if you want to delete its attribution tables, settings, and metadata.', 'pluginect-analytics-for-woocommerce' ); ?></p>
				</div>
			</div>

			<dl class="cbaz-stats">
				<div><dt><?php echo esc_html__( 'Recorded visits', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $rows ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Page views', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $views ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Order storage', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_hpos() ? __( 'WooCommerce Tables', 'pluginect-analytics-for-woocommerce' ) : __( 'WordPress Posts', 'pluginect-analytics-for-woocommerce' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Automatic purge', 'pluginect-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( wp_next_scheduled( 'cbaz_daily_purge' ) ? __( 'Scheduled', 'pluginect-analytics-for-woocommerce' ) : __( 'Not scheduled', 'pluginect-analytics-for-woocommerce' ) ); ?></dd></div>
			</dl>

			<p class="cbaz-note">
				<?php echo esc_html__( "The purge runs once a night and clears visits beyond the retention period.\n\t\t\t\tIf WordPress scheduled tasks are disabled, it will not run.", 'pluginect-analytics-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<p class="cbaz-form__actions">
			<button type="submit" class="cbaz-btn"><?php echo esc_html__( 'Save changes', 'pluginect-analytics-for-woocommerce' ); ?></button>
			<?php if ( $saved ) : ?>
				<span class="cbaz-saved"><?php /* translators: %1$s: last settings save date. */ printf( esc_html__( 'Last save: %1$s', 'pluginect-analytics-for-woocommerce' ), esc_html( wp_date(
					/* translators: date and time format of the last save, see https://www.php.net/manual/datetime.format.php — escape any literal letter with a backslash. */
					__( 'j M Y \\a\\t H:i', 'pluginect-analytics-for-woocommerce' ),
					(int) $saved
				) ) ); ?></span>
			<?php endif; ?>
		</p>
	</form>
</section>

<?php endif; ?>
