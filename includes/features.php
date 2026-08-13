<?php
/**
 * Registre des fonctionnalités Free / Pro.
 *
 * Un seul endroit décide de ce qui est gratuit et de ce qui ne l'est pas.
 * Sans lui, la question « est-ce Pro ? » se retrouverait dispersée dans
 * une vingtaine de fichiers, et chaque oubli deviendrait une fuite.
 *
 * @package CamiBijoux\Analytics
 */

defined( 'ABSPATH' ) || exit;

/** Version minimale du module Pro compatible avec ce cœur. */
const CBAZ_PRO_MIN = '1.0.0';

/**
 * Version de la surface d'extension offerte au module Pro.
 *
 * À incrémenter uniquement lors d'une rupture de compatibilité.
 */
const CBAZ_API = 1;

/**
 * Le module Pro est-il chargé et compatible ?
 *
 * Il se déclare lui-même en définissant cette constante : le cœur n'a
 * ainsi aucune connaissance du code premium, ce qu'exige WordPress.org.
 */
function cbaz_pro() {
	return defined( 'CBAZ_PRO_VERSION' );
}

/**
 * Fonctionnalités réservées au module Pro.
 *
 * La valeur est le libellé affiché dans l'interface lorsqu'on présente
 * la fonctionnalité. Ajouter une entrée ici suffit à la marquer Pro
 * partout où le registre est consulté.
 */
function cbaz_pro_features() {
	return [
		'exports'            => __( 'Exports CSV', 'shop-analytics-for-woocommerce' ),
		'journeys_full'      => __( 'Historique complet des parcours', 'shop-analytics-for-woocommerce' ),
		'journeys_filters'   => __( 'Filtres et recherche de parcours', 'shop-analytics-for-woocommerce' ),
		'journeys_stats'     => __( 'Statistiques des parcours', 'shop-analytics-for-woocommerce' ),
		'campaign_stats'     => __( 'Performances des campagnes', 'shop-analytics-for-woocommerce' ),
		'realtime_details'   => __( 'Temps réel détaillé', 'shop-analytics-for-woocommerce' ),
	];
}

/**
 * Cette fonctionnalité est-elle disponible sur cette installation ?
 */
function cbaz_can( $feature ) {
	if ( ! isset( cbaz_pro_features()[ $feature ] ) ) {
		return true; // Tout ce qui n'est pas listé est gratuit.
	}

	return cbaz_pro();
}

/** Nombre de parcours visibles sans le module Pro. */
const CBAZ_FREE_JOURNEYS = 10;

/**
 * Combien de parcours récupérer.
 *
 * La limite est appliquée à la LECTURE, pas à l'affichage : sans Pro,
 * les parcours au-delà du dixième ne sortent jamais de la base.
 */
function cbaz_journeys_limit( $wanted = 40 ) {
	if ( ! cbaz_can( 'journeys_full' ) ) {
		return CBAZ_FREE_JOURNEYS;
	}

	return max( 1, min( 500, (int) $wanted ) );
}

/** Adresse de présentation du module Pro. */
function cbaz_pro_url() {
	return apply_filters( 'cbaz_pro_url', 'https://pluginect.com/shop-analytics/' );
}

/**
 * Encart de découverte du module Pro.
 *
 * Volontairement sobre : ni fenêtre surgissante, ni contenu flouté, ni
 * fausse alerte. Une bande discrète, alignée sur le reste de l'interface,
 * qui dit ce que fait la version payante et laisse un lien.
 *
 * Le libellé de l'action est explicite plutôt qu'impératif : « Voir ce
 * que fait Pro » informe, là où « Acheter » presse.
 *
 * @param string $title Ce dont il s'agit.
 * @param string $text  Ce que Pro apporte, en une phrase.
 */
function cbaz_pro_notice( $title, $text ) {
	if ( cbaz_pro() ) {
		return;
	}
	?>
	<aside class="cbaz-pro">
		<div class="cbaz-pro__body">
			<p class="cbaz-pro__title">
				<span class="cbaz-badge-pro"><?php echo esc_html__( 'Pro', 'shop-analytics-for-woocommerce' ); ?></span>
				<?php echo esc_html( $title ); ?>
			</p>
			<p class="cbaz-pro__text"><?php echo esc_html( $text ); ?></p>
		</div>

		<a class="cbaz-pro__cta" href="<?php echo esc_url( cbaz_pro_url() ); ?>" target="_blank" rel="noopener">
			<?php echo esc_html__( 'Voir ce que fait Pro', 'shop-analytics-for-woocommerce' ); ?>
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 17L17 7M9 7h8v8"/></svg>
			<span class="screen-reader-text"><?php echo esc_html__( '(ouvre un nouvel onglet)', 'shop-analytics-for-woocommerce' ); ?></span>
		</a>
	</aside>
	<?php
}

/** Petit badge « Pro » à accoler à un intitulé. */
function cbaz_pro_badge() {
	return cbaz_pro() ? '' : sprintf( ' <span class="cbaz-badge-pro">%1$s</span>', esc_html__( 'Pro', 'shop-analytics-for-woocommerce' ) );
}
