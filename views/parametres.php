<?php
/** Paramètres, en quatre volets. */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$opts  = cbaz_opt();
$roles = get_editable_roles();
$rows  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cbaz_table( 'sessions' ) );
$views = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cbaz_table( 'views' ) );
$saved = get_option( 'cbaz_saved_at' );

$volets_coeur = [
	'general' => __( 'Général', 'shop-analytics-for-woocommerce' ),
	'rgpd'    => __( 'RGPD & confidentialité', 'shop-analytics-for-woocommerce' ),
	'exclus'  => __( 'Exclusions', 'shop-analytics-for-woocommerce' ),
	'donnees' => __( 'Données', 'shop-analytics-for-woocommerce' ),
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

<?php if ( ! empty( $_GET['ok'] ) ) : ?>
	<div class="cbaz-notice cbaz-notice--ok"><?php echo esc_html__( 'Réglages enregistrés.', 'shop-analytics-for-woocommerce' ); ?></div>
<?php endif; ?>

<section class="cbaz-card">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cbaz_settings">
		<input type="hidden" name="volet" value="<?php echo esc_attr( $volet ); ?>">
		<?php wp_nonce_field( 'cbaz_settings' ); ?>

		<?php if ( 'general' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Activer le suivi', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Enregistre les visites et les étapes d’achat sur la boutique.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'enabled', $opts['enabled'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Suivre les étapes d’achat', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Ajout au panier et commande entamée — nécessaire à l’entonnoir de conversion.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'track_events', $opts['track_events'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Devise des rapports', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Reprise de WooCommerce : tous les montants viennent de la boutique.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Fuseau horaire', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Détermine les bornes journalières des rapports. Réglé dans WordPress.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( wp_timezone_string() ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Durée d’une visite', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Délai d’inactivité au-delà duquel une nouvelle visite commence.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="30" disabled>
					<span class="cbaz-setting__unit"><?php echo esc_html__( 'minutes', 'shop-analytics-for-woocommerce' ); ?></span>
				</div>
			</div>

		<?php elseif ( 'rgpd' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Fenêtre d’attribution', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Période pendant laquelle une campagne reste créditée d’une vente. C’est le réglage qui décide si tes campagnes paraissent rentables ou stériles.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
				<div class="cbaz-setting__field">
					<select name="attribution_days">
						<option value="0" <?php selected( (int) $opts['attribution_days'], 0 ); ?>><?php echo esc_html__( 'Aucune', 'shop-analytics-for-woocommerce' ); ?></option>
						<?php foreach ( [ 7, 30, 60, 90 ] as $d ) : ?>
							<option value="<?php echo $d; ?>" <?php selected( (int) $opts['attribution_days'], $d ); ?>><?php /* translators: %1$d: attribution duration in days. */ printf( esc_html__( '%1$d jours', 'shop-analytics-for-woocommerce' ), $d ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Conservation du détail', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( 'Les visites une à une, leurs pages, leurs évènements. Treize mois est la durée retenue par
						la CNIL pour une mesure d’audience dispensée de consentement ; au-delà, l’exemption ne
						tient plus et il faut un consentement.', 'shop-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="retention_months">
						<?php foreach ( [ 3 => __( '3 mois', 'shop-analytics-for-woocommerce' ), 6 => __( '6 mois', 'shop-analytics-for-woocommerce' ), 13 => __( '13 mois', 'shop-analytics-for-woocommerce' ), 24 => __( '2 ans', 'shop-analytics-for-woocommerce' ), 36 => __( '3 ans', 'shop-analytics-for-woocommerce' ), 60 => __( '5 ans', 'shop-analytics-for-woocommerce' ), 120 => __( '10 ans', 'shop-analytics-for-woocommerce' ) ] as $m => $texte ) : ?>
							<option value="<?php echo (int) $m; ?>" <?php selected( (int) $opts['retention_months'], $m ); ?>><?php echo esc_html( $texte ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Historique consolidé', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( 'Un résumé par jour — visites, visiteurs, pages, commandes, chiffre d’affaires, et leur
						répartition par source, pays et appareil. Aucune visite individuelle, donc aucune donnée
						personnelle : il survit à la purge du détail et se compare d’une année sur l’autre.', 'shop-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="history_years">
						<?php foreach ( [ 3, 5, 10, 15, 20 ] as $a ) : ?>
							<option value="<?php echo (int) $a; ?>" <?php selected( (int) $opts['history_years'], $a ); ?>><?php /* translators: %1$d: history retention in years. */ printf( esc_html__( '%1$d ans', 'shop-analytics-for-woocommerce' ), (int) $a ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php $poids = cbaz_history_weight(); ?>
			<p class="cbaz-note">
				<?php if ( $poids['days'] ) : ?>
					<?php if ( $poids['oldest'] ) : ?>
						<?php /* translators: 1: consolidated days, 2: oldest date, 3: total rows. */ printf( esc_html__( '%1$s journées déjà consolidées depuis le %2$s, soit %3$s lignes en tout. À ce rythme, dix ans d’historique pèsent moins qu’une semaine de détail.', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $poids['days'] ) ), esc_html( wp_date( 'j F Y', strtotime( $poids['oldest'] ) ) ), esc_html( cbaz_int( $poids['days'] + $poids['rows'] ) ) ); ?>
					<?php else : ?>
						<?php /* translators: 1: consolidated days, 2: total rows. */ printf( esc_html__( '%1$s journées déjà consolidées, soit %2$s lignes en tout. À ce rythme, dix ans d’historique pèsent moins qu’une semaine de détail.', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $poids['days'] ) ), esc_html( cbaz_int( $poids['days'] + $poids['rows'] ) ) ); ?>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html__( 'La consolidation démarre cette nuit, puis rattrape l’existant par tranches à chaque ouverture de l’administration. Rien n’est effacé tant qu’il n’a pas été résumé.', 'shop-analytics-for-woocommerce' ); ?>
				<?php endif; ?>
			</p>

			<p class="cbaz-cookiestate cbaz-cookiestate--<?php echo (int) $opts['attribution_days'] ? 'on' : 'off'; ?>">
				<?php if ( (int) $opts['attribution_days'] ) : ?>
					<?php /* translators: %1$d: attribution duration in days. */ printf( wp_kses_post( __( '<strong>Un cookie est déposé</strong> — la mémoire d’attribution, réglée sur %1$d jours. Il ne contient que la provenance, jamais d’identifiant. Règle-la sur « aucune » pour n’en déposer aucun.', 'shop-analytics-for-woocommerce' ) ), (int) $opts['attribution_days'] ); ?>
				<?php else : ?>
					<?php echo wp_kses_post( __( '<strong>Aucun cookie n’est déposé.</strong> Le plugin n’écrit rien dans le navigateur des visiteurs : ni cookie, ni stockage local, ni stockage de session.', 'shop-analytics-for-woocommerce' ) ); ?>
				<?php endif; ?>
			</p>

			<ul class="cbaz-facts">
				<li><?php echo wp_kses_post( __( '<strong>L’adresse IP n’est jamais enregistrée.</strong> Elle sert à calculer une empreinte, puis elle est oubliée.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>Elle est même tronquée avant ce calcul</strong> — seul le réseau est retenu, jamais l’adresse
					complète. Une empreinte ne peut donc plus être recalculée pour retrouver les visites de quelqu’un
					dont on connaîtrait l’adresse : elle désigne un réseau entier, pas une personne.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>L’empreinte change chaque jour</strong> : impossible de suivre quelqu’un dans le temps.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>Rien ne sort de ton serveur.</strong> Pas de service tiers, pas de transfert hors Union européenne.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>Aucun profilage</strong>, aucun recoupement entre sites.', 'shop-analytics-for-woocommerce' ) ); ?></li>
			</ul>

			<p class="cbaz-note">
				<?php echo esc_html__( 'Ces points sont les conditions posées par la CNIL pour qu’une mesure d’audience soit dispensée de consentement.
				Pas de bandeau à afficher pour cette extension — ce qui ne dispense ni d’en avoir un pour les autres traceurs,
				ni de mentionner la mesure dans ta politique de confidentialité.', 'shop-analytics-for-woocommerce' ); ?>
			</p>

			<div class="cbaz-legal">
				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'Ce qui reste à ta charge', 'shop-analytics-for-woocommerce' ); ?></h3>

				<p><?php echo wp_kses_post( __( 'L’extension mesure ; elle ne met personne en conformité à sa place. En installant ce plugin,
					tu es le <strong>responsable de traitement</strong> au sens du RGPD, et les obligations
					suivantes t’incombent.', 'shop-analytics-for-woocommerce' ) ); ?></p>

				<ol class="cbaz-legal__list">
					<li><?php echo wp_kses_post( __( '<strong>Informer.</strong> Mentionner la mesure d’audience dans ta politique de
						confidentialité : ce qui est collecté, pourquoi, combien de temps, et à qui s’adresser.
						L’obligation d’information ne dépend pas des cookies — elle s’applique dès qu’il y a
						traitement.', 'shop-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( '<strong>Justifier d’une base légale.</strong> L’intérêt légitime convient à une mesure
						d’audience interne. Si tu actives la mémoire d’attribution, un cookie est déposé et la
						question du consentement se pose à nouveau.', 'shop-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( '<strong>Tenir un registre.</strong> Une ligne dans ton registre des traitements suffit
						pour une boutique de cette taille, mais elle doit exister.', 'shop-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( '<strong>Répondre aux demandes.</strong> Droit d’accès, de rectification, d’effacement,
						d’opposition. Les visites anonymes n’y sont pas soumises ; celles rattachées à une
						commande, si.', 'shop-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( '<strong>Sécuriser ton hébergement.</strong> Les données vivent dans ta base WordPress :
						sauvegardes, mots de passe, mises à jour, accès à la base et au serveur relèvent de toi
						seul.', 'shop-analytics-for-woocommerce' ) ); ?></li>
					<li><?php echo wp_kses_post( __( '<strong>Notifier une violation.</strong> Toute fuite de données doit être signalée à la
						CNIL sous 72 heures, et aux personnes concernées si le risque est élevé.', 'shop-analytics-for-woocommerce' ) ); ?></li>
				</ol>

				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'Texte à reprendre dans ta politique de confidentialité', 'shop-analytics-for-woocommerce' ); ?></h3>

				<p>
					<?php echo esc_html__( 'Le strict minimum, rédigé d’après la configuration réelle de cette installation.
					À coller tel quel, en remplaçant l’adresse de contact.', 'shop-analytics-for-woocommerce' ); ?>
				</p>

				<?php
				$mois = max( 1, (int) $opts['retention_months'] );
				if ( $mois >= 24 && 0 === $mois % 12 ) {
					$annees = (int) ( $mois / 12 );
					/* translators: %1$d: retention duration in years. */
					$duree = sprintf( _n( '%1$d an', '%1$d ans', $annees, 'shop-analytics-for-woocommerce' ), $annees );
				} else {
					/* translators: %1$d: retention duration in months. */
					$duree = sprintf( _n( '%1$d mois', '%1$d mois', $mois, 'shop-analytics-for-woocommerce' ), $mois );
				}
				if ( (int) $opts['attribution_days'] ) {
					/* translators: %1$d: attribution duration in days. */
					$cookie = sprintf( __( 'Un cookie de provenance est déposé pendant %1$d jours ; il ne contient aucun identifiant et sert uniquement à rattacher une commande à la campagne qui l’a précédée.', 'shop-analytics-for-woocommerce' ), (int) $opts['attribution_days'] );
				} else {
					$cookie = __( 'Aucun cookie n’est déposé, et aucune information n’est stockée dans votre navigateur.', 'shop-analytics-for-woocommerce' );
				}
				/* translators: 1: cookie policy according to settings, 2: data retention duration. */
				$texte = sprintf( __( "Mesure d’audience\n\nCe site mesure sa fréquentation à l’aide d’une solution hébergée sur son propre serveur. Sont enregistrés : les pages consultées, la provenance de la visite, le type d’appareil, le navigateur, la langue et le pays. Aucune de ces informations n’est transmise à un tiers.\n\n%1\$s\n\nVotre adresse IP n’est jamais conservée. Elle est tronquée puis transformée en un identifiant technique non réversible, renouvelé chaque nuit, qui ne permet ni de vous identifier, ni de vous reconnaître d’un jour à l’autre.\n\nLorsqu’une visite aboutit à une commande, elle est rattachée à celle-ci afin de mesurer l’efficacité des opérations commerciales.\n\nLes données détaillées sont conservées %2\$s, puis remplacées par des statistiques agrégées qui ne se rapportent à aucune personne en particulier.\n\nBase légale : intérêt légitime (mesure d’audience et amélioration du site).\n\nVous pouvez exercer vos droits d’accès, de rectification, d’effacement et d’opposition en écrivant à : [votre adresse e-mail].", 'shop-analytics-for-woocommerce' ), $cookie, $duree );
				?>

				<div class="cbaz-copyblock">
					<textarea readonly rows="12"><?php echo esc_textarea( $texte ); ?></textarea>
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy><?php echo esc_html__( 'Copier', 'shop-analytics-for-woocommerce' ); ?></button>
				</div>

				<p class="cbaz-legal__hint">
					<?php echo esc_html__( 'Ce texte suit tes réglages : la durée de conservation et la mention du cookie s’ajustent
					toutes seules. Si tu les modifies, reviens le copier.', 'shop-analytics-for-woocommerce' ); ?>
				</p>

				<h3 class="cbaz-legal__title"><?php echo esc_html__( 'Limite de responsabilité de l’éditeur', 'shop-analytics-for-woocommerce' ); ?></h3>

				<p><?php echo wp_kses_post( __( 'Cette extension fonctionne <strong>entièrement sur ton serveur</strong>. Aucune donnée n’est
					transmise à son éditeur, qui n’y a aucun accès et n’agit donc ni comme sous-traitant ni comme
					destinataire au sens du RGPD.', 'shop-analytics-for-woocommerce' ) ); ?></p>

				<p class="cbaz-legal__foot">
					<?php echo esc_html__( 'Ce texte décrit le fonctionnement du logiciel et rappelle des obligations courantes.
					Il ne constitue pas un avis juridique. En cas de doute, notamment si tu traites des données
					sensibles ou si tu exportes des données hors Union européenne, consulte un professionnel du
					droit.', 'shop-analytics-for-woocommerce' ); ?>
				</p>
			</div>

		<?php elseif ( 'exclus' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Ignorer les robots', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p>
						<?php echo esc_html__( 'Aspirateurs de contenu, sondes de supervision, générateurs d’aperçus de lien :
						ils passent, ils ne commandent jamais, et ils font baisser le taux de conversion.
						Leurs visites ne sont ni mesurées ni affichées.', 'shop-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'exclude_bots', $opts['exclude_bots'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Rôles exclus', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Une journée de mise au point sur la boutique gonflerait les pages produits sans la moindre vente.', 'shop-analytics-for-woocommerce' ); ?></p>
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

			<label class="cbaz-label"><?php echo esc_html__( 'Chemins exclus — un par ligne, début d’adresse', 'shop-analytics-for-woocommerce' ); ?></label>
			<textarea name="exclude_paths" rows="5" class="cbaz-textarea"><?php echo esc_textarea( $opts['exclude_paths'] ); ?></textarea>

		<?php else : ?>
			<div class="cbaz-setting">
				<?php cbaz_toggle( 'delete_on_uninstall', ! empty( $opts['delete_on_uninstall'] ) ); ?>
				<div class="cbaz-setting__text">
					<strong><?php echo esc_html__( 'Effacer définitivement les données à la désinstallation', 'shop-analytics-for-woocommerce' ); ?></strong>
					<p><?php echo esc_html__( 'Désactivé par défaut. Activez ce choix avant de supprimer l’extension si vous souhaitez supprimer ses tables, réglages et métadonnées d’attribution.', 'shop-analytics-for-woocommerce' ); ?></p>
				</div>
			</div>

			<dl class="cbaz-stats">
				<div><dt><?php echo esc_html__( 'Visites enregistrées', 'shop-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $rows ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Pages vues', 'shop-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_int( $views ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Stockage des commandes', 'shop-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( cbaz_hpos() ? __( 'Tables WooCommerce', 'shop-analytics-for-woocommerce' ) : __( 'Articles WordPress', 'shop-analytics-for-woocommerce' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Purge automatique', 'shop-analytics-for-woocommerce' ); ?></dt><dd><?php echo esc_html( wp_next_scheduled( 'cbaz_daily_purge' ) ? __( 'Programmée', 'shop-analytics-for-woocommerce' ) : __( 'Non programmée', 'shop-analytics-for-woocommerce' ) ); ?></dd></div>
			</dl>

			<p class="cbaz-note">
				<?php echo esc_html__( 'La purge tourne une fois par nuit et efface les visites au-delà de la durée de conservation.
				Si les tâches planifiées de WordPress sont désactivées, elle ne s’exécutera pas.', 'shop-analytics-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<p class="cbaz-form__actions">
			<button type="submit" class="cbaz-btn"><?php echo esc_html__( 'Enregistrer les modifications', 'shop-analytics-for-woocommerce' ); ?></button>
			<?php if ( $saved ) : ?>
				<span class="cbaz-saved"><?php /* translators: %1$s: last settings save date. */ printf( esc_html__( 'Dernière sauvegarde : %1$s', 'shop-analytics-for-woocommerce' ), esc_html( wp_date( "j M Y 'à' H:i", (int) $saved ) ) ); ?></span>
			<?php endif; ?>
		</p>
	</form>
</section>

<?php endif; ?>
