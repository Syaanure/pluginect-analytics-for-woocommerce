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

	$content = '<p>' . esc_html__( 'Shop Analytics for WooCommerce conserve localement des visites pseudonymes quotidiennes, les pages consultées, des événements de navigation et des informations techniques (préfixe réseau utilisé sans être stocké, navigateur, appareil, langue, résolution et pays estimé). Une visite convertie est reliée à la commande WooCommerce. Aucun cookie n’est déposé par défaut ; la mémoire d’attribution facultative dépose un cookie de provenance signé. Les durées de conservation sont configurables dans Analytics → Paramètres.', 'shop-analytics-for-woocommerce' ) . '</p>';

	wp_add_privacy_policy_content( 'Shop Analytics for WooCommerce', wp_kses_post( $content ) );
}

// ══════════════════════════════════════════════════════════════
//  MENU
// ══════════════════════════════════════════════════════════════

function cbaz_tabs() {
	return [
		'overview'     => [ "Vue d'ensemble", 'Analysez les performances de votre boutique.' ],
		'temps-reel'   => [ 'Temps réel', 'Ce qui se passe sur votre boutique en ce moment.' ],
		'acquisition'  => [ 'Acquisition', "Comprenez d'où viennent vos visiteurs et quelles sources convertissent." ],
		'comportement' => [ 'Comportement', 'Ce que vos visiteurs consultent et comment ils naviguent.' ],
		'ecommerce'    => [ 'E-commerce', 'Du visiteur à la commande : où se perdent vos conversions.' ],
		'produits'     => [ 'Produits', 'Performance de chaque produit du catalogue WooCommerce.' ],
		'campagnes'    => [ 'Campagnes', 'Suivez les performances de vos campagnes UTM.' ],
		'geographie'   => [ 'Géographie', 'Localisation des visiteurs et performance commerciale par pays.' ],
		'visiteurs'    => [ 'Visiteurs', 'Appareils, technologies et fidélité de votre audience.' ],
		'visites'      => [ 'Parcours', 'Le parcours de chaque visite, page par page.' ],
		'parametres'   => [ 'Paramètres', 'Configuration du suivi, de la confidentialité et de la synchronisation.' ],
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

	add_menu_page( 'Analytics', 'Analytics', 'manage_woocommerce', 'cbaz', 'cbaz_render_page', 'dashicons-chart-area', 56 );

	foreach ( $tabs as $slug => $meta ) {
		add_submenu_page(
			'cbaz',
			$meta[0] . ' — Analytics',
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
	$dependencies = [];

	if ( 'cbaz-geographie' === sanitize_key( $_GET['page'] ?? '' ) ) {
		// Le maillage du monde est volumineux et ne sert qu'au globe.
		wp_enqueue_script( 'cbaz-world', CBAZ_URL . 'assets/world.js', [], CBAZ_VERSION, true );
		$dependencies[] = 'cbaz-world';
	}

	wp_enqueue_script( 'cbaz-admin', CBAZ_URL . 'assets/admin.js', $dependencies, CBAZ_VERSION . '.' . filemtime( CBAZ_DIR . 'assets/admin.js' ), true );
}

// ══════════════════════════════════════════════════════════════
//  MESSAGES DE WORDPRESS
//
//  Les bandeaux restent visibles par défaut : certains portent une alerte
//  de sécurité ou de maintenance que l'extension ne doit pas supprimer.
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

	if ( ! apply_filters( 'cbaz_hide_admin_notices', false ) ) {
		return;
	}

	foreach ( [ 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ] as $hook ) {
		remove_all_actions( $hook );
	}
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
		return '<span class="cbaz-delta cbaz-delta--none" title="Aucune donnée sur la période précédente">nouveau</span>';
	}

	$value = (float) $value;

	if ( 0.0 === $value ) {
		return '<span class="cbaz-delta cbaz-delta--flat">stable <span class="cbaz-delta__arrow" aria-hidden="true">→</span></span>';
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
		'money'    => [ 3, [ 'ca ', 'chiffre', 'panier moyen', 'valeur', 'budget', 'investi', 'retour', 'coût', 'prix', 'revenu' ] ],
		'clock'    => [ 6, [ 'durée', 'délai', 'temps' ] ],
		'cart'     => [ 4, [ 'commande', 'conversion', 'achat', 'vendus', 'vente' ] ],
		'users'    => [ 1, [ 'visiteur', 'audience', 'nouveau', 'fidélité' ] ],
		'page'     => [ 2, [ 'page' ] ],
		'flag'     => [ 5, [ 'rebond', 'fuite', 'sortie' ] ],
		'box'      => [ 3, [ 'produit', 'référence', 'article', 'catalogue' ] ],
		'tag'      => [ 4, [ 'campagne' ] ],
		'globe'    => [ 6, [ 'pays', 'provenance', 'source', 'contexte' ] ],
		'path'     => [ 1, [ 'parcours', 'visite', 'chemin', 'issue' ] ],
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
		return '<p class="cbaz-empty">Aucune visite sur la période.</p>';
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
		echo '<li class="cbaz-empty">Rien à afficher.</li>';
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
		return '<p class="cbaz-empty">Pas encore de données sur cette période.</p>';
	}

	$defs = [
		'sessions'  => 'Visites',
		'pageviews' => 'Pages vues',
		'revenue'   => "Chiffre d'affaires",
		'orders'    => 'Commandes',
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
		<svg viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" role="img" aria-label="Évolution sur la période">
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
		return '<p class="cbaz-empty">Aucune donnée sur la période.</p>';
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

	return '<div class="cbaz-chart"><svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Comparaison par canal">' . $svg . '</svg></div>'
		. '<div class="cbaz-chart__legend">'
		. '<span class="cbaz-key cbaz-key--first">' . esc_html( $legend[0] ) . '</span>'
		. '<span class="cbaz-key cbaz-key--last">' . esc_html( $legend[1] ) . '</span>'
		. '</div>';
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
					<strong>− <?php echo esc_html( cbaz_int( $lost ) ); ?></strong>
					abandons (<?php echo esc_html( number_format_i18n( $step['drop'], 1 ) ); ?> %)
					<?php if ( $previous ) : ?>
						<?php echo cbaz_delta_badge( $drop_delta, true ); // phpcs:ignore ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<div class="cbaz-step">
				<span class="cbaz-step__n"><?php echo (int) ( $i + 1 ); ?></span>
				<span class="cbaz-step__label"><?php echo esc_html( $step['label'] ); ?></span>
				<span class="cbaz-step__share">
					<?php echo 0 === $i ? "Point d'entrée" : esc_html( number_format_i18n( $step['pct'], 1 ) . ' % des visites' ); ?>
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
				<p class="cbaz-leaks__note"><?php echo esc_html( cbaz_int( $leak['lost'] ) ); ?> personnes perdues à cette étape</p>
			</li>
		<?php endforeach; ?>
		<?php if ( ! $leaks ) : ?><li class="cbaz-empty">Pas encore de parcours mesuré.</li><?php endif; ?>
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
		return '<p class="cbaz-empty">Aucune donnée sur la période.</p>';
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
		return '<p class="cbaz-empty">Aucune donnée sur la période.</p>';
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
 * Logos de marque, dessinés dans le plugin.
 *
 * La version précédente allait chercher le favicon officiel de chaque
 * réseau. C'était séduisant sur le papier — toujours à jour, rien à
 * maintenir — et inutilisable en pratique : un bloqueur de contenu,
 * un pare-feu d'entreprise ou une simple protection anti-hotlink
 * suffit à faire échouer la requête, et l'administration retombait
 * silencieusement sur la pastille à la lettre. C'est exactement ce
 * qu'on voyait.
 *
 * Ces marques sont donc dessinées ici, en SVG, servies par le site
 * lui-même. Aucune requête sortante, rien à bloquer, rien à charger :
 * elles s'affichent hors ligne comme derrière n'importe quel filtre.
 * Le prix à payer est de les retoucher si une identité change — c'est
 * arrivé une fois en dix ans pour Twitter.
 */
function cbaz_source_icon( $label ) {
	$svg = [

		'Instagram' => '<defs><linearGradient id="cbaz-ig" x1="0" y1="1" x2="1" y2="0">'
			. '<stop offset="0" stop-color="#FEDA75"/><stop offset=".25" stop-color="#FA7E1E"/>'
			. '<stop offset=".5" stop-color="#D62976"/><stop offset=".75" stop-color="#962FBF"/>'
			. '<stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs>'
			. '<rect x="1" y="1" width="22" height="22" rx="6" fill="url(#cbaz-ig)"/>'
			. '<rect x="5.6" y="5.6" width="12.8" height="12.8" rx="4" fill="none" stroke="#fff" stroke-width="1.7"/>'
			. '<circle cx="12" cy="12" r="3.3" fill="none" stroke="#fff" stroke-width="1.7"/>'
			. '<circle cx="16.7" cy="7.3" r="1.05" fill="#fff"/>',

		'Facebook' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#1877F2"/>'
			. '<path fill="#fff" d="M15.6 13.1l.46-3.1h-3V8c0-.85.42-1.68 1.76-1.68h1.38V3.68s-1.25-.21-2.45-.21c-2.5 0-4.14 1.51-4.14 4.26V10H6.85v3.1h2.76V21h3.4v-7.9h2.59z"/>',

		'Messenger' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#0084FF"/>'
			. '<path fill="#fff" d="M12 4.5c-4.2 0-7.5 3.1-7.5 7.2 0 2.35 1.08 4.43 2.78 5.79V20l2.55-1.4c.68.19 1.4.29 2.17.29 4.2 0 7.5-3.1 7.5-7.19S16.2 4.5 12 4.5zm.76 9.66l-1.93-2.05-3.76 2.05 4.13-4.38 1.98 2.05 3.71-2.05-4.13 4.38z"/>',

		'TikTok' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#000"/>'
			. '<path fill="#25F4EE" d="M12.4 4.5h2.4c.15 1.3 1 2.4 2.3 2.7v2.4c-.9 0-1.8-.25-2.55-.7v4.9a4.55 4.55 0 1 1-4.55-4.55c.2 0 .4.01.6.05v2.5a2.1 2.1 0 1 0 1.5 2V4.5z"/>'
			. '<path fill="#FE2C55" d="M13.6 4.5H16c.15 1.3 1 2.4 2.3 2.7v2.4c-.9 0-1.8-.25-2.55-.7v4.9a4.55 4.55 0 1 1-4.55-4.55c.2 0 .4.01.6.05v2.5a2.1 2.1 0 1 0 1.5 2V4.5z"/>'
			. '<path fill="#fff" d="M13 4.5h2.4c.15 1.3 1 2.4 2.3 2.7v2.4c-.9 0-1.8-.25-2.55-.7v4.9a4.55 4.55 0 1 1-4.55-4.55c.2 0 .4.01.6.05v2.5a2.1 2.1 0 1 0 1.5 2V4.5z"/>',

		'Pinterest' => '<circle cx="12" cy="12" r="11" fill="#E60023"/>'
			. '<path fill="#fff" d="M12.3 5.3c-3.9 0-5.9 2.6-5.9 4.9 0 1.4.5 2.5 1.7 3 .2.08.38-.01.43-.22l.16-.63c.05-.2.03-.28-.11-.45-.33-.4-.54-.92-.54-1.65 0-2.13 1.6-4.03 4.16-4.03 2.27 0 3.52 1.38 3.52 3.22 0 2.42-1.07 4.46-2.66 4.46-.88 0-1.53-.72-1.32-1.61.25-1.06.74-2.2.74-2.96 0-.68-.37-1.25-1.13-1.25-.9 0-1.62.93-1.62 2.17 0 .79.27 1.33.27 1.33s-.92 3.87-1.08 4.56c-.32 1.36-.05 3.02-.03 3.19.01.1.14.13.2.05.09-.11 1.2-1.48 1.58-2.85.1-.39.61-2.38.61-2.38.3.58 1.19 1.08 2.14 1.08 2.81 0 4.72-2.56 4.72-5.99 0-2.59-2.2-5.02-5.54-5.02z"/>',

		'YouTube' => '<rect x="1" y="4.2" width="22" height="15.6" rx="4.4" fill="#FF0000"/>'
			. '<path fill="#fff" d="M10.1 8.4l6.1 3.6-6.1 3.6z"/>',

		'X' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#000"/>'
			. '<path fill="#fff" d="M16.5 5.4h2.4l-5.24 6 6.16 9h-4.83l-3.78-5.53L6.8 20.4H4.4l5.6-6.42L4.1 5.4h4.95l3.42 5.02L16.5 5.4zm-.84 13.06h1.33L8.4 6.85H6.97l8.69 11.61z"/>',

		'LinkedIn' => '<rect x="1" y="1" width="22" height="22" rx="4.5" fill="#0A66C2"/>'
			. '<circle cx="7.1" cy="7.2" r="1.75" fill="#fff"/>'
			. '<rect x="5.6" y="10" width="3" height="8.6" fill="#fff"/>'
			. '<path fill="#fff" d="M10.6 10h2.87v1.18c.4-.74 1.38-1.43 2.68-1.43 2.37 0 3.35 1.4 3.35 3.87v4.98h-3v-4.4c0-1.18-.44-1.87-1.47-1.87-1.08 0-1.56.73-1.56 1.87v4.4h-2.87V10z"/>',

		'Snapchat' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#FFFC00"/>'
			. '<path fill="#fff" stroke="#1a1a1a" stroke-width=".55" stroke-linejoin="round" d="M12 4.9c2.15 0 3.62 1.55 3.68 3.7.02.68-.05 1.32-.05 1.56.02.3.35.34.6.24.3-.12.73-.14.92.05.24.24.1.63-.24.83-.34.2-1.27.39-1.32.83-.05.49.88 2.05 2.44 2.68.34.15.3.49-.05.63-.34.15-.88.2-1.03.44-.1.2 0 .54-.24.68-.3.15-.98-.1-1.66.05-.59.13-1.32 1.07-2.83 1.07s-2.25-.94-2.83-1.07c-.68-.15-1.37.1-1.66-.05-.24-.15-.15-.49-.24-.68-.15-.24-.68-.29-1.03-.44-.34-.15-.39-.49-.05-.63 1.56-.63 2.49-2.2 2.44-2.68-.05-.44-.98-.63-1.32-.83-.34-.2-.49-.59-.24-.83.2-.2.63-.17.92-.05.24.1.58.05.6-.24 0-.24-.07-.88-.05-1.56C8.38 6.45 9.85 4.9 12 4.9z"/>',

		'WhatsApp' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#25D366"/>'
			. '<path fill="#fff" d="M12 5.6a6.35 6.35 0 0 0-5.42 9.66L5.7 18.4l3.5-.86A6.35 6.35 0 1 0 12 5.6zm3.66 8.93c-.15.43-.88.82-1.22.87-.31.05-.7.07-1.13-.07-.26-.08-.6-.19-1.02-.37-1.8-.78-2.97-2.6-3.06-2.72-.09-.12-.73-.97-.73-1.86s.46-1.32.63-1.5c.16-.17.36-.21.48-.21h.34c.11 0 .26-.04.4.31.15.36.51 1.24.55 1.33.05.09.08.19.02.31-.06.12-.09.19-.17.3l-.26.3c-.09.09-.18.19-.08.36.1.18.44.72.94 1.17.64.57 1.18.75 1.35.84.18.09.28.07.38-.05.11-.13.44-.51.55-.68.12-.18.24-.15.4-.09.15.06 1.02.48 1.2.57.17.09.29.13.33.2.05.07.05.42-.1.86z"/>',

		'Google' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#fff" stroke="#e5e7eb"/>'
			. '<path fill="#4285F4" d="M19.6 12.18c0-.55-.05-1.08-.14-1.58H12v3h4.26a3.64 3.64 0 0 1-1.58 2.39v1.98h2.56c1.5-1.38 2.36-3.4 2.36-5.79z"/>'
			. '<path fill="#34A853" d="M12 20c2.14 0 3.93-.71 5.24-1.92l-2.56-1.98c-.71.48-1.62.76-2.68.76-2.06 0-3.8-1.39-4.43-3.26H4.93v2.05A8 8 0 0 0 12 20z"/>'
			. '<path fill="#FBBC05" d="M7.57 13.6a4.8 4.8 0 0 1 0-3.06V8.49H4.93a8 8 0 0 0 0 7.16l2.64-2.05z"/>'
			. '<path fill="#EA4335" d="M12 7.28c1.16 0 2.21.4 3.03 1.18l2.27-2.27C15.93 4.93 14.14 4.2 12 4.2a8 8 0 0 0-7.07 4.29l2.64 2.05C8.2 8.67 9.94 7.28 12 7.28z"/>',

		'Gmail' => '<rect x="1" y="4.5" width="22" height="15" rx="3" fill="#fff" stroke="#e5e7eb"/>'
			. '<path fill="#EA4335" d="M1 7.5v-.3a2.7 2.7 0 0 1 4.3-2.16L12 10l6.7-4.96A2.7 2.7 0 0 1 23 7.2v.3l-11 8.15L1 7.5z"/>'
			. '<path fill="#34A853" d="M1 7.5l4 2.96v9.04H2.5A1.5 1.5 0 0 1 1 18V7.5z"/>'
			. '<path fill="#4285F4" d="M23 7.5v10.5a1.5 1.5 0 0 1-1.5 1.5H19v-9.04l4-2.96z"/>',

		'Reddit' => '<circle cx="12" cy="12" r="11" fill="#FF4500"/>'
			. '<circle cx="12" cy="13.2" r="6.2" fill="#fff"/>'
			. '<circle cx="9.7" cy="12.9" r="1.15" fill="#FF4500"/>'
			. '<circle cx="14.3" cy="12.9" r="1.15" fill="#FF4500"/>'
			. '<path fill="none" stroke="#FF4500" stroke-width="1.1" stroke-linecap="round" d="M9.6 15.7c1.4.95 3.4.95 4.8 0"/>'
			. '<circle cx="18" cy="7.4" r="1.5" fill="#fff"/>'
			. '<path fill="none" stroke="#fff" stroke-width="1.1" stroke-linecap="round" d="M12.4 7.2l1-3.1 3.2.7"/>',

		'Etsy' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#F1641E"/>'
			. '<path fill="#fff" d="M9.3 6.6h8.1l.3 2.9h-.9c-.4-1.5-.9-2-2.2-2h-2.5c-.4 0-.5.1-.5.5v3.5h1.9c1 0 1.3-.3 1.5-1.4h.9v3.9h-.9c-.2-1.1-.5-1.4-1.5-1.4h-1.9v3.9c0 .4.1.5.5.5h2.6c1.4 0 2-.6 2.5-2.3h.9l-.4 3.2H9.3v-.9c1.2-.1 1.4-.3 1.4-1.2V8.7c0-.9-.2-1.1-1.4-1.2v-.9z"/>',

		'Amazon' => '<rect x="1" y="1" width="22" height="22" rx="6" fill="#232F3E"/>'
			. '<path fill="#FF9900" d="M4.6 16.4c2.3 1.7 5 2.5 7.7 2.5 1.9 0 4-.4 5.9-1.3.3-.13.55.19.27.4-1.7 1.3-4.1 2-6.2 2-2.9 0-5.6-1.1-7.7-2.9-.17-.15-.02-.35.06-.7z"/>'
			. '<path fill="#FF9900" d="M18.4 15.5c-.3-.4-1.9-.2-2.6-.1-.2.03-.24-.16-.05-.3.9-.63 2.4-.45 2.57-.24.18.22-.05 1.75-.9 2.48-.13.11-.26.05-.2-.1.2-.5.63-1.34.44-1.74z"/>'
			. '<path fill="#fff" d="M11.9 6.4c-1.6 0-3.1.6-3.5 2.6l1.9.2c.18-.9.7-1.2 1.4-1.2.4 0 .85.15 1.05.5.23.4.2.95.2 1.4v.25c-1.1.12-2.5.2-3.5.64-1.15.5-1.95 1.5-1.95 3 0 1.9 1.2 2.85 2.75 2.85 1.3 0 2-.3 3-1.35.33.48.44.7 1.05 1.2.14.07.31.07.43-.05l1.3-1.15c.15-.13.13-.32.02-.48-.36-.5-.75-.9-.75-1.83v-3.1c0-1.3.1-2.5-.87-3.4-.77-.73-2.03-.98-3-.98zm.35 5.3c.35 0 .7 0 .7.01v.45c0 .8.04 1.47-.37 2.18-.33.58-.86.94-1.44.94-.8 0-1.27-.61-1.27-1.51 0-1.78 1.6-2.07 2.38-2.07z"/>',
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
				CSV
			</a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Pied de tableau : nombre de lignes affichées. */
function cbaz_table_count( $shown, $total, $unit = 'lignes' ) {
	return '<p class="cbaz-count" data-cbaz-count data-unit="' . esc_attr( $unit ) . '">'
		. esc_html( sprintf( '1–%d sur %d %s', $shown, $total, $unit ) ) . '</p>';
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
		wp_die( 'Accès refusé.' );
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

								<p class="cbaz-daterange__title">Plage précise</p>

								<label>Du
									<input type="date" name="du" max="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>"
									       value="<?php echo esc_attr( $range['du'] ? $range['du'] : gmdate( 'Y-m-d', strtotime( $range['from'] ) ) ); ?>">
								</label>
								<label>Au
									<input type="date" name="au" max="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>"
									       value="<?php echo esc_attr( $range['au'] ? $range['au'] : gmdate( 'Y-m-d', strtotime( $range['to'] ) ) ); ?>">
								</label>

								<button type="submit" class="cbaz-btn cbaz-btn--mini">Appliquer</button>
							</form>
						</div>
					</div>

					<a class="cbaz-ctrl<?php echo cbaz_comparing() ? ' is-on' : ''; ?>"
					   href="<?php echo esc_url( cbaz_comparing() ? cbaz_url( [], [ 'compare' ] ) : cbaz_url( [ 'compare' => 1 ] ) ); ?>">
						<?php echo cbaz_comparing() ? 'Courbe comparée' : 'Superposer la période précédente'; ?>
					</a>

					<a class="cbaz-ctrl" href="<?php echo esc_url( cbaz_url() ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>
						Actualiser
					</a>

					<?php if ( cbaz_can( 'exports' ) ) : ?>
						<a class="cbaz-ctrl cbaz-ctrl--primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( [ 'action' => 'cbaz_export', 'quoi' => $tab, 'periode' => $range['preset'], 'du' => $range['du'], 'au' => $range['au'] ] ), admin_url( 'admin-post.php' ) ), 'cbaz_export' ) ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 21h16"/></svg>
							Exporter
						</a>
					<?php endif; ?>
				</div>
			<?php elseif ( 'temps-reel' === $tab ) : ?>
				<div class="cbaz-livepill">
					<span class="cbaz-live__pulse"></span>
					<strong data-cbaz-online><?php echo esc_html( cbaz_int( cbaz_realtime()['online'] ) ); ?></strong>
					visiteurs actifs maintenant
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

		<footer class="cbaz-footer">
			CamiBijoux Analytics <?php echo esc_html( CBAZ_VERSION ); ?> · données hébergées sur ton serveur
			<?php if ( cbaz_hpos() ) : ?> · stockage WooCommerce moderne<?php endif; ?>
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
		wp_die( 'Accès refusé.' );
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
		wp_die( 'Accès refusé.' );
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
		wp_die( 'Accès refusé.' );
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
