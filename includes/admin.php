<?php
/**
 * Administration.
 *
 * Une page, des onglets. Les graphiques sont dessinés en SVG côté
 * serveur : aucune bibliothèque à charger, donc un écran qui s'affiche
 * d'un bloc même sur une connexion moyenne.
 */

defined( 'ABSPATH' ) || exit;

require_once CBAZ_DIR . 'includes/geo.php';

add_action( 'admin_init', 'cbaz_privacy_policy_content' );
function cbaz_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = sprintf( '<p>%1$s</p>', esc_html__( 'Pluginect Analytics conserve localement des visites pseudonymes quotidiennes, les pages consultées, des événements de navigation et des informations techniques (préfixe réseau utilisé sans être stocké, navigateur, appareil, langue, résolution et pays estimé). Une visite convertie est reliée à la commande WooCommerce. Aucun cookie n’est déposé par défaut ; la mémoire d’attribution facultative dépose un cookie de provenance signé. Les durées de conservation sont configurables dans Analytics → Paramètres.', 'shop-analytics-for-woocommerce' ) );

	wp_add_privacy_policy_content( __( 'Pluginect Analytics', 'shop-analytics-for-woocommerce' ), wp_kses_post( $content ) );
}

// ══════════════════════════════════════════════════════════════
//  MENU
// ══════════════════════════════════════════════════════════════

function cbaz_tabs() {
	$tabs = [
		'overview'     => [ __( "Vue d'ensemble", 'shop-analytics-for-woocommerce' ), __( 'Analysez les performances de votre boutique.', 'shop-analytics-for-woocommerce' ) ],
		'temps-reel'   => [ __( 'Temps réel', 'shop-analytics-for-woocommerce' ), __( 'Ce qui se passe sur votre boutique en ce moment.', 'shop-analytics-for-woocommerce' ) ],
		'acquisition'  => [ __( 'Acquisition', 'shop-analytics-for-woocommerce' ), __( "Comprenez d'où viennent vos visiteurs et quelles sources convertissent.", 'shop-analytics-for-woocommerce' ) ],
		'comportement' => [ __( 'Comportement', 'shop-analytics-for-woocommerce' ), __( 'Ce que vos visiteurs consultent et comment ils naviguent.', 'shop-analytics-for-woocommerce' ) ],
		'ecommerce'    => [ __( 'E-commerce', 'shop-analytics-for-woocommerce' ), __( 'Du visiteur à la commande : où se perdent vos conversions.', 'shop-analytics-for-woocommerce' ) ],
		'produits'     => [ __( 'Produits', 'shop-analytics-for-woocommerce' ), __( 'Performance de chaque produit du catalogue WooCommerce.', 'shop-analytics-for-woocommerce' ) ],
		'campagnes'    => [ __( 'Campagnes', 'shop-analytics-for-woocommerce' ), __( 'Suivez les performances de vos campagnes UTM.', 'shop-analytics-for-woocommerce' ) ],
		'geographie'   => [ __( 'Géographie', 'shop-analytics-for-woocommerce' ), __( 'Localisation des visiteurs et performance commerciale par pays.', 'shop-analytics-for-woocommerce' ) ],
		'visiteurs'    => [ __( 'Visiteurs', 'shop-analytics-for-woocommerce' ), __( 'Appareils, technologies et fidélité de votre audience.', 'shop-analytics-for-woocommerce' ) ],
		'visites'      => [ __( 'Parcours', 'shop-analytics-for-woocommerce' ), __( 'Le parcours de chaque visite, page par page.', 'shop-analytics-for-woocommerce' ) ],
		'parametres'   => [ __( 'Paramètres', 'shop-analytics-for-woocommerce' ), __( 'Configuration du suivi, de la confidentialité et de la synchronisation.', 'shop-analytics-for-woocommerce' ) ],
	];

	/**
	 * Écrans du menu.
	 *
	 * Le module Pro y ajoute les siens ; « Paramètres » reste en dernier.
	 *
	 * @param array $tabs slug => [ titre, description ].
	 */
	$tabs = apply_filters( 'cbaz_tabs', $tabs );

	// Paramètres ferme toujours la marche, quel que soit l'ordre d'ajout.
	if ( isset( $tabs['parametres'] ) ) {
		$fin = $tabs['parametres'];
		unset( $tabs['parametres'] );
		$tabs['parametres'] = $fin;
	}

	return $tabs;
}

/**
 * Un sous-élément de menu par écran.
 *
 * WordPress n'accepte qu'une fonction de rendu par entrée : chacune
 * mémorise donc son onglet dans une variable globale, que la page
 * relit ensuite. C'est le prix d'un menu latéral plutôt qu'une rangée
 * d'onglets — et ça vaut le coup, on garde le repère de navigation
 * habituel de WordPress.
 */
add_action( 'admin_menu', 'cbaz_menu' );
function cbaz_menu() {
	$tabs  = cbaz_tabs();
	$first = array_key_first( $tabs );

	add_menu_page( __( 'Analytics', 'shop-analytics-for-woocommerce' ), __( 'Analytics', 'shop-analytics-for-woocommerce' ), 'manage_woocommerce', 'cbaz', 'cbaz_render_page', 'dashicons-chart-area', 56 );

	foreach ( $tabs as $slug => $meta ) {
		add_submenu_page(
			'cbaz',
			/* translators: %1$s: analytics screen title. */
			sprintf( __( '%1$s — Analytics', 'shop-analytics-for-woocommerce' ), $meta[0] ),
			$meta[0],
			'manage_woocommerce',
			$slug === $first ? 'cbaz' : 'cbaz-' . $slug,
			'cbaz_render_page'
		);
	}
}

/** Écran demandé, déduit du sous-menu en cours. */
function cbaz_current_tab() {
	$page = sanitize_key( $_GET['page'] ?? 'cbaz' );
	$slug = 'cbaz' === $page ? 'overview' : substr( $page, 5 );

	return isset( cbaz_tabs()[ $slug ] ) ? $slug : 'overview';
}

function cbaz_tab_page( $slug ) {
	return 'overview' === $slug ? 'cbaz' : 'cbaz-' . $slug;
}

add_action( 'admin_enqueue_scripts', 'cbaz_admin_assets' );
function cbaz_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'page_cbaz' ) ) {
		return;
	}

	wp_enqueue_style( 'cbaz-admin', CBAZ_URL . 'assets/admin.css', [], CBAZ_VERSION . '.' . filemtime( CBAZ_DIR . 'assets/admin.css' ) );
	$dependencies = [ 'wp-i18n' ];

	if ( 'cbaz-geographie' === sanitize_key( $_GET['page'] ?? '' ) ) {
		// Le maillage du monde est volumineux et ne sert qu'au globe.
		wp_enqueue_script( 'cbaz-world', CBAZ_URL . 'assets/world.js', [], CBAZ_VERSION, true );
		$dependencies[] = 'cbaz-world';
	}

	wp_enqueue_script( 'cbaz-admin', CBAZ_URL . 'assets/admin.js', $dependencies, CBAZ_VERSION . '.' . filemtime( CBAZ_DIR . 'assets/admin.js' ), true );
	wp_set_script_translations( 'cbaz-admin', 'shop-analytics-for-woocommerce', CBAZ_DIR . 'languages' );
}

// ══════════════════════════════════════════════════════════════
//  MESSAGES DE WORDPRESS
//
//  Les bandeaux des AUTRES extensions sont masqués sur nos écrans : une
//  page d'analyse couverte de sollicitations commerciales devient
//  illisible. Les nôtres survivent, et un pied de page indique combien
//  ont été écartés — escamoter sans le dire serait malhonnête, certains
//  portent une alerte réelle.
//
//  On coupe les actions plutôt que de masquer en CSS : WordPress
//  déplace les bandeaux en JavaScript juste après le titre de page,
//  donc les replier ne servait à rien — ils ressortaient du bloc où
//  on les avait rangés.
//
//  Un intégrateur peut explicitement les masquer :
//      add_filter( 'cbaz_hide_admin_notices', '__return_true' );
// ══════════════════════════════════════════════════════════════

add_action( 'in_admin_header', 'cbaz_hide_notices', 1000 );
function cbaz_hide_notices() {
	$screen = get_current_screen();

	if ( ! $screen || false === strpos( (string) $screen->id, 'page_cbaz' ) ) {
		return;
	}

	/**
	 * Masquer les bandeaux des autres extensions sur nos écrans.
	 *
	 * @param bool $masquer Vrai par défaut.
	 */
	if ( ! apply_filters( 'cbaz_hide_admin_notices', true ) ) {
		return;
	}

	global $wp_filter;

	$masques = 0;

	foreach ( [ 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ] as $hook ) {
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			continue;
		}

		$garder = [];

		foreach ( $wp_filter[ $hook ]->callbacks as $priorite => $rappels ) {
			foreach ( $rappels as $rappel ) {
				if ( cbaz_notice_is_ours( $rappel['function'] ) ) {
					$garder[] = [ $priorite, $rappel['function'], (int) $rappel['accepted_args'] ];
					continue;
				}

				++$masques;
			}
		}

		remove_all_actions( $hook );

		// Nos propres bandeaux sont remis en place avec leur priorité
		// d'origine : les réenregistrer à la volée changerait leur ordre.
		foreach ( $garder as $g ) {
			add_action( $hook, $g[1], $g[0], $g[2] );
		}
	}

	if ( $masques > 0 ) {
		set_transient( 'cbaz_notices_masquees', $masques, 60 );
	}
}

/**
 * Ce bandeau vient-il de nos extensions ?
 *
 * Seuls les nôtres survivent au nettoyage : un message de licence ou un
 * avertissement de configuration doit rester visible là où il compte.
 *
 * Une fonction anonyme est indissociable de son auteur : on la considère
 * comme extérieure, ce qui est le cas dans l'immense majorité des
 * extensions.
 *
 * @param mixed $rappel Fonction de rappel enregistrée.
 * @return bool
 */
function cbaz_notice_is_ours( $rappel ) {
	$nom = '';

	if ( is_string( $rappel ) ) {
		$nom = $rappel;
	} elseif ( is_array( $rappel ) && isset( $rappel[1] ) ) {
		$objet = $rappel[0];
		$nom   = ( is_object( $objet ) ? get_class( $objet ) : (string) $objet ) . '::' . $rappel[1];
	}

	if ( '' === $nom ) {
		return false;
	}

	/**
	 * Préfixes de bandeaux conservés sur nos écrans.
	 *
	 * @param array $prefixes Fragments recherchés dans le nom du rappel.
	 */
	$prefixes = apply_filters( 'cbaz_kept_notices', [
		'cbaz',
		'ShopAnalytics',
		'Shop_Analytics_Demo',
		'sad_',
	] );

	foreach ( (array) $prefixes as $prefixe ) {
		if ( false !== stripos( $nom, (string) $prefixe ) ) {
			return true;
		}
	}

	/*
	 * Quelques bandeaux du cœur de WordPress signalent un incident réel :
	 * un site en mode dépannage tourne avec une extension neutralisée, et
	 * l'administrateur doit le savoir où qu'il se trouve. Masquer
	 * celui-là pour faire joli serait indéfendable.
	 */
	$critiques = apply_filters( 'cbaz_critical_notices', [
		'wp_recovery_mode_nag',
		'paused_plugins_notice',
		'paused_themes_notice',
		'privacy_policy_guide_notice',
	] );

	return in_array( $nom, (array) $critiques, true );
}

/**
 * Signale discrètement ce qui a été masqué.
 *
 * Escamoter des messages sans le dire serait malhonnête : certains
 * portent une alerte de sécurité ou réclament une mise à jour de base de
 * données. On indique donc combien, et où les retrouver.
 *
 * @return void
 */
function cbaz_hidden_notice_hint() {
	$n = (int) get_transient( 'cbaz_notices_masquees' );

	if ( $n < 1 ) {
		return;
	}

	delete_transient( 'cbaz_notices_masquees' );

	printf(
		'<p class="cbaz-note">%1$s <a href="%2$s">%3$s</a></p>',
		esc_html( sprintf(
			/* translators: %d : nombre de bandeaux masqués */
			_n(
				'%d message d’une autre extension a été masqué sur cet écran.',
				'%d messages d’autres extensions ont été masqués sur cet écran.',
				$n,
				'shop-analytics-for-woocommerce'
			),
			$n
		) ),
		esc_url( admin_url() ),
		esc_html__( 'Les consulter', 'shop-analytics-for-woocommerce' )
	);
}

// ══════════════════════════════════════════════════════════════
//  COMPOSANTS
// ══════════════════════════════════════════════════════════════

/**
 * Pastille de variation.
 *
 * Vert quand ça monte, rouge quand ça descend, sans exception : c'est
 * la lecture demandée, et elle a le mérite d'être la même partout.
 *
 * La flèche double la couleur — elle reste lisible en noir et blanc,
 * et pour qui distingue mal le rouge du vert.
 */
function cbaz_delta_badge( $value, $invert = false ) {
	if ( null === $value ) {
		return sprintf( '<span class="cbaz-delta cbaz-delta--none" title="%1$s">%2$s</span>', esc_attr__( 'Aucune donnée sur la période précédente', 'shop-analytics-for-woocommerce' ), esc_html__( 'nouveau', 'shop-analytics-for-woocommerce' ) );
	}

	$value = (float) $value;

	if ( 0.0 === $value ) {
		return sprintf( '<span class="cbaz-delta cbaz-delta--flat">%1$s <span class="cbaz-delta__arrow" aria-hidden="true">→</span></span>', esc_html__( 'stable', 'shop-analytics-for-woocommerce' ) );
	}

	$up = $value > 0;

	// La flèche suit le chiffre : on lit d'abord la valeur, le sens
	// vient la confirmer.
	return '<span class="cbaz-delta cbaz-delta--' . ( $up ? 'up' : 'down' ) . '">'
		. ( $up ? '+' : '−' ) . number_format_i18n( abs( $value ), 1 ) . ' %'
		. '<span class="cbaz-delta__arrow" aria-hidden="true">' . ( $up ? '↗' : '↘' ) . '</span>'
		. '</span>';
}

/**
 * Petites icônes d'interface.
 *
 * Dessinées au trait, dans la couleur héritée : une seule définition
 * sert aussi bien sur fond clair que sur une pastille teintée. Elles
 * repèrent une famille de données — appareils, pays, ventes — et pas
 * une action, donc elles ne cliquent jamais.
 */
function cbaz_icon( $name, $ton = 0 ) {
	$paths = [
		'device'   => '<rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M11 18.5h2"/>',
		'os'       => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
		'browser'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3.5 9h17M3.5 15h17M12 3c2.4 2.6 2.4 15.4 0 18M12 3c-2.4 2.6-2.4 15.4 0 18"/>',
		'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.4 6-5.4s6 2.1 6 5.4"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M17.5 14.9c2.1.6 3.5 2.3 3.5 5.1"/>',
		'screen'   => '<rect x="2.5" y="4.5" width="19" height="12" rx="2"/><path d="M8 20h8"/>',
		'language' => '<path d="M3 6h9M7.5 4v2M9.5 6c-.6 4-3 6.6-6.5 8"/><path d="M5 11c1.6 2.2 3.8 3.6 6 4.3"/><path d="M12.5 20l4-9 4 9M14 17h5"/>',
		'loyalty'  => '<path d="M12 3.6l2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7-5.1 2.7 1-5.6-4-3.9 5.6-.8z"/>',
		'chart'    => '<path d="M3 20h18"/><path d="M6 16v-4M11 16V6M16 16v-7"/>',
		'cart'     => '<circle cx="9.5" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/><path d="M2.5 3.5h2.4l2.4 11.2h11l2-7.7H6.3"/>',
		'money'    => '<circle cx="12" cy="12" r="9"/><path d="M12 6.8v10.4M14.7 9.4c-.6-.9-1.6-1.3-2.7-1.3-1.6 0-2.7.8-2.7 2s1 1.7 2.7 2.1c1.9.4 2.9 1 2.9 2.2s-1.2 2-2.9 2c-1.3 0-2.3-.5-2.9-1.4"/>',
		'box'      => '<path d="M12 2.8l8.2 4.3v9.8L12 21.2 3.8 16.9V7.1z"/><path d="M3.8 7.1L12 11.4l8.2-4.3M12 11.4v9.8"/>',
		'tag'      => '<path d="M3.5 11.3V4h7.3l9.7 9.7-7.3 7.3z"/><circle cx="7.7" cy="8" r="1.3"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 6.8V12l3.4 2"/>',
		'search'   => '<circle cx="11" cy="11" r="6.6"/><path d="M20 20l-4.4-4.4"/>',
		'path'     => '<circle cx="5.5" cy="6" r="2.3"/><circle cx="18.5" cy="18" r="2.3"/><path d="M7.8 6h6.4a3.6 3.6 0 0 1 0 7.2H9.8a3.6 3.6 0 0 0 0 4.8h6.4"/>',
		'page'     => '<path d="M13.5 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8.5z"/><path d="M13.5 3v5.5H19M8.5 13h7M8.5 16.5h5"/>',
		'flag'     => '<path d="M5 21V4M5 4.5h11l-2 3.5 2 3.5H5"/>',
		'bolt'     => '<path d="M13.5 2.5L5 13.5h6L10.5 21.5 19 10.5h-6z"/>',
		'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
	];

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$classe = $ton ? ' cbaz-card__icon--' . (int) $ton : '';

	return '<span class="cbaz-card__icon' . $classe . '" aria-hidden="true">'
		. '<svg viewBox="0 0 24 24" focusable="false">' . $paths[ $name ] . '</svg></span>';
}

/**
 * Icône d'un indicateur, déduite de son intitulé.
 *
 * Le rapprochement se fait sur les mots plutôt que sur une table de
 * correspondance : les indicateurs se nomment d'eux-mêmes, et un
 * nouveau libellé contenant « commandes » trouve son panier sans
 * qu'on ait à l'inscrire quelque part. Ce qui reste sans mot connu
 * n'a pas d'icône — mieux vaut aucun repère qu'un repère faux.
 */
function cbaz_kpi_icon( $label ) {
	/*
	 * Chaque famille porte une icône ET une teinte. La teinte n'est pas
	 * décorative : elle reprend la couleur de la série correspondante
	 * dans les graphiques — visites en bleu, pages vues en vert,
	 * chiffre d'affaires en orange, commandes en violet. L'indicateur
	 * et la courbe qui le raconte parlent ainsi de la même voix.
	 */
	$mots = [
		'money'    => [ 3, [ 'ca ', 'chiffre', 'panier moyen', 'valeur', 'budget', 'investi', 'retour', 'coût', 'prix', 'revenu', 'revenue', 'aov' ] ],
		'clock'    => [ 6, [ 'durée', 'délai', 'temps' ] ],
		'cart'     => [ 4, [ 'commande', 'conversion', 'achat', 'vendus', 'vente', 'cr' ] ],
		'users'    => [ 1, [ 'visiteur', 'audience', 'nouveau', 'fidélité', 'visitors' ] ],
		'page'     => [ 2, [ 'page', 'pageviews' ] ],
		'flag'     => [ 5, [ 'rebond', 'fuite', 'sortie' ] ],
		'box'      => [ 3, [ 'produit', 'référence', 'article', 'catalogue' ] ],
		'tag'      => [ 4, [ 'campagne' ] ],
		'globe'    => [ 6, [ 'pays', 'provenance', 'source', 'contexte' ] ],
		'path'     => [ 1, [ 'parcours', 'visite', 'chemin', 'issue', 'sessions' ] ],
		'calendar' => [ 2, [ 'profondeur', 'mois', 'année', 'période' ] ],
	];

	$cible = ' ' . strtolower( remove_accents( wp_strip_all_tags( (string) $label ) ) ) . ' ';

	foreach ( $mots as $icone => $reglage ) {
		list( $ton, $liste ) = $reglage;

		foreach ( $liste as $mot ) {
			if ( false !== strpos( $cible, remove_accents( $mot ) ) ) {
				/*
				 * La teinte est répétée sur le conteneur, et pas
				 * seulement sur la pastille : c'est elle qui permet de
				 * peindre la courbe du même indicateur, plus bas dans
				 * le bloc, sans avoir à la lui répéter dans chaque vue.
				 */
				return '<span class="cbaz-kpi__icon cbaz-tone-' . (int) $ton . '">' . cbaz_icon( $icone, $ton ) . '</span>';
			}
		}
	}

	return '';
}

function cbaz_bar( $value, $max ) {
	$pct = $max > 0 ? min( 100, ( $value / $max ) * 100 ) : 0;

	return '<span class="cbaz-bar"><span style="width:' . round( $pct, 2 ) . '%"></span></span>';
}

/**
 * Anneau de répartition.
 *
 * Un camembert ment sur les petites parts ; l'anneau les rend lisibles
 * parce que l'œil compare des longueurs d'arc et non des surfaces.
 */
function cbaz_donut( array $rows, $total ) {
	$total = max( 1, (float) $total );
	$r     = 54;
	$c     = 2 * M_PI * $r;
	$offset = 0;
	$arcs  = '';
	$legend = '';
	$i     = 0;

	foreach ( $rows as $row ) {
		$share = ( (float) $row->sessions / $total ) * 100;
		$len   = ( $share / 100 ) * $c;

		$arcs .= '<circle class="cbaz-donut__arc cbaz-donut__arc--' . ( $i % 5 ) . '" cx="70" cy="70" r="' . $r . '"'
			. ' stroke-dasharray="' . round( $len, 2 ) . ' ' . round( $c - $len, 2 ) . '"'
			. ' stroke-dashoffset="' . round( -$offset, 2 ) . '"></circle>';

		$legend .= '<li><span class="cbaz-dot cbaz-dot--' . ( $i % 5 ) . '"></span>'
			. '<span>' . esc_html( ucfirst( $row->label ) ) . '</span>'
			. '<span class="cbaz-num">' . esc_html( cbaz_pct( $share, 1 ) ) . '</span></li>';

		$offset += $len;
		$i++;
	}

	if ( ! $rows ) {
		return sprintf( '<p class="cbaz-empty">%1$s</p>', esc_html__( 'Aucune visite sur la période.', 'shop-analytics-for-woocommerce' ) );
	}

	return '<div class="cbaz-donut">'
		. '<svg viewBox="0 0 140 140" aria-hidden="true">' . $arcs . '</svg>'
		. '<ul class="cbaz-donut__legend">' . $legend . '</ul>'
		. '</div>';
}

/** Liste à jauges, celle des systèmes et des navigateurs. */
function cbaz_meter_list( array $rows, $total, array $previous = [] ) {
	$total = max( 1, (float) $total );

	echo '<ul class="cbaz-list">';

	foreach ( $rows as $i => $row ) {
		$share = ( (float) $row->sessions / $total ) * 100;

		// La variation n'apparaît que si l'on sait à quoi comparer :
		// une liste appelée sans période précédente reste telle quelle.
		$delta = $previous
			? '<span class="cbaz-list__delta">' . cbaz_delta_badge( cbaz_row_delta( $previous, $row->label, $row->sessions ) ) . '</span>'
			: '';

		echo '<li>'
			. '<div class="cbaz-list__row"><span>' . esc_html( $row->label ) . '</span>'
			. '<span class="cbaz-num">' . $delta . esc_html( cbaz_int( $row->sessions ) ) . ' · ' . esc_html( cbaz_pct( $share, 1 ) ) . '</span></div>'
			. '<span class="cbaz-bar cbaz-bar--' . ( $i % 5 ) . '"><span style="width:' . round( $share, 2 ) . '%"></span></span>'
			. '</li>';
	}

	if ( ! $rows ) {
		printf( '<li class="cbaz-empty">%1$s</li>', esc_html__( 'Rien à afficher.', 'shop-analytics-for-woocommerce' ) );
	}

	echo '</ul>';
}

/**
 * Trace une courbe lissée.
 *
 * Des segments droits donnent un profil anguleux qui suggère des
 * ruptures là où il n'y a qu'un jour de creux. Les points de contrôle
 * sont posés au tiers de chaque intervalle : la courbe passe par tous
 * les points mesurés sans jamais inventer de bosse entre deux.
 */
function cbaz_smooth_path( array $points ) {
	$n = count( $points );

	if ( $n < 2 ) {
		return $n ? 'M' . $points[0][0] . ' ' . $points[0][1] : '';
	}

	$d = 'M' . round( $points[0][0], 1 ) . ' ' . round( $points[0][1], 1 );

	for ( $i = 0; $i < $n - 1; $i++ ) {
		$x0 = $points[ $i ][0];
		$y0 = $points[ $i ][1];
		$x1 = $points[ $i + 1 ][0];
		$y1 = $points[ $i + 1 ][1];

		$dx = ( $x1 - $x0 ) / 3;

		$d .= ' C' . round( $x0 + $dx, 1 ) . ' ' . round( $y0, 1 )
			. ' ' . round( $x1 - $dx, 1 ) . ' ' . round( $y1, 1 )
			. ' ' . round( $x1, 1 ) . ' ' . round( $y1, 1 );
	}

	return $d;
}

/** Échelle lisible : on arrondit le maximum au cran supérieur. */
function cbaz_nice_max( $value ) {
	$value = max( 1, (float) $value );
	$pow   = pow( 10, floor( log10( $value ) ) );
	$head  = $value / $pow;

	foreach ( [ 1, 1.5, 2, 2.5, 3, 4, 5, 7.5, 10 ] as $step ) {
		if ( $head <= $step ) {
			return $step * $pow;
		}
	}

	return 10 * $pow;
}

/**
 * Graphique principal.
 *
 * Deux échelles : les effectifs à gauche, l'argent à droite. Sans
 * cela, une courbe de trente visites disparaîtrait sous une barre de
 * deux mille euros — les deux grandeurs n'ont pas d'unité commune.
 *
 * Dessiné en PHP : le graphe est là au premier affichage, sans
 * bibliothèque à télécharger ni à exécuter.
 */
function cbaz_chart( array $series, array $show = [ 'sessions', 'revenue' ] ) {
	if ( ! $series ) {
		return sprintf( '<p class="cbaz-empty">%1$s</p>', esc_html__( 'Pas encore de données sur cette période.', 'shop-analytics-for-woocommerce' ) );
	}

	$defs = [
		'sessions'  => __( 'Visites', 'shop-analytics-for-woocommerce' ),
		'pageviews' => __( 'Pages vues', 'shop-analytics-for-woocommerce' ),
		'revenue'   => __( "Chiffre d'affaires", 'shop-analytics-for-woocommerce' ),
		'orders'    => __( 'Commandes', 'shop-analytics-for-woocommerce' ),
	];

	$w  = 1000;
	$h  = 300;
	$l  = 52;
	$r  = 58;
	$t  = 18;
	$b  = 38;

	$n    = count( $series );
	$step = $n > 1 ? ( $w - $l - $r ) / ( $n - 1 ) : 0;
	$plot = $h - $t - $b;

	// Une seule échelle pour les trois séries d'effectifs : elles se
	// comparent entre elles, ce qui serait faux si chacune avait la
	// sienne.
	$left_max  = cbaz_nice_max( max( 1, max( array_merge(
		wp_list_pluck( $series, 'sessions' ),
		wp_list_pluck( $series, 'pageviews' ),
		wp_list_pluck( $series, 'orders' )
	) ) ) );
	$right_max = cbaz_nice_max( max( 1, max( wp_list_pluck( $series, 'revenue' ) ) ) );

	// ── Grille et graduations ──────────────────────────────
	$grid = '';

	for ( $g = 0; $g <= 4; $g++ ) {
		$y = $t + ( $g / 4 ) * $plot;

		$grid .= '<line class="cbaz-chart__grid" x1="' . $l . '" x2="' . ( $w - $r ) . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"></line>'
			. '<text class="cbaz-chart__tick" x="' . ( $l - 10 ) . '" y="' . round( $y + 4, 1 ) . '" text-anchor="end">'
			. esc_html( cbaz_int( $left_max * ( 1 - $g / 4 ) ) ) . '</text>'
			. '<text class="cbaz-chart__tick" x="' . ( $w - $r + 10 ) . '" y="' . round( $y + 4, 1 ) . '" text-anchor="start">'
			. esc_html( cbaz_int( $right_max * ( 1 - $g / 4 ) ) ) . '</text>';
	}

	// ── Barres du chiffre d'affaires ───────────────────────
	$bars = '';
	$dots = '';
	$xlab = '';
	$every = max( 1, (int) ceil( $n / 6 ) );

	foreach ( $series as $i => $p ) {
		$x  = $l + $i * $step;
		$bw = max( 2, min( 30, $step * 0.5 ) );
		$bh = ( $p['revenue'] / $right_max ) * $plot;

		if ( $bh > 0.5 ) {
			$bars .= '<rect class="cbaz-chart__bar" x="' . round( $x - $bw / 2, 1 ) . '" y="' . round( $h - $b - $bh, 1 )
				. '" width="' . round( $bw, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="2"></rect>';
		}

		$dots .= '<rect class="cbaz-chart__dot" x="' . round( $x - $step / 2, 1 ) . '" y="' . $t . '"'
			. ' width="' . round( max( 4, $step ), 1 ) . '" height="' . round( $plot, 1 ) . '"'
			. ' data-label="' . esc_attr( $p['label'] ) . '"'
			. ' data-sessions="' . esc_attr( cbaz_int( $p['sessions'] ) ) . '"'
			. ' data-pageviews="' . esc_attr( cbaz_int( $p['pageviews'] ) ) . '"'
			. ' data-revenue="' . esc_attr( cbaz_money( $p['revenue'] ) ) . '"'
			. ' data-orders="' . esc_attr( cbaz_int( $p['orders'] ) ) . '"></rect>';

		if ( 0 === $i % $every || $i === $n - 1 ) {
			$xlab .= '<text class="cbaz-chart__tick" x="' . round( $x, 1 ) . '" y="' . ( $h - 14 ) . '" text-anchor="middle">'
				. esc_html( $p['label'] ) . '</text>';
		}
	}

	// ── Courbes ────────────────────────────────────────────
	$lines = '';

	foreach ( [ 'sessions', 'pageviews', 'orders' ] as $key ) {
		$pts = [];

		foreach ( $series as $i => $p ) {
			$pts[] = [ $l + $i * $step, $h - $b - ( $p[ $key ] / $left_max ) * $plot ];
		}

		$path = cbaz_smooth_path( $pts );

		if ( 'sessions' === $key ) {
			$area = $path . ' L' . round( $l + ( $n - 1 ) * $step, 1 ) . ' ' . ( $h - $b ) . ' L' . $l . ' ' . ( $h - $b ) . ' Z';

			$lines .= '<path class="cbaz-chart__fill--sessions" data-serie="sessions" d="' . esc_attr( $area ) . '"'
				. ( in_array( 'sessions', $show, true ) ? '' : ' hidden' ) . '></path>';
		}

		$lines .= '<path class="cbaz-chart__serie cbaz-chart__serie--' . $key . '" data-serie="' . $key . '" d="' . esc_attr( $path ) . '"'
			. ( in_array( $key, $show, true ) ? '' : ' hidden' ) . '></path>';
	}

	ob_start();
	?>
	<div class="cbaz-series">
		<?php foreach ( $defs as $key => $label ) : ?>
			<button type="button" class="cbaz-serie<?php echo in_array( $key, $show, true ) ? ' is-on' : ''; ?>"
			        data-serie="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="cbaz-chart" data-cbaz-chart>
		<svg viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" role="img" aria-label="<?php echo esc_attr__( 'Évolution sur la période', 'shop-analytics-for-woocommerce' ); ?>">
			<?php echo $grid; // phpcs:ignore ?>
			<g data-serie="revenue"<?php echo in_array( 'revenue', $show, true ) ? '' : ' hidden'; ?>><?php echo $bars; // phpcs:ignore ?></g>
			<?php echo $lines; // phpcs:ignore ?>
			<?php echo $xlab; // phpcs:ignore ?>
			<?php echo $dots; // phpcs:ignore ?>
		</svg>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Histogramme groupé : deux séries par catégorie.
 *
 * C'est la forme qu'il faut pour comparer premier et dernier contact —
 * l'œil rapproche naturellement deux barres accolées, alors qu'il
 * peine à relier deux barres éloignées d'une même couleur.
 */
function cbaz_grouped_bars( array $rows, array $legend ) {
	if ( ! $rows ) {
		return sprintf( '<p class="cbaz-empty">%1$s</p>', esc_html__( 'Aucune donnée sur la période.', 'shop-analytics-for-woocommerce' ) );
	}

	$w = 1000;
	$h = 300;
	$l = 58;
	$b = 46;
	$t = 18;

	$plot = $h - $t - $b;
	$n    = count( $rows );
	$slot = ( $w - $l - 20 ) / $n;
	$bw   = min( 34, $slot * 0.3 );

	$max  = cbaz_nice_max( max( 1, max( array_map( fn( $r ) => max( $r['a'], $r['b'] ), $rows ) ) ) );
	$svg  = '';

	for ( $g = 0; $g <= 4; $g++ ) {
		$y    = $t + ( $g / 4 ) * $plot;
		$svg .= '<line class="cbaz-chart__grid" x1="' . $l . '" x2="' . ( $w - 20 ) . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"></line>'
			. '<text class="cbaz-chart__tick" x="' . ( $l - 10 ) . '" y="' . round( $y + 4, 1 ) . '" text-anchor="end">'
			. esc_html( cbaz_int( $max * ( 1 - $g / 4 ) ) ) . '</text>';
	}

	foreach ( array_values( $rows ) as $i => $row ) {
		$cx = $l + $i * $slot + $slot / 2;

		foreach ( [ 'a' => -1, 'b' => 1 ] as $key => $side ) {
			$bh = ( (float) $row[ $key ] / $max ) * $plot;
			$x  = $cx + ( $side < 0 ? -$bw - 2 : 2 );

			$svg .= '<rect class="cbaz-gbar cbaz-gbar--' . $key . '" x="' . round( $x, 1 ) . '" y="' . round( $h - $b - $bh, 1 )
				. '" width="' . round( $bw, 1 ) . '" height="' . round( max( 1, $bh ), 1 ) . '" rx="3">'
				. '<title>' . esc_html( $row['label'] . ' — ' . cbaz_money( $row[ $key ] ) ) . '</title></rect>';
		}

		$svg .= '<text class="cbaz-chart__tick" x="' . round( $cx, 1 ) . '" y="' . ( $h - 18 ) . '" text-anchor="middle">'
			. esc_html( $row['label'] ) . '</text>';
	}

	return sprintf(
		'<div class="cbaz-chart"><svg viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s">%4$s</svg></div><div class="cbaz-chart__legend"><span class="cbaz-key cbaz-key--first">%5$s</span><span class="cbaz-key cbaz-key--last">%6$s</span></div>',
		$w,
		$h,
		esc_attr__( 'Comparaison par canal', 'shop-analytics-for-woocommerce' ),
		$svg,
		esc_html( $legend[0] ),
		esc_html( $legend[1] )
	);
}

/**
 * Entonnoir de conversion.
 *
 * L'information n'est pas dans les étapes, elle est dans ce qui se
 * perd entre elles : chaque palier est donc suivi du nombre de
 * personnes parties, en clair, plutôt que d'un simple pourcentage
 * restant.
 */
function cbaz_funnel_block( array $steps, array $previous = [] ) {
	ob_start();
	?>
	<div class="cbaz-steps">
		<?php foreach ( $steps as $i => $step ) : ?>
			<?php if ( $i > 0 ) : ?>
				<?php
				$lost = $steps[ $i - 1 ]['value'] - $step['value'];

				// Un taux d'abandon en hausse est une mauvaise nouvelle :
				// la flèche est donc inversée pour cette seule mesure.
				$drop_delta = isset( $previous[ $i ]['drop'] )
					? cbaz_delta( $step['drop'], $previous[ $i ]['drop'] )
					: null;
				?>
				<p class="cbaz-loss">
					<?php /* translators: 1: abandoned visitor count, 2: abandonment rate. */ printf( wp_kses_post( __( '<strong>− %1$s</strong> abandons (%2$s %%)', 'shop-analytics-for-woocommerce' ) ), esc_html( cbaz_int( $lost ) ), esc_html( number_format_i18n( $step['drop'], 1 ) ) ); ?>
					<?php if ( $previous ) : ?>
						<?php echo cbaz_delta_badge( $drop_delta, true ); // phpcs:ignore ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<div class="cbaz-step">
				<span class="cbaz-step__n"><?php echo (int) ( $i + 1 ); ?></span>
				<span class="cbaz-step__label"><?php echo esc_html( $step['label'] ); ?></span>
				<span class="cbaz-step__share">
					<?php if ( 0 === $i ) { echo esc_html__( "Point d'entrée", 'shop-analytics-for-woocommerce' ); } else { /* translators: %1$s: share of visits. */ printf( esc_html__( '%1$s %% des visites', 'shop-analytics-for-woocommerce' ), esc_html( number_format_i18n( $step['pct'], 1 ) ) ); } ?>
				</span>
				<span class="cbaz-step__value"><?php echo esc_html( cbaz_int( $step['value'] ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Panneau « points de fuite » : où l'on perd le plus, par ordre. */
function cbaz_leaks_block( array $steps, array $previous = [] ) {
	$leaks = [];

	for ( $i = 1; $i < count( $steps ); $i++ ) {
		$leaks[] = [
			'label' => $steps[ $i - 1 ]['label'] . ' → ' . $steps[ $i ]['label'],
			'pct'   => $steps[ $i ]['drop'],
			'lost'  => $steps[ $i - 1 ]['value'] - $steps[ $i ]['value'],
			// Une fuite qui s'aggrave doit se lire en rouge : la flèche
			// est inversée à l'affichage, comme pour l'entonnoir.
			'delta' => isset( $previous[ $i ]['drop'] ) ? cbaz_delta( $steps[ $i ]['drop'], $previous[ $i ]['drop'] ) : null,
			'avant' => isset( $previous[ $i ]['drop'] ),
		];
	}

	ob_start();
	?>
	<ul class="cbaz-leaks">
		<?php foreach ( $leaks as $leak ) : ?>
			<li>
				<div class="cbaz-leaks__head">
					<span><?php echo esc_html( $leak['label'] ); ?></span>
					<span class="cbaz-leaks__pct">
						− <?php echo esc_html( number_format_i18n( $leak['pct'], 1 ) ); ?> %
						<?php if ( $leak['avant'] ) : ?>
							<?php echo cbaz_delta_badge( $leak['delta'], true ); // phpcs:ignore ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="cbaz-leaks__bar"><span style="width:<?php echo esc_attr( min( 100, max( 1, $leak['pct'] ) ) ); ?>%"></span></div>
				<p class="cbaz-leaks__note"><?php /* translators: %1$s: visitors lost at this step. */ printf( esc_html__( '%1$s personnes perdues à cette étape', 'shop-analytics-for-woocommerce' ), esc_html( cbaz_int( $leak['lost'] ) ) ); ?></p>
			</li>
		<?php endforeach; ?>
		<?php if ( ! $leaks ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Pas encore de parcours mesuré.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
	</ul>
	<?php
	return ob_get_clean();
}

/**
 * Histogramme vertical.
 *
 * Pour comparer une poignée de catégories entre elles — le chiffre
 * d'affaires par canal, la conversion par appareil. Au-delà de huit
 * barres, une liste horizontale se lit mieux : les étiquettes n'ont
 * plus la place de tenir sous les colonnes.
 */
function cbaz_bars_chart( array $rows, $format = 'money' ) {
	if ( ! $rows ) {
		return sprintf( '<p class="cbaz-empty">%1$s</p>', esc_html__( 'Aucune donnée sur la période.', 'shop-analytics-for-woocommerce' ) );
	}

	$max = max( 1, max( array_map( fn( $r ) => (float) $r['value'], $rows ) ) );
	$n   = count( $rows );
	$w   = 600;
	$h   = 220;
	$pad = 30;
	$slot = ( $w - $pad * 2 ) / $n;
	$bw  = min( 54, $slot * 0.55 );

	$bars = '';
	$axis = '';

	foreach ( array_values( $rows ) as $i => $row ) {
		$bh = ( (float) $row['value'] / $max ) * ( $h - $pad * 2 );
		$x  = $pad + $i * $slot + ( $slot - $bw ) / 2;

		$bars .= '<rect class="cbaz-vbar cbaz-vbar--' . ( $i % 6 ) . '" x="' . round( $x, 1 ) . '" y="' . round( $h - $pad - $bh, 1 )
			. '" width="' . round( $bw, 1 ) . '" height="' . round( max( 1, $bh ), 1 ) . '" rx="3">'
			. '<title>' . esc_html( $row['label'] . ' — ' . cbaz_format( $row['value'], $format ) ) . '</title></rect>';

		$axis .= '<div style="flex:1 1 0;">' . esc_html( $row['label'] ) . '</div>';
	}

	return '<div class="cbaz-vchart">'
		. '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
		. '<line class="cbaz-chart__grid" x1="' . $pad . '" x2="' . ( $w - $pad ) . '" y1="' . ( $h - $pad ) . '" y2="' . ( $h - $pad ) . '"></line>'
		. $bars . '</svg>'
		. '<div class="cbaz-vchart__axis">' . $axis . '</div>'
		. '</div>';
}

/** Barres horizontales étiquetées, quand les catégories sont nombreuses. */
function cbaz_hbars( array $rows, $format = 'money' ) {
	if ( ! $rows ) {
		return sprintf( '<p class="cbaz-empty">%1$s</p>', esc_html__( 'Aucune donnée sur la période.', 'shop-analytics-for-woocommerce' ) );
	}

	$max = max( 1, max( array_map( fn( $r ) => (float) $r['value'], $rows ) ) );
	$out = '<ul class="cbaz-hbars">';

	foreach ( array_values( $rows ) as $i => $row ) {
		$out .= '<li>'
			. '<span class="cbaz-hbars__label">' . esc_html( $row['label'] ) . '</span>'
			. '<span class="cbaz-hbars__track"><span class="cbaz-hbars__fill cbaz-hbars__fill--' . ( $i % 6 ) . '"'
			. ' style="width:' . round( ( (float) $row['value'] / $max ) * 100, 2 ) . '%"></span></span>'
			. '<span class="cbaz-hbars__value">' . esc_html( cbaz_format( $row['value'], $format ) ) . '</span>'
			. '</li>';
	}

	return $out . '</ul>';
}

/**
 * Logos de marque, embarqués dans le plugin.
 *
 * La version précédente allait chercher le favicon officiel de chaque
 * réseau. C'était séduisant sur le papier — toujours à jour, rien à
 * maintenir — et inutilisable en pratique : un bloqueur de contenu,
 * un pare-feu d'entreprise ou une simple protection anti-hotlink
 * suffit à faire échouer la requête, et l'administration retombait
 * silencieusement sur la pastille à la lettre. C'est exactement ce
 * qu'on voyait.
 *
 * Ces marques sont donc servies ici en SVG par le site lui-même. Aucune
 * requête sortante, rien à bloquer, rien à charger : elles s'affichent
 * hors ligne comme derrière n'importe quel filtre. Les tracés proviennent
 * de Bootstrap Icons 1.13.1 et, pour Gmail et Etsy, de Tabler Icons 3.46.0.
 * Les notices MIT correspondantes sont conservées dans le dossier licenses.
 */
function cbaz_source_icon( $label ) {
	$svg = [

		'Instagram' => '<defs><linearGradient id="cbaz-ig" x1="0" y1="1" x2="1" y2="0">'
			. '<stop offset="0" stop-color="#FEDA75"/><stop offset=".25" stop-color="#FA7E1E"/>'
			. '<stop offset=".5" stop-color="#D62976"/><stop offset=".75" stop-color="#962FBF"/>'
			. '<stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs>'
			. '<rect x="1" y="1" width="22" height="22" rx="6" fill="url(#cbaz-ig)"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/>',

		'Facebook' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#1877F2"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/>',

		'Messenger' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#0084FF"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M0 7.76C0 3.301 3.493 0 8 0s8 3.301 8 7.76-3.493 7.76-8 7.76c-.81 0-1.586-.107-2.316-.307a.64.64 0 0 0-.427.03l-1.588.702a.64.64 0 0 1-.898-.566l-.044-1.423a.64.64 0 0 0-.215-.456C.956 12.108 0 10.092 0 7.76m5.546-1.459-2.35 3.728c-.225.358.214.761.551.506l2.525-1.916a.48.48 0 0 1 .578-.002l1.869 1.402a1.2 1.2 0 0 0 1.735-.32l2.35-3.728c.226-.358-.214-.761-.551-.506L9.728 7.381a.48.48 0 0 1-.578.002L7.281 5.98a1.2 1.2 0 0 0-1.735.32z"/>',

		'TikTok' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#000"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M9 0h1.98c.144.715.54 1.617 1.235 2.512C12.895 3.389 13.797 4 15 4v2c-1.753 0-3.07-.814-4-1.829V11a5 5 0 1 1-5-5v2a3 3 0 1 0 3 3z"/>',

		'Pinterest' => '<circle cx="12" cy="12" r="11" fill="#E60023"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M8 0a8 8 0 0 0-2.915 15.452c-.07-.633-.134-1.606.027-2.297.146-.625.938-3.977.938-3.977s-.239-.479-.239-1.187c0-1.113.645-1.943 1.448-1.943.682 0 1.012.512 1.012 1.127 0 .686-.437 1.712-.663 2.663-.188.796.4 1.446 1.185 1.446 1.422 0 2.515-1.5 2.515-3.664 0-1.915-1.377-3.254-3.342-3.254-2.276 0-3.612 1.707-3.612 3.471 0 .688.265 1.425.595 1.826a.24.24 0 0 1 .056.23c-.061.252-.196.796-.222.907-.035.146-.116.177-.268.107-1-.465-1.624-1.926-1.624-3.1 0-2.523 1.834-4.84 5.286-4.84 2.775 0 4.932 1.977 4.932 4.62 0 2.757-1.739 4.976-4.151 4.976-.811 0-1.573-.421-1.834-.919l-.498 1.902c-.181.695-.669 1.566-.995 2.097A8 8 0 1 0 8 0"/>',

		'YouTube' => '<rect x="1" y="4.2" width="22" height="15.6" rx="4.4" fill="#FF0000"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z"/>',

		'X' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#000"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/>',

		'LinkedIn' => '<rect x="1" y="1" width="22" height="22" rx="4.5" fill="#0A66C2"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/>',

		'Snapchat' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#FFFC00"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M15.943 11.526c-.111-.303-.323-.465-.564-.599a1 1 0 0 0-.123-.064l-.219-.111c-.752-.399-1.339-.902-1.746-1.498a3.4 3.4 0 0 1-.3-.531c-.034-.1-.032-.156-.008-.207a.3.3 0 0 1 .097-.1c.129-.086.262-.173.352-.231.162-.104.289-.187.371-.245.309-.216.525-.446.66-.702a1.4 1.4 0 0 0 .069-1.16c-.205-.538-.713-.872-1.329-.872a1.8 1.8 0 0 0-.487.065c.006-.368-.002-.757-.035-1.139-.116-1.344-.587-2.048-1.077-2.61a4.3 4.3 0 0 0-1.095-.881C9.764.216 8.92 0 7.999 0s-1.76.216-2.505.641c-.412.232-.782.53-1.097.883-.49.562-.96 1.267-1.077 2.61-.033.382-.04.772-.036 1.138a1.8 1.8 0 0 0-.487-.065c-.615 0-1.124.335-1.328.873a1.4 1.4 0 0 0 .067 1.161c.136.256.352.486.66.701.082.058.21.14.371.246l.339.221a.4.4 0 0 1 .109.11c.026.053.027.11-.012.217a3.4 3.4 0 0 1-.295.52c-.398.583-.968 1.077-1.696 1.472-.385.204-.786.34-.955.8-.128.348-.044.743.28 1.075q.18.189.409.31a4.4 4.4 0 0 0 1 .4.7.7 0 0 1 .202.09c.118.104.102.26.259.488q.12.178.296.3c.33.229.701.243 1.095.258.355.014.758.03 1.217.18.19.064.389.186.618.328.55.338 1.305.802 2.566.802 1.262 0 2.02-.466 2.576-.806.227-.14.424-.26.609-.321.46-.152.863-.168 1.218-.181.393-.015.764-.03 1.095-.258a1.14 1.14 0 0 0 .336-.368c.114-.192.11-.327.217-.42a.6.6 0 0 1 .19-.087 4.5 4.5 0 0 0 1.014-.404c.16-.087.306-.2.429-.336l.004-.005c.304-.325.38-.709.256-1.047m-1.121.602c-.684.378-1.139.337-1.493.565-.3.193-.122.61-.34.76-.269.186-1.061-.012-2.085.326-.845.279-1.384 1.082-2.903 1.082s-2.045-.801-2.904-1.084c-1.022-.338-1.816-.14-2.084-.325-.218-.15-.041-.568-.341-.761-.354-.228-.809-.187-1.492-.563-.436-.24-.189-.39-.044-.46 2.478-1.199 2.873-3.05 2.89-3.188.022-.166.045-.297-.138-.466-.177-.164-.962-.65-1.18-.802-.36-.252-.52-.503-.402-.812.082-.214.281-.295.49-.295a1 1 0 0 1 .197.022c.396.086.78.285 1.002.338q.04.01.082.011c.118 0 .16-.06.152-.195-.026-.433-.087-1.277-.019-2.066.094-1.084.444-1.622.859-2.097.2-.229 1.137-1.22 2.93-1.22 1.792 0 2.732.987 2.931 1.215.416.475.766 1.013.859 2.098.068.788.009 1.632-.019 2.065-.01.142.034.195.152.195a.4.4 0 0 0 .082-.01c.222-.054.607-.253 1.002-.338a1 1 0 0 1 .197-.023c.21 0 .409.082.49.295.117.309-.04.56-.401.812-.218.152-1.003.638-1.18.802-.184.169-.16.3-.139.466.018.14.413 1.991 2.89 3.189.147.073.394.222-.041.464"/>',

		'WhatsApp' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#25D366"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/>',

		'Google' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#fff" stroke="#e5e7eb"/>'
			. '<path transform="translate(4 4)" fill="#4285F4" d="M15.545 6.558a9.4 9.4 0 0 1 .139 1.626c0 2.434-.87 4.492-2.384 5.885h.002C11.978 15.292 10.158 16 8 16A8 8 0 1 1 8 0a7.7 7.7 0 0 1 5.352 2.082l-2.284 2.284A4.35 4.35 0 0 0 8 3.166c-2.087 0-3.86 1.408-4.492 3.304a4.8 4.8 0 0 0 0 3.063h.003c.635 1.893 2.405 3.301 4.492 3.301 1.078 0 2.004-.276 2.722-.764h-.003a3.7 3.7 0 0 0 1.599-2.431H8v-3.08z"/>',

		'Gmail' => '<rect x="1" y="4.5" width="22" height="15" rx="3" fill="#fff" stroke="#e5e7eb"/>'
			. '<g fill="none" stroke="#EA4335" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M16 20h3a1 1 0 0 0 1 -1v-14a1 1 0 0 0 -1 -1h-3v16"/>'
			. '<path d="M5 20h3v-16h-3a1 1 0 0 0 -1 1v14a1 1 0 0 0 1 1"/>'
			. '<path d="M16 4l-4 4l-4 -4"/><path d="M4 6.5l8 7.5l8 -7.5"/></g>',

		'Reddit' => '<circle cx="12" cy="12" r="11" fill="#FF4500"/>'
			. '<g transform="translate(4 4)" fill="#fff"><path d="M6.167 8a.83.83 0 0 0-.83.83c0 .459.372.84.83.831a.831.831 0 0 0 0-1.661m1.843 3.647c.315 0 1.403-.038 1.976-.611a.23.23 0 0 0 0-.306.213.213 0 0 0-.306 0c-.353.363-1.126.487-1.67.487-.545 0-1.308-.124-1.671-.487a.213.213 0 0 0-.306 0 .213.213 0 0 0 0 .306c.564.563 1.652.61 1.977.61zm.992-2.807c0 .458.373.83.831.83s.83-.381.83-.83a.831.831 0 0 0-1.66 0z"/><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.828-1.165c-.315 0-.602.124-.812.325-.801-.573-1.9-.945-3.121-.993l.534-2.501 1.738.372a.83.83 0 1 0 .83-.869.83.83 0 0 0-.744.468l-1.938-.41a.2.2 0 0 0-.153.028.2.2 0 0 0-.086.134l-.592 2.788c-1.24.038-2.358.41-3.17.992-.21-.2-.496-.324-.81-.324a1.163 1.163 0 0 0-.478 2.224q-.03.17-.029.353c0 1.795 2.091 3.256 4.669 3.256s4.668-1.451 4.668-3.256c0-.114-.01-.238-.029-.353.401-.181.688-.592.688-1.069 0-.65-.525-1.165-1.165-1.165"/></g>',

		'Etsy' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#F1641E"/>'
			. '<g fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M14 12h-5"/>'
			. '<path d="M3 8a5 5 0 0 1 5 -5h8a5 5 0 0 1 5 5v8a5 5 0 0 1 -5 5h-8a5 5 0 0 1 -5 -5l0 -8"/>'
			. '<path d="M15 16h-5a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h5"/></g>',

		'Amazon' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#232F3E"/>'
			. '<path transform="translate(4 4)" fill="#fff" d="M10.813 11.968c.157.083.36.074.5-.05l.005.005a90 90 0 0 1 1.623-1.405c.173-.143.143-.372.006-.563l-.125-.17c-.345-.465-.673-.906-.673-1.791v-3.3l.001-.335c.008-1.265.014-2.421-.933-3.305C10.404.274 9.06 0 8.03 0 6.017 0 3.77.75 3.296 3.24c-.047.264.143.404.316.443l2.054.22c.19-.009.33-.196.366-.387.176-.857.896-1.271 1.703-1.271.435 0 .929.16 1.188.55.264.39.26.91.257 1.376v.432q-.3.033-.621.065c-1.113.114-2.397.246-3.36.67C3.873 5.91 2.94 7.08 2.94 8.798c0 2.2 1.387 3.298 3.168 3.298 1.506 0 2.328-.354 3.489-1.54l.167.246c.274.405.456.675 1.047 1.166ZM6.03 8.431C6.03 6.627 7.647 6.3 9.177 6.3v.57c.001.776.002 1.434-.396 2.133-.336.595-.87.961-1.465.961-.812 0-1.286-.619-1.286-1.533M.435 12.174c2.629 1.603 6.698 4.084 13.183.997.28-.116.475.078.199.431C13.538 13.96 11.312 16 7.57 16 3.832 16 .968 13.446.094 12.386c-.24-.275.036-.4.199-.299z"/>'
			. '<path transform="translate(4 4)" fill="#FF9900" d="M13.828 11.943c.567-.07 1.468-.027 1.645.204.135.176-.004.966-.233 1.533-.23.563-.572.961-.762 1.115s-.333.094-.23-.137c.105-.23.684-1.663.455-1.963-.213-.278-1.177-.177-1.625-.13l-.09.009q-.142.013-.233.024c-.193.021-.245.027-.274-.032-.074-.209.779-.556 1.347-.623"/>',
	];

	return isset( $svg[ $label ] )
		? '<svg class="cbaz-logo" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg[ $label ] . '</svg>'
		: '';
}

/**
 * Logo d'une source, ou pastille à défaut.
 *
 * La correspondance passe par le répertoire des sources, donc
 * « l.instagram.com », « instagram.com » et « ig » donnent tous les
 * trois le même logo.
 */
function cbaz_source_brand( $name ) {
	$entry = function_exists( 'cbaz_source_entry' ) ? cbaz_source_entry( $name ) : null;

	if ( $entry ) {
		$icon = cbaz_source_icon( $entry['label'] );

		if ( $icon ) {
			return $icon;
		}

		// Marque connue mais non dessinée : au moins sa couleur, ce qui
		// vaut mieux qu'un gris tiré au sort.
		if ( ! empty( $entry['color'] ) ) {
			return cbaz_avatar( $entry['label'], $entry['color'] );
		}
	}

	return cbaz_avatar( $name );
}

/**
 * Pastille de source, repli quand la marque est inconnue.
 *
 * Une lettre colorée plutôt qu'un logo : aucune image à charger, et le
 * repère visuel reste stable.
 */
function cbaz_avatar( $name, $couleur = '' ) {
	$name  = trim( (string) $name );
	$index = $name ? ( ord( strtoupper( $name[0] ) ) % 6 ) : 0;
	$style = $couleur ? ' style="background:' . esc_attr( $couleur ) . '"' : '';

	return '<span class="cbaz-avatar cbaz-avatar--' . $index . '"' . $style . '>'
		. esc_html( $name ? strtoupper( $name[0] ) : '?' ) . '</span>';
}

/**
 * Recherche et export au-dessus d'un tableau.
 *
 * La recherche filtre les lignes déjà affichées, dans le navigateur :
 * pas de rechargement, et le tri comme la pagination ne bougent pas
 * sous les doigts.
 */
function cbaz_table_tools( $placeholder, $export = '' ) {
	ob_start();
	?>
	<div class="cbaz-tools">
		<label class="cbaz-search">
			<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
			<input type="search" placeholder="<?php echo esc_attr( $placeholder ); ?>" data-cbaz-search>
		</label>

		<?php if ( $export && cbaz_can( 'exports' ) ) : ?>
			<a class="cbaz-ctrl cbaz-ctrl--mini" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( [ 'action' => 'cbaz_export', 'quoi' => $export, 'periode' => cbaz_range()['preset'], 'du' => cbaz_range()['du'], 'au' => cbaz_range()['au'] ] ), admin_url( 'admin-post.php' ) ), 'cbaz_export' ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 21h16"/></svg>
				<?php echo esc_html__( 'CSV', 'shop-analytics-for-woocommerce' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Pied de tableau : nombre de lignes affichées. */
function cbaz_table_count( $shown, $total, $unit = '' ) {
	$unit = $unit ?: __( 'lignes', 'shop-analytics-for-woocommerce' );
	return sprintf(
		'<p class="cbaz-count" data-cbaz-count data-unit="%1$s">%2$s</p>',
		esc_attr( $unit ),
		/* translators: 1: displayed row count, 2: total row count, 3: row type. */
		esc_html( sprintf( __( '1–%1$d sur %2$d %3$s', 'shop-analytics-for-woocommerce' ), $shown, $total, $unit ) )
	);
}

/** Interrupteur, pour les réglages qui n'ont que deux états. */
function cbaz_toggle( $name, $checked, $label = '' ) {
	ob_start();
	?>
	<label class="cbaz-switch">
		<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
		<span class="cbaz-switch__track"><span class="cbaz-switch__knob"></span></span>
		<?php if ( $label ) : ?><span class="cbaz-switch__label"><?php echo esc_html( $label ); ?></span><?php endif; ?>
	</label>
	<?php
	return ob_get_clean();
}

/** Sous-onglets à l'intérieur d'une carte. */
function cbaz_subtabs( $param, array $items, $default ) {
	$current = sanitize_key( $_GET[ $param ] ?? $default );
	$current = isset( $items[ $current ] ) ? $current : $default;

	echo '<div class="cbaz-subtabs">';

	foreach ( $items as $key => $label ) {
		printf(
			'<a class="cbaz-subtab%s" href="%s">%s</a>',
			$key === $current ? ' is-active' : '',
			esc_url( cbaz_url( [ $param => $key ] ) ),
			esc_html( $label )
		);
	}

	echo '</div>';

	return $current;
}

/** Petite bulle d'explication, au survol. */
function cbaz_hint( $text ) {
	return '<span class="cbaz-hint" title="' . esc_attr( $text ) . '">?</span>';
}

// ══════════════════════════════════════════════════════════════
//  PAGE
// ══════════════════════════════════════════════════════════════

function cbaz_render_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'shop-analytics-for-woocommerce' ) );
	}

	$tab   = cbaz_current_tab();
	$meta  = cbaz_tabs()[ $tab ];
	$range = cbaz_range();
	$no_range = [ 'temps-reel', 'parametres', 'rapports', 'historique', 'licence' ];
	?>
	<div class="wrap cbaz">
		<header class="cbaz-top">
			<div>
				<h1 class="cbaz-title"><?php echo esc_html( $meta[0] ); ?></h1>
				<p class="cbaz-sub"><?php echo esc_html( $meta[1] ); ?></p>
			</div>

			<?php if ( ! in_array( $tab, $no_range, true ) ) : ?>
				<div class="cbaz-controls">
					<div class="cbaz-drop cbaz-drop--range">
						<button type="button" class="cbaz-ctrl" data-cbaz-drop>
							<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
							<?php echo esc_html( cbaz_range_label( $range ) ); ?>
						</button>
						<div class="cbaz-drop__menu" hidden>
							<?php foreach ( cbaz_presets() as $key => $label ) : ?>
								<a href="<?php echo esc_url( cbaz_url( [ 'periode' => $key ] ) ); ?>"
								   class="<?php echo $range['preset'] === $key ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>

							<form class="cbaz-daterange" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
								<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_key( $_GET['page'] ?? 'cbaz' ) ); ?>">
								<input type="hidden" name="periode" value="perso">
								<?php foreach ( cbaz_filters() as $key => $value ) : ?>
									<input type="hidden" name="f_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
								<?php endforeach; ?>
								<?php if ( cbaz_comparing() ) : ?>
									<input type="hidden" name="compare" value="1">
								<?php endif; ?>

								<p class="cbaz-daterange__title"><?php echo esc_html__( 'Plage précise', 'shop-analytics-for-woocommerce' ); ?></p>

								<label><?php echo esc_html__( 'Du', 'shop-analytics-for-woocommerce' ); ?>
									<input type="date" name="du" max="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>"
									       value="<?php echo esc_attr( $range['du'] ? $range['du'] : gmdate( 'Y-m-d', strtotime( $range['from'] ) ) ); ?>">
								</label>
								<label><?php echo esc_html__( 'Au', 'shop-analytics-for-woocommerce' ); ?>
									<input type="date" name="au" max="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>"
									       value="<?php echo esc_attr( $range['au'] ? $range['au'] : gmdate( 'Y-m-d', strtotime( $range['to'] ) ) ); ?>">
								</label>

								<button type="submit" class="cbaz-btn cbaz-btn--mini"><?php echo esc_html__( 'Appliquer', 'shop-analytics-for-woocommerce' ); ?></button>
							</form>
						</div>
					</div>

					<a class="cbaz-ctrl<?php echo cbaz_comparing() ? ' is-on' : ''; ?>"
					   href="<?php echo esc_url( cbaz_comparing() ? cbaz_url( [], [ 'compare' ] ) : cbaz_url( [ 'compare' => 1 ] ) ); ?>">
						<?php echo esc_html( cbaz_comparing() ? __( 'Courbe comparée', 'shop-analytics-for-woocommerce' ) : __( 'Superposer la période précédente', 'shop-analytics-for-woocommerce' ) ); ?>
					</a>

					<a class="cbaz-ctrl" href="<?php echo esc_url( cbaz_url() ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>
						<?php echo esc_html__( 'Actualiser', 'shop-analytics-for-woocommerce' ); ?>
					</a>

					<?php if ( cbaz_can( 'exports' ) ) : ?>
						<a class="cbaz-ctrl cbaz-ctrl--primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( [ 'action' => 'cbaz_export', 'quoi' => $tab, 'periode' => $range['preset'], 'du' => $range['du'], 'au' => $range['au'] ] ), admin_url( 'admin-post.php' ) ), 'cbaz_export' ) ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 21h16"/></svg>
							<?php echo esc_html__( 'Exporter', 'shop-analytics-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php elseif ( 'temps-reel' === $tab ) : ?>
				<div class="cbaz-livepill">
					<span class="cbaz-live__pulse"></span>
					<strong data-cbaz-online><?php echo esc_html( cbaz_int( cbaz_realtime()['online'] ) ); ?></strong>
					<?php echo esc_html__( 'visiteurs actifs maintenant', 'shop-analytics-for-woocommerce' ); ?>
				</div>
			<?php endif; ?>
		</header>

		<?php
		if ( ! in_array( $tab, $no_range, true ) ) {
			cbaz_filter_bar( $range );
		}

		/**
		 * Fichier de vue d'un écran.
		 *
		 * Permet au module Pro de servir ses propres écrans sans que le
		 * cœur ait à connaître leur emplacement.
		 *
		 * @param string $view Chemin absolu du fichier.
		 * @param string $tab  Écran demandé.
		 */
		$view = apply_filters( 'cbaz_view', CBAZ_DIR . 'views/' . $tab . '.php', $tab );

		if ( file_exists( $view ) ) {
			include $view;
		}
		?>

		<?php cbaz_hidden_notice_hint(); ?>

		<footer class="cbaz-footer">
			<?php if ( cbaz_hpos() ) : ?>
				<?php /* translators: %1$s: plugin version. */ printf( esc_html__( 'Pluginect Analytics %1$s · données hébergées sur ton serveur · stockage WooCommerce moderne', 'shop-analytics-for-woocommerce' ), esc_html( CBAZ_VERSION ) ); ?>
			<?php else : ?>
				<?php /* translators: %1$s: plugin version. */ printf( esc_html__( 'Pluginect Analytics %1$s · données hébergées sur ton serveur', 'shop-analytics-for-woocommerce' ), esc_html( CBAZ_VERSION ) ); ?>
			<?php endif; ?>
		</footer>
	</div>
	<?php
}

/** La comparaison à la période précédente est-elle demandée ? */
function cbaz_comparing() {
	return ! empty( $_GET['compare'] );
}

// ══════════════════════════════════════════════════════════════
//  ACTIONS
// ══════════════════════════════════════════════════════════════

add_action( 'admin_post_cbaz_settings', 'cbaz_save_settings' );
function cbaz_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'shop-analytics-for-woocommerce' ) );
	}

	check_admin_referer( 'cbaz_settings' );

	$current = cbaz_opt();
	$volet   = sanitize_key( $_POST['volet'] ?? 'general' );

	/*
	 * Chaque volet ne poste que ses propres champs. Sans fusion, ouvrir
	 * « Exclusions » et enregistrer effacerait la fenêtre d'attribution
	 * réglée dans « RGPD » — une perte silencieuse, la pire espèce.
	 */
	$posted = [
		'enabled'          => empty( $_POST['enabled'] ) ? 0 : 1,
		'track_events'     => empty( $_POST['track_events'] ) ? 0 : 1,
		'retention_months' => max( 1, min( 120, (int) ( $_POST['retention_months'] ?? 24 ) ) ),
		'history_years'    => max( 1, min( 20, (int) ( $_POST['history_years'] ?? 10 ) ) ),
		'exclude_roles'    => array_map( 'sanitize_key', (array) ( $_POST['exclude_roles'] ?? [] ) ),
		'exclude_paths'    => sanitize_textarea_field( wp_unslash( $_POST['exclude_paths'] ?? '' ) ),
		'exclude_bots'     => empty( $_POST['exclude_bots'] ) ? 0 : 1,
		'attribution_days' => max( 0, min( 90, (int) ( $_POST['attribution_days'] ?? 30 ) ) ),
		'delete_on_uninstall' => empty( $_POST['delete_on_uninstall'] ) ? 0 : 1,
	];

	$fields = [
		'general' => [ 'enabled', 'track_events' ],
		'rgpd'    => [ 'attribution_days', 'retention_months', 'history_years' ],
		'exclus'  => [ 'exclude_roles', 'exclude_paths', 'exclude_bots' ],
		'donnees' => [ 'delete_on_uninstall' ],
	];

	$keep = $fields[ $volet ] ?? [];

	foreach ( $posted as $key => $value ) {
		if ( in_array( $key, $keep, true ) ) {
			$current[ $key ] = $value;
		}
	}

	update_option( 'cbaz_settings', $current, false );
	update_option( 'cbaz_saved_at', time(), false );

	wp_safe_redirect( add_query_arg( [ 'page' => 'cbaz-parametres', 'volet' => $volet, 'ok' => 1 ], admin_url( 'admin.php' ) ) );
	exit;
}

add_action( 'admin_post_cbaz_campaign', 'cbaz_handle_campaign' );
function cbaz_handle_campaign() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'shop-analytics-for-woocommerce' ) );
	}

	check_admin_referer( 'cbaz_campaign' );

	$back = [ 'page' => 'cbaz-campagnes' ];

	if ( ! empty( $_POST['delete'] ) ) {
		cbaz_delete_campaign( (int) $_POST['delete'] );
		$back['msg'] = 'supprimee';
	} else {
		$result = cbaz_save_campaign( wp_unslash( $_POST ), (int) ( $_POST['id'] ?? 0 ) );

		if ( is_wp_error( $result ) ) {
			// On renvoie sur le formulaire, pas sur la liste : sinon la
			// saisie est perdue et le message d'erreur s'affiche loin
			// du champ qui l'a provoqué.
			$back['msg']      = 'incomplete';
			$back['nouvelle'] = 1;
		} else {
			$back['msg'] = 'enregistree';
		}
	}

	wp_safe_redirect( add_query_arg( $back, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Export CSV des campagnes.
 *
 * Un tableau de bord se consulte ; un budget se discute dans un
 * tableur. L'export reprend exactement les colonnes affichées, pour
 * qu'aucun chiffre ne diffère entre les deux.
 */

/**
 * Favoris de rapports.
 *
 * Stockés par utilisateur : deux personnes qui pilotent la boutique ne
 * suivent pas les mêmes chiffres, et un favori partagé deviendrait le
 * favori de personne.
 */
add_action( 'admin_post_cbaz_favori', 'cbaz_toggle_favori' );
function cbaz_toggle_favori() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'shop-analytics-for-woocommerce' ) );
	}

	check_admin_referer( 'cbaz_favori' );

	$key  = sanitize_key( $_GET['rapport'] ?? '' );
	$user = get_current_user_id();
	$favs = (array) get_user_meta( $user, 'cbaz_favoris', true );

	$favs = in_array( $key, $favs, true )
		? array_values( array_diff( $favs, [ $key ] ) )
		: array_merge( $favs, [ $key ] );

	update_user_meta( $user, 'cbaz_favoris', $favs );

	wp_safe_redirect( add_query_arg( [ 'page' => 'cbaz-rapports' ], admin_url( 'admin.php' ) ) );
	exit;
}

/** Rafraîchissement du temps réel, sans recharger la page. */
add_action( 'wp_ajax_cbaz_realtime', 'cbaz_ajax_realtime' );
function cbaz_ajax_realtime() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	check_ajax_referer( 'cbaz_realtime' );

	$live = cbaz_realtime();

	// Le compteur reste gratuit ; le détail évènement par évènement, non.
	// Le masquer côté affichage seulement laisserait l'endpoint exploitable.
	if ( ! cbaz_can( 'realtime_details' ) ) {
		wp_send_json_success( [
			'online' => $live['online'],
			'feed'   => [],
		] );
	}

	wp_send_json_success( [
		'online' => $live['online'],
		'feed'   => array_map( function ( $r ) {
			$ev = cbaz_event_label( $r->kind, $r->object_id, $r->value, $r->path );

			return [
				'time'   => wp_date( 'H:i', cbaz_ts( $r->at ) ),
				'label'  => $ev['label'],
				'tone'   => $ev['tone'],
				'what'   => $ev['detail'] ? $ev['detail'] : ( $r->title ? $r->title : $r->path ),
				'flag'   => cbaz_country_flag( $r->country ),
				'country' => cbaz_country_name( $r->country ),
				'device' => $r->device,
			];
		}, $live['feed'] ),
	] );
}
