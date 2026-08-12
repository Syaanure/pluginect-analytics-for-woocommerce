<?php
/** Paramètres, en quatre volets. */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$opts  = cbaz_opt();
$roles = get_editable_roles();
$rows  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cbaz_table( 'sessions' ) );
$views = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cbaz_table( 'views' ) );
$saved = get_option( 'cbaz_saved_at' );

$volet = cbaz_subtabs( 'volet', [
	'general' => 'Général',
	'rgpd'    => 'RGPD & confidentialité',
	'exclus'  => 'Exclusions',
	'donnees' => 'Données',
], 'general' );
?>

<?php if ( ! empty( $_GET['ok'] ) ) : ?>
	<div class="cbaz-notice cbaz-notice--ok">Réglages enregistrés.</div>
<?php endif; ?>

<section class="cbaz-card">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cbaz_settings">
		<input type="hidden" name="volet" value="<?php echo esc_attr( $volet ); ?>">
		<?php wp_nonce_field( 'cbaz_settings' ); ?>

		<?php if ( 'general' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Activer le suivi</strong>
					<p>Enregistre les visites et les étapes d’achat sur la boutique.</p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'enabled', $opts['enabled'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Suivre les étapes d’achat</strong>
					<p>Ajout au panier et commande entamée — nécessaire à l’entonnoir de conversion.</p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'track_events', $opts['track_events'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Devise des rapports</strong>
					<p>Reprise de WooCommerce : tous les montants viennent de la boutique.</p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Fuseau horaire</strong>
					<p>Détermine les bornes journalières des rapports. Réglé dans WordPress.</p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="<?php echo esc_attr( wp_timezone_string() ); ?>" disabled>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Durée d’une visite</strong>
					<p>Délai d’inactivité au-delà duquel une nouvelle visite commence.</p>
				</div>
				<div class="cbaz-setting__field">
					<input type="text" value="30" disabled>
					<span class="cbaz-setting__unit">minutes</span>
				</div>
			</div>

		<?php elseif ( 'rgpd' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Fenêtre d’attribution</strong>
					<p>Période pendant laquelle une campagne reste créditée d’une vente. C’est le réglage qui décide si tes campagnes paraissent rentables ou stériles.</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="attribution_days">
						<option value="0" <?php selected( (int) $opts['attribution_days'], 0 ); ?>>Aucune</option>
						<?php foreach ( [ 7, 30, 60, 90 ] as $d ) : ?>
							<option value="<?php echo $d; ?>" <?php selected( (int) $opts['attribution_days'], $d ); ?>><?php echo $d; ?> jours</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Conservation du détail</strong>
					<p>
						Les visites une à une, leurs pages, leurs évènements. Treize mois est la durée retenue par
						la CNIL pour une mesure d’audience dispensée de consentement ; au-delà, l’exemption ne
						tient plus et il faut un consentement.
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="retention_months">
						<?php foreach ( [ 3 => '3 mois', 6 => '6 mois', 13 => '13 mois', 24 => '2 ans', 36 => '3 ans', 60 => '5 ans', 120 => '10 ans' ] as $m => $texte ) : ?>
							<option value="<?php echo (int) $m; ?>" <?php selected( (int) $opts['retention_months'], $m ); ?>><?php echo esc_html( $texte ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Historique consolidé</strong>
					<p>
						Un résumé par jour — visites, visiteurs, pages, commandes, chiffre d’affaires, et leur
						répartition par source, pays et appareil. Aucune visite individuelle, donc aucune donnée
						personnelle : il survit à la purge du détail et se compare d’une année sur l’autre.
					</p>
				</div>
				<div class="cbaz-setting__field">
					<select name="history_years">
						<?php foreach ( [ 3, 5, 10, 15, 20 ] as $a ) : ?>
							<option value="<?php echo (int) $a; ?>" <?php selected( (int) $opts['history_years'], $a ); ?>><?php echo (int) $a; ?> ans</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php $poids = cbaz_history_weight(); ?>
			<p class="cbaz-note">
				<?php if ( $poids['days'] ) : ?>
					<?php echo esc_html( cbaz_int( $poids['days'] ) ); ?> journées déjà consolidées
					<?php if ( $poids['oldest'] ) : ?>depuis le <?php echo esc_html( wp_date( 'j F Y', strtotime( $poids['oldest'] ) ) ); ?><?php endif; ?>,
					soit <?php echo esc_html( cbaz_int( $poids['days'] + $poids['rows'] ) ); ?> lignes en tout.
					À ce rythme, dix ans d’historique pèsent moins qu’une semaine de détail.
				<?php else : ?>
					La consolidation démarre cette nuit, puis rattrape l’existant par tranches à chaque ouverture
					de l’administration. Rien n’est effacé tant qu’il n’a pas été résumé.
				<?php endif; ?>
			</p>

			<p class="cbaz-cookiestate cbaz-cookiestate--<?php echo (int) $opts['attribution_days'] ? 'on' : 'off'; ?>">
				<?php if ( (int) $opts['attribution_days'] ) : ?>
					<strong>Un cookie est déposé</strong> — la mémoire d’attribution, réglée sur <?php echo (int) $opts['attribution_days']; ?> jours.
					Il ne contient que la provenance, jamais d’identifiant. Règle-la sur « aucune » pour n’en déposer aucun.
				<?php else : ?>
					<strong>Aucun cookie n’est déposé.</strong> Le plugin n’écrit rien dans le navigateur des visiteurs :
					ni cookie, ni stockage local, ni stockage de session.
				<?php endif; ?>
			</p>

			<ul class="cbaz-facts">
				<li><strong>L’adresse IP n’est jamais enregistrée.</strong> Elle sert à calculer une empreinte, puis elle est oubliée.</li>
				<li>
					<strong>Elle est même tronquée avant ce calcul</strong> — seul le réseau est retenu, jamais l’adresse
					complète. Une empreinte ne peut donc plus être recalculée pour retrouver les visites de quelqu’un
					dont on connaîtrait l’adresse : elle désigne un réseau entier, pas une personne.
				</li>
				<li><strong>L’empreinte change chaque jour</strong> : impossible de suivre quelqu’un dans le temps.</li>
				<li><strong>Rien ne sort de ton serveur.</strong> Pas de service tiers, pas de transfert hors Union européenne.</li>
				<li><strong>Aucun profilage</strong>, aucun recoupement entre sites.</li>
			</ul>

			<p class="cbaz-note">
				Ces points sont les conditions posées par la CNIL pour qu’une mesure d’audience soit dispensée de consentement.
				Pas de bandeau à afficher pour cette extension — ce qui ne dispense ni d’en avoir un pour les autres traceurs,
				ni de mentionner la mesure dans ta politique de confidentialité.
			</p>

			<div class="cbaz-legal">
				<h3 class="cbaz-legal__title">Ce qui reste à ta charge</h3>

				<p>
					L’extension mesure ; elle ne met personne en conformité à sa place. En installant ce plugin,
					tu es le <strong>responsable de traitement</strong> au sens du RGPD, et les obligations
					suivantes t’incombent.
				</p>

				<ol class="cbaz-legal__list">
					<li>
						<strong>Informer.</strong> Mentionner la mesure d’audience dans ta politique de
						confidentialité : ce qui est collecté, pourquoi, combien de temps, et à qui s’adresser.
						L’obligation d’information ne dépend pas des cookies — elle s’applique dès qu’il y a
						traitement.
					</li>
					<li>
						<strong>Justifier d’une base légale.</strong> L’intérêt légitime convient à une mesure
						d’audience interne. Si tu actives la mémoire d’attribution, un cookie est déposé et la
						question du consentement se pose à nouveau.
					</li>
					<li>
						<strong>Tenir un registre.</strong> Une ligne dans ton registre des traitements suffit
						pour une boutique de cette taille, mais elle doit exister.
					</li>
					<li>
						<strong>Répondre aux demandes.</strong> Droit d’accès, de rectification, d’effacement,
						d’opposition. Les visites anonymes n’y sont pas soumises ; celles rattachées à une
						commande, si.
					</li>
					<li>
						<strong>Sécuriser ton hébergement.</strong> Les données vivent dans ta base WordPress :
						sauvegardes, mots de passe, mises à jour, accès à la base et au serveur relèvent de toi
						seul.
					</li>
					<li>
						<strong>Notifier une violation.</strong> Toute fuite de données doit être signalée à la
						CNIL sous 72 heures, et aux personnes concernées si le risque est élevé.
					</li>
				</ol>

				<h3 class="cbaz-legal__title">Texte à reprendre dans ta politique de confidentialité</h3>

				<p>
					Le strict minimum, rédigé d’après la configuration réelle de cette installation.
					À coller tel quel, en remplaçant l’adresse de contact.
				</p>

				<?php
				$mois   = max( 1, (int) $opts['retention_months'] );
				$duree  = $mois >= 24 && 0 === $mois % 12 ? ( $mois / 12 ) . ' ans' : $mois . ' mois';
				$cookie = (int) $opts['attribution_days']
					? 'Un cookie de provenance est déposé pendant ' . (int) $opts['attribution_days'] . ' jours ; il ne contient aucun identifiant et sert uniquement à rattacher une commande à la campagne qui l’a précédée.'
					: 'Aucun cookie n’est déposé, et aucune information n’est stockée dans votre navigateur.';

				$texte = "Mesure d’audience\n\n"
					. "Ce site mesure sa fréquentation à l’aide d’une solution hébergée sur son propre serveur. "
					. "Sont enregistrés : les pages consultées, la provenance de la visite, le type d’appareil, "
					. "le navigateur, la langue et le pays. Aucune de ces informations n’est transmise à un tiers.\n\n"
					. $cookie . "\n\n"
					. "Votre adresse IP n’est jamais conservée. Elle est tronquée puis transformée en un identifiant "
					. "technique non réversible, renouvelé chaque nuit, qui ne permet ni de vous identifier, ni de "
					. "vous reconnaître d’un jour à l’autre.\n\n"
					. "Lorsqu’une visite aboutit à une commande, elle est rattachée à celle-ci afin de mesurer "
					. "l’efficacité des opérations commerciales.\n\n"
					. "Les données détaillées sont conservées {$duree}, puis remplacées par des statistiques "
					. "agrégées qui ne se rapportent à aucune personne en particulier.\n\n"
					. "Base légale : intérêt légitime (mesure d’audience et amélioration du site).\n\n"
					. "Vous pouvez exercer vos droits d’accès, de rectification, d’effacement et d’opposition en "
					. "écrivant à : [votre adresse e-mail].";
				?>

				<div class="cbaz-copyblock">
					<textarea readonly rows="12"><?php echo esc_textarea( $texte ); ?></textarea>
					<button type="button" class="cbaz-btn cbaz-btn--mini" data-cbaz-copy>Copier</button>
				</div>

				<p class="cbaz-legal__hint">
					Ce texte suit tes réglages : la durée de conservation et la mention du cookie s’ajustent
					toutes seules. Si tu les modifies, reviens le copier.
				</p>

				<h3 class="cbaz-legal__title">Limite de responsabilité de l’éditeur</h3>

				<p>
					Cette extension fonctionne <strong>entièrement sur ton serveur</strong>. Aucune donnée n’est
					transmise à son éditeur, qui n’y a aucun accès et n’agit donc ni comme sous-traitant ni comme
					destinataire au sens du RGPD.
				</p>

				<p class="cbaz-legal__foot">
					Ce texte décrit le fonctionnement du logiciel et rappelle des obligations courantes.
					Il ne constitue pas un avis juridique. En cas de doute, notamment si tu traites des données
					sensibles ou si tu exportes des données hors Union européenne, consulte un professionnel du
					droit.
				</p>
			</div>

		<?php elseif ( 'exclus' === $volet ) : ?>
			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Ignorer les robots</strong>
					<p>
						Aspirateurs de contenu, sondes de supervision, générateurs d’aperçus de lien :
						ils passent, ils ne commandent jamais, et ils font baisser le taux de conversion.
						Leurs visites ne sont ni mesurées ni affichées.
					</p>
				</div>
				<div class="cbaz-setting__field"><?php echo cbaz_toggle( 'exclude_bots', $opts['exclude_bots'] ); // phpcs:ignore ?></div>
			</div>

			<div class="cbaz-setting">
				<div class="cbaz-setting__text">
					<strong>Rôles exclus</strong>
					<p>Une journée de mise au point sur la boutique gonflerait les pages produits sans la moindre vente.</p>
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

			<label class="cbaz-label">Chemins exclus — un par ligne, début d’adresse</label>
			<textarea name="exclude_paths" rows="5" class="cbaz-textarea"><?php echo esc_textarea( $opts['exclude_paths'] ); ?></textarea>

		<?php else : ?>
			<div class="cbaz-setting">
				<?php cbaz_toggle( 'delete_on_uninstall', ! empty( $opts['delete_on_uninstall'] ) ); ?>
				<div class="cbaz-setting__text">
					<strong>Effacer définitivement les données à la désinstallation</strong>
					<p>Désactivé par défaut. Activez ce choix avant de supprimer l’extension si vous souhaitez supprimer ses tables, réglages et métadonnées d’attribution.</p>
				</div>
			</div>

			<dl class="cbaz-stats">
				<div><dt>Visites enregistrées</dt><dd><?php echo esc_html( cbaz_int( $rows ) ); ?></dd></div>
				<div><dt>Pages vues</dt><dd><?php echo esc_html( cbaz_int( $views ) ); ?></dd></div>
				<div><dt>Stockage des commandes</dt><dd><?php echo cbaz_hpos() ? 'Tables WooCommerce' : 'Articles WordPress'; ?></dd></div>
				<div><dt>Purge automatique</dt><dd><?php echo wp_next_scheduled( 'cbaz_daily_purge' ) ? 'Programmée' : 'Non programmée'; ?></dd></div>
			</dl>

			<p class="cbaz-note">
				La purge tourne une fois par nuit et efface les visites au-delà de la durée de conservation.
				Si les tâches planifiées de WordPress sont désactivées, elle ne s’exécutera pas.
			</p>
		<?php endif; ?>

		<p class="cbaz-form__actions">
			<button type="submit" class="cbaz-btn">Enregistrer les modifications</button>
			<?php if ( $saved ) : ?>
				<span class="cbaz-saved">Dernière sauvegarde : <?php echo esc_html( wp_date( "j M Y 'à' H:i", (int) $saved ) ); ?></span>
			<?php endif; ?>
		</p>
	</form>
</section>
