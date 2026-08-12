<?php
/**
 * Collecte.
 *
 * Fonctionnement : le navigateur envoie une balise à un point REST, le
 * serveur calcule une empreinte non réversible à partir de l'adresse IP
 * et du navigateur, puis oublie l'adresse. Deux pages vues portant la
 * même empreinte à moins de trente minutes d'écart appartiennent à la
 * même visite.
 *
 * Pourquoi ça compte : aucun identifiant persistant, aucune adresse IP
 * conservée, aucune transmission à un tiers. Le sel change chaque jour,
 * donc une même personne revenant demain est un visiteur différent aux
 * yeux du système — impossible de la suivre dans le temps.
 *
 * Une seule exception, réglable et désactivée d'un cran : la mémoire
 * d'attribution. Elle dépose un cookie qui ne contient QUE la
 * provenance — source, support, campagne — et jamais d'identifiant. Il
 * ne dit pas qui tu es, seulement d'où tu viens. Voir
 * cbaz_remember_attribution() pour le détail et la raison.
 */

defined( 'ABSPATH' ) || exit;

// ══════════════════════════════════════════════════════════════
//  BALISE CÔTÉ NAVIGATEUR
// ══════════════════════════════════════════════════════════════

add_action( 'wp_enqueue_scripts', 'cbaz_enqueue_tracker' );
function cbaz_enqueue_tracker() {
	if ( ! cbaz_should_track() ) {
		return;
	}

	wp_enqueue_script(
		'cbaz-track',
		CBAZ_URL . 'assets/track.js',
		[],
		CBAZ_VERSION . '.' . filemtime( CBAZ_DIR . 'assets/track.js' ),
		true
	);

	wp_localize_script( 'cbaz-track', 'cbazTrack', [
		'url'     => esc_url_raw( rest_url( 'cbaz/v1/hit' ) ),
		'events'  => (int) cbaz_opt( 'track_events' ),
		// Le produit consulté ne peut être connu que du serveur :
		// l'adresse seule ne dit pas quel identifiant elle porte.
		'product' => ( function_exists( 'is_product' ) && is_product() ) ? get_queried_object_id() : 0,
		'search'  => is_search() ? get_search_query() : '',
		'cart'    => function_exists( 'is_cart' ) && is_cart() ? 1 : 0,
		'checkout'=> function_exists( 'is_checkout' ) && is_checkout()
			&& ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ? 1 : 0,
	] );
}

/** Faut-il mesurer cette page, pour cette personne ? */
function cbaz_should_track() {
	if ( ! cbaz_opt( 'enabled' ) || is_admin() || wp_doing_ajax() || is_feed() || is_preview() ) {
		return false;
	}

	// L'équipe fausse les chiffres : une journée de mise au point sur
	// la boutique gonflerait les pages produits sans aucune vente.
	$excluded = (array) cbaz_opt( 'exclude_roles' );

	if ( is_user_logged_in() && array_intersect( wp_get_current_user()->roles, $excluded ) ) {
		return false;
	}

	$path = cbaz_current_path();

	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) cbaz_opt( 'exclude_paths' ) ) ) ) as $rule ) {
		if ( '' !== $rule && 0 === strpos( $path, $rule ) ) {
			return false;
		}
	}

	return true;
}

function cbaz_current_path() {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

	return $path ? substr( $path, 0, 190 ) : '/';
}

// ══════════════════════════════════════════════════════════════
//  POINT DE COLLECTE
// ══════════════════════════════════════════════════════════════

add_action( 'rest_api_init', 'cbaz_register_routes' );
function cbaz_register_routes() {
	register_rest_route( 'cbaz/v1', '/hit', [
		'methods'             => 'POST',
		'callback'            => 'cbaz_receive_hit',
		'permission_callback' => '__return_true',
	] );
}

function cbaz_receive_hit( WP_REST_Request $request ) {
	if ( ! cbaz_opt( 'enabled' ) || ( cbaz_opt( 'exclude_bots' ) && cbaz_is_bot() ) ) {
		return new WP_REST_Response( [ 'ok' => false ], 204 );
	}

	if ( cbaz_flooding() ) {
		return new WP_REST_Response( [ 'ok' => false ], 429 );
	}

	$path    = substr( sanitize_text_field( (string) $request->get_param( 'p' ) ), 0, 190 );
	$title   = substr( sanitize_text_field( (string) $request->get_param( 't' ) ), 0, 190 );
	$ref     = (string) $request->get_param( 'r' );
	$event   = sanitize_key( (string) $request->get_param( 'e' ) );
	$object  = (int) $request->get_param( 'o' );
	$value   = (float) $request->get_param( 'v' );
	// Tableau venu de l'extérieur : on n'en garde que ce qu'on sait
	// lire. Sans borne, un appel pourrait envoyer dix mille entrées et
	// faire travailler le serveur pour rien.
	$query   = array_slice( (array) $request->get_param( 'q' ), 0, 20, true );
	$label   = substr( sanitize_text_field( (string) $request->get_param( 'l' ) ), 0, 190 );
	$screen  = substr( preg_replace( '/[^0-9x]/', '', (string) $request->get_param( 's' ) ), 0, 12 );
	$lang    = substr( preg_replace( '/[^a-zA-Z-]/', '', (string) $request->get_param( 'g' ) ), 0, 12 );

	/*
	 * Le nom d'événement vient d'un endpoint public. Une liste positive
	 * empêche un appel externe d'inventer un achat et de polluer le tunnel.
	 * `purchase` reste exclusivement écrit côté serveur depuis la commande.
	 */
	$public_events = [ 'view_item', 'view_cart', 'begin_checkout', 'search', 'page_time' ];

	if ( $event && ! in_array( $event, $public_events, true ) ) {
		return new WP_REST_Response( [ 'ok' => false ], 400 );
	}

	$object = max( 0, $object );
	$value  = 'page_time' === $event ? max( 0, min( 1800, $value ) ) : 0;

	if ( '' === $path ) {
		$path = '/';
	}

	$session_id = cbaz_current_session( $path, $ref, $query, compact( 'screen', 'lang' ), ! $event );

	if ( ! $session_id ) {
		return new WP_REST_Response( [ 'ok' => false ], 204 );
	}

	if ( $event ) {
		cbaz_record_event( $session_id, $event, $object, $value, $label );
	} else {
		cbaz_record_view( $session_id, $path, $title );
	}

	return new WP_REST_Response( [ 'ok' => true ], 200 );
}

// ══════════════════════════════════════════════════════════════
//  EMPREINTE ET VISITE
// ══════════════════════════════════════════════════════════════

/**
 * Secret propre à l'installation.
 *
 * Il ne tourne pas, contrairement au sel du jour, et n'a pas à être
 * connu : il sert seulement à ce qu'une empreinte ne puisse pas être
 * recalculée par quelqu'un qui connaîtrait la méthode et disposerait
 * du sel. C'est une couche de plus, pas la protection principale.
 */
function cbaz_secret() {
	$secret = get_option( 'cbaz_secret' );

	if ( ! $secret ) {
		$secret = wp_generate_password( 64, true, true );
		add_option( 'cbaz_secret', $secret, '', false );
	}

	return $secret;
}

/**
 * Adresse IP réduite avant tout calcul.
 *
 * C'est le point le plus important du fichier. Sans troncature, une
 * empreinte reste RECALCULABLE : qui connaît l'adresse IP d'une
 * personne, sa signature de navigateur et le sel du jour retrouve
 * exactement ses visites. Le sel est en base ; l'IP se lit dans
 * n'importe quel journal de serveur. L'attaque était donc à la portée
 * de quiconque accède à la machine.
 *
 * En ne gardant que le réseau — les trois premiers octets en IPv4, les
 * trois premiers groupes en IPv6 — le calcul cesse de désigner
 * quelqu'un : des centaines d'abonnés partagent le même préfixe, et le
 * même résultat. On apprend « quelqu'un de ce réseau », jamais « cette
 * personne-là ».
 *
 * Ce que ça coûte : deux visiteurs du même réseau, avec le même
 * navigateur, dans la même demi-heure, comptent pour une seule. Sur
 * une boutique, c'est rare — et c'était déjà le cas derrière les
 * réseaux mobiles, qui partagent une adresse entre des milliers
 * d'abonnés.
 */
function cbaz_narrow_ip( $ip ) {
	if ( '' === $ip ) {
		return '';
	}

	/*
	 * La coupe se fait sur la forme BINAIRE, jamais sur le texte : une
	 * adresse IPv6 s'écrit de plusieurs façons — « ::1 » compresse six
	 * groupes de zéros — et découper la chaîne au caractère « : »
	 * donnait des résultats faux sur les formes compressées.
	 */
	$binaire = @inet_pton( $ip );

	if ( false === $binaire ) {
		// Adresse illisible : on ne devine pas, on hache ce qu'on a.
		return substr( (string) $ip, 0, 45 );
	}

	// Trois octets en IPv4 (/24), six en IPv6 (/48).
	$garde   = 4 === strlen( $binaire ) ? 3 : 6;
	$binaire = substr( $binaire, 0, $garde ) . str_repeat( "\0", strlen( $binaire ) - $garde );

	return (string) inet_ntop( $binaire );
}

/**
 * Empreinte du visiteur.
 *
 * Trois ingrédients : le réseau (jamais l'adresse complète), la
 * signature du navigateur, et un sel qui change chaque nuit. Le sel
 * n'est jamais conservé au-delà : deux jours de suite, la même
 * personne produit deux empreintes sans lien possible.
 *
 * Aucune adresse IP n'est écrite nulle part, à aucun moment.
 */
function cbaz_visitor_hash() {
	$salt = get_transient( 'cbaz_salt' );

	if ( ! $salt ) {
		$salt = wp_generate_password( 40, false );
		set_transient( 'cbaz_salt', $salt, DAY_IN_SECONDS );
	}

	$ip = cbaz_narrow_ip( $_SERVER['REMOTE_ADDR'] ?? '' );
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

	return substr( hash_hmac( 'sha256', $salt . '|' . $ip . '|' . $ua, cbaz_secret() ), 0, 32 );
}

/**
 * Débit anormal en provenance d'un même réseau.
 *
 * Le point de collecte est public : il DOIT l'être, c'est un
 * navigateur anonyme qui l'appelle. Rien n'empêche donc d'y envoyer
 * des appels en boucle. Le plafond par visite ne suffit pas, puisqu'il
 * suffit de changer de signature de navigateur à chaque appel pour
 * ouvrir une visite de plus — et faire grossir la table sans fin.
 *
 * On compte donc les visites NOUVELLES par réseau et par heure. Le
 * seuil est volontairement haut : deux cents visites en une heure
 * depuis un même /24, c'est déjà beaucoup pour une boutique, et les
 * réseaux mobiles qui partagent une adresse entre des milliers
 * d'abonnés restent largement sous la barre.
 *
 * Le compteur vit dans un transient nommé par un condensé du réseau :
 * aucune adresse n'est écrite, même temporairement.
 */
function cbaz_flooding() {
	$reseau = cbaz_narrow_ip( $_SERVER['REMOTE_ADDR'] ?? '' );

	if ( '' === $reseau ) {
		return false;
	}

	$cle    = 'cbaz_rate_' . substr( hash_hmac( 'sha256', $reseau, cbaz_secret() ), 0, 20 );
	$compte = (int) get_transient( $cle );

	if ( $compte >= apply_filters( 'cbaz_hits_per_hour', 600 ) ) {
		return true;
	}

	set_transient( $cle, $compte + 1, HOUR_IN_SECONDS );

	return false;
}

/** Visite en cours, ou nouvelle visite si la précédente a expiré. */
function cbaz_current_session( $path, $referrer, array $query, array $client = [], $is_pageview = true ) {
	global $wpdb;

	$table = cbaz_table( 'sessions' );
	$hash  = cbaz_visitor_hash();
	$now   = current_time( 'mysql' );
	$since = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - CBAZ_SESSION_GAP );

	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, pageviews FROM {$table} WHERE visitor_hash = %s AND last_seen >= %s ORDER BY last_seen DESC LIMIT 1",
		$hash,
		$since
	) );
	$lock_name = 'cbaz-session-' . $hash;
	$locked    = false;

	/* La page vue et ses événements partent presque simultanément. Deux
	 * requêtes qui ne trouvent encore rien créeraient deux visites. Un verrou
	 * très court sérialise uniquement la création pour cette empreinte. */
	if ( ! $existing ) {
		$lock_result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock_name ) );

		if ( '0' === (string) $lock_result ) {
			return 0;
		}

		$locked = '1' === (string) $lock_result;

		if ( $locked ) {
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, pageviews FROM {$table} WHERE visitor_hash = %s AND last_seen >= %s ORDER BY last_seen DESC LIMIT 1",
				$hash,
				$since
			) );
		}
	}

	/*
	 * Plafond de sécurité. Le point de collecte est public par nature
	 * — il doit l'être, c'est un navigateur qui l'appelle — et rien
	 * n'empêche d'y envoyer des milliers d'appels en boucle. Passé
	 * cinq cents pages sur une même visite, on cesse d'enregistrer :
	 * aucune vraie visite n'atteint ce chiffre, et la table ne peut
	 * plus être gonflée indéfiniment.
	 */
	if ( $is_pageview && $existing && (int) $existing->pageviews >= 500 ) {
		if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }
		return 0;
	}

	if ( $existing ) {
		$update = [ 'last_seen' => $now ];

		if ( $is_pageview ) {
			$update['pageviews'] = (int) $existing->pageviews + 1;
			$update['exit_path'] = $path;
		}

		$wpdb->update( $table, $update, [ 'id' => (int) $existing->id ] );
		if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }

		return (int) $existing->id;
	}

	$attribution = cbaz_attribution( $referrer, $query );

	if ( $attribution['campaign'] ) {
		$remembered = cbaz_remember_attribution( $attribution );

		if ( $remembered ) {
			$attribution = array_merge( $attribution, [
				'first_source'   => $remembered['first_source'],
				'first_medium'   => $remembered['first_medium'],
				'first_campaign' => $remembered['first_campaign'],
			] );
		}
	} elseif ( ! cbaz_attr_window() ) {
		// Réglage à zéro : on profite du passage pour nettoyer.
		cbaz_forget_attribution();
	} elseif ( 'direct' === $attribution['source'] ) {
		// Retour direct après un clic de campagne récent : c'est la
		// campagne qui a fait venir, pas le hasard.
		$remembered = cbaz_recall_attribution();

		if ( $remembered ) {
			$attribution = array_merge( $attribution, $remembered );
		}
	}

	$agent = cbaz_parse_agent();

	// Déjà vue aujourd'hui sous une autre visite : la personne revient,
	// elle n'est plus « nouvelle ».
	$seen_today = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s",
		$hash
	) );

	// Premier contact : la toute première visite connue de cette
	// empreinte garde sa provenance, ce qui permet de distinguer la
	// campagne qui a fait découvrir la boutique de celle qui a
	// déclenché l'achat.
	$first = $wpdb->get_row( $wpdb->prepare(
		"SELECT source, medium, campaign FROM {$table} WHERE visitor_hash = %s ORDER BY started_at ASC LIMIT 1",
		$hash
	) );

	$wpdb->insert( $table, [
		'first_source'   => $attribution['first_source'] ?? ( $first ? $first->source : $attribution['source'] ),
		'first_medium'   => $attribution['first_medium'] ?? ( $first ? $first->medium : $attribution['medium'] ),
		'first_campaign' => $attribution['first_campaign'] ?? ( $first ? $first->campaign : $attribution['campaign'] ),
		'visitor_hash'  => $hash,
		'started_at'    => $now,
		'last_seen'     => $now,
		'pageviews'     => $is_pageview ? 1 : 0,
		'entry_path'    => $path,
		'exit_path'     => $path,
		'referrer_host' => $attribution['host'],
		'source'        => $attribution['source'],
		'medium'        => $attribution['medium'],
		'campaign'      => $attribution['campaign'],
		'term'          => $attribution['term'],
		'content'       => $attribution['content'],
		'country'       => cbaz_country(),
		'device'        => $agent['device'],
		'browser'       => $agent['browser'],
		'os'            => $agent['os'],
		'screen'        => $client['screen'] ?? '',
		'lang'          => $client['lang'] ?? '',
		'is_new'        => $seen_today ? 0 : 1,
	] );

	if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }

	return (int) $wpdb->insert_id;
}

function cbaz_record_view( $session_id, $path, $title ) {
	global $wpdb;

	$wpdb->insert( cbaz_table( 'views' ), [
		'session_id' => $session_id,
		'path'       => $path,
		'title'      => $title,
		'viewed_at'  => current_time( 'mysql' ),
	] );
}

function cbaz_record_event( $session_id, $name, $object_id = 0, $value = 0, $label = '' ) {
	global $wpdb;

	/*
	 * Le nom du produit est retenu au moment de l'évènement, pas
	 * seulement son identifiant. Un produit renommé, dépublié ou
	 * supprimé laisse sinon un « Consultation produit » sans rien
	 * derrière — et c'est justement l'historique ancien qu'on relit.
	 */
	if ( '' === $label && $object_id && in_array( $name, [ 'view_item', 'add_to_cart' ], true )
		&& function_exists( 'wc_get_product' ) ) {

		$produit = wc_get_product( $object_id );

		if ( $produit ) {
			$label = $produit->get_name();
		}
	}

	$wpdb->insert( cbaz_table( 'events' ), [
		'session_id' => $session_id,
		'name'       => substr( $name, 0, 40 ),
		'object_id'  => (int) $object_id,
		'value'      => (float) $value,
		'label'      => substr( (string) $label, 0, 190 ),
		'created_at' => current_time( 'mysql' ),
	] );

	if ( 'page_time' === $name ) {
		$seconds = max( 0, min( 1800, (int) $value ) );
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . cbaz_table( 'sessions' ) . ' SET engaged_seconds = LEAST(86400, engaged_seconds + %d) WHERE id = %d',
			$seconds,
			(int) $session_id
		) );
	}
}

/**
 * Ajout au panier confirmé par WooCommerce.
 *
 * Le hook est commun au panier classique et à la Store API des blocs. Il
 * évite les doubles comptages des thèmes qui émettent plusieurs événements
 * JavaScript et n'enregistre jamais un clic dont l'ajout a finalement échoué.
 */
add_action( 'woocommerce_add_to_cart', 'cbaz_track_add_to_cart', 30, 6 );
function cbaz_track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = [], $cart_item_data = [] ) {
	if ( ! cbaz_opt( 'enabled' ) || ! cbaz_opt( 'track_events' ) || cbaz_is_bot() || cbaz_flooding() ) {
		return;
	}

	$excluded = (array) cbaz_opt( 'exclude_roles' );

	if ( is_user_logged_in() && array_intersect( wp_get_current_user()->roles, $excluded ) ) {
		return;
	}

	$referer = wp_get_raw_referer();
	$path    = $referer ? wp_parse_url( $referer, PHP_URL_PATH ) : '/';
	$query   = [];

	if ( $referer ) {
		parse_str( (string) wp_parse_url( $referer, PHP_URL_QUERY ), $query );
	}

	$session_id = cbaz_current_session( $path ?: '/', $referer ?: '', $query, [], false );

	if ( $session_id ) {
		cbaz_record_event( $session_id, 'add_to_cart', $product_id );
	}
}

// ══════════════════════════════════════════════════════════════
//  PROVENANCE
// ══════════════════════════════════════════════════════════════

/**
 * Mémoire d'attribution.
 *
 * LE point qui fait ou défait un suivi de campagne. Sans mémoire, un
 * clic le lundi et un achat le jeudi comptent comme deux histoires
 * sans lien : la vente est mise au crédit du « direct » et la campagne
 * paraît stérile. Sur des bijoux, où l'on hésite volontiers quelques
 * jours, cela sous-estime absolument tout.
 *
 * Ce petit cookie ne contient QUE la provenance — source, support,
 * campagne — et aucun identifiant : il ne dit pas qui tu es, seulement
 * d'où tu viens. Il reste propre au site, n'est jamais lu ailleurs, et
 * sa durée est réglable. À zéro, il n'est pas déposé du tout et l'on
 * revient à une attribution limitée à la visite.
 */
const CBAZ_ATTR_COOKIE = 'cbaz_attr';

function cbaz_attr_window() {
	return max( 0, (int) cbaz_opt( 'attribution_days' ) );
}

function cbaz_remember_attribution( array $attr ) {
	$days = cbaz_attr_window();

	if ( ! $days ) {
		// Mémoire désactivée : on efface un cookie éventuellement posé
		// du temps où elle l'était. Sans ça, il subsisterait chez les
		// visiteurs jusqu'à sa date d'expiration, et la promesse
		// « aucun cookie » serait fausse pour elles pendant un mois.
		cbaz_forget_attribution();

		return null;
	}

	if ( headers_sent() || '' === $attr['campaign'] . $attr['source'] ) {
		return null;
	}

	$previous = cbaz_recall_attribution();
	$data     = [
		's' => $attr['source'],
		'm' => $attr['medium'],
		'c' => $attr['campaign'],
		't' => $attr['term'],
		'k' => $attr['content'],
		'f' => $previous['first_source'] ?? $attr['source'],
		'g' => $previous['first_medium'] ?? $attr['medium'],
		'p' => $previous['first_campaign'] ?? $attr['campaign'],
		'd' => time(),
	];

	/*
	 * La provenance influe sur le CA attribué. Sans signature, chacun
	 * pourrait fabriquer ce cookie et créditer arbitrairement une campagne.
	 */
	$data['h'] = hash_hmac( 'sha256', wp_json_encode( $data ), cbaz_secret() );
	$payload   = wp_json_encode( $data );

	setcookie( CBAZ_ATTR_COOKIE, $payload, [
		'expires'  => time() + $days * DAY_IN_SECONDS,
		'path'     => COOKIEPATH ? COOKIEPATH : '/',
		'domain'   => COOKIE_DOMAIN,
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );

	return [
		'host'           => '',
		'source'         => (string) $data['s'],
		'medium'         => (string) $data['m'],
		'campaign'       => (string) $data['c'],
		'term'           => (string) $data['t'],
		'content'        => (string) $data['k'],
		'first_source'   => (string) $data['f'],
		'first_medium'   => (string) $data['g'],
		'first_campaign' => (string) $data['p'],
	];
}

/** Efface le cookie de provenance, s'il existe. */
function cbaz_forget_attribution() {
	if ( headers_sent() || empty( $_COOKIE[ CBAZ_ATTR_COOKIE ] ) ) {
		return;
	}

	setcookie( CBAZ_ATTR_COOKIE, '', [
		'expires' => time() - DAY_IN_SECONDS,
		'path'    => COOKIEPATH ? COOKIEPATH : '/',
		'domain'  => COOKIE_DOMAIN,
	] );

	unset( $_COOKIE[ CBAZ_ATTR_COOKIE ] );
}

function cbaz_recall_attribution() {
	$days = cbaz_attr_window();

	if ( ! $days || empty( $_COOKIE[ CBAZ_ATTR_COOKIE ] ) ) {
		return null;
	}

	$data = json_decode( wp_unslash( $_COOKIE[ CBAZ_ATTR_COOKIE ] ), true );

	if ( ! is_array( $data ) || empty( $data['c'] ) || empty( $data['h'] ) || ! is_string( $data['h'] ) ) {
		return null;
	}

	$signed = [
		's' => (string) ( $data['s'] ?? '' ),
		'm' => (string) ( $data['m'] ?? '' ),
		'c' => (string) ( $data['c'] ?? '' ),
		't' => (string) ( $data['t'] ?? '' ),
		'k' => (string) ( $data['k'] ?? '' ),
		'f' => (string) ( $data['f'] ?? '' ),
		'g' => (string) ( $data['g'] ?? '' ),
		'p' => (string) ( $data['p'] ?? '' ),
		'd' => (int) ( $data['d'] ?? 0 ),
	];
	$valid  = hash_hmac( 'sha256', wp_json_encode( $signed ), cbaz_secret() );

	if ( ! hash_equals( $valid, $data['h'] ) ) {
		return null;
	}

	// Un cookie qui traîne au-delà de la fenêtre choisie ne doit plus
	// peser : le navigateur devrait l'avoir supprimé, on n'en dépend pas.
	if ( $signed['d'] <= 0 || ( time() - $signed['d'] ) > $days * DAY_IN_SECONDS ) {
		return null;
	}

	return [
		'host'           => '',
		'source'         => $signed['s'],
		'medium'         => $signed['m'],
		'campaign'       => $signed['c'],
		'term'           => $signed['t'],
		'content'        => $signed['k'],
		'first_source'   => $signed['f'],
		'first_medium'   => $signed['g'],
		'first_campaign' => $signed['p'],
	];
}

/**
 * D'où vient la visite.
 *
 * Les paramètres UTM l'emportent sur le référent : si une personne
 * arrive par un lien de campagne, c'est la campagne qui compte, même
 * si le clic vient d'Instagram.
 */
function cbaz_attribution( $referrer, array $query ) {
	$out = [
		'host'     => '',
		'source'   => 'direct',
		// « direct » plutôt que « (none) » : une visite sans référent
		// n'a pas un support absent, elle a le support direct.
		'medium'   => 'direct',
		'campaign' => '',
		'term'     => '',
		'content'  => '',
	];

	$host = $referrer ? wp_parse_url( $referrer, PHP_URL_HOST ) : '';

	if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$out['host']   = substr( preg_replace( '/^www\./', '', $host ), 0, 120 );
		$out['source'] = cbaz_canon_source( $out['host'] );
		$out['medium'] = cbaz_guess_medium( $out['host'] );
	}

	/*
	 * ?utm=rentree-2026 — la forme courte.
	 *
	 * Un seul paramètre dans le lien diffusé ; le reste (source,
	 * support, contenu) est lu sur la fiche de campagne. C'est ce qui
	 * permet d'écrire camibijoux.fr/?utm=rentree-2026 dans une story
	 * plutôt qu'une adresse de deux cents caractères.
	 */
	if ( ! empty( $query['utm'] ) ) {
		$ref   = substr( sanitize_text_field( (string) $query['utm'] ), 0, 120 );
		$sheet = function_exists( 'cbaz_campaign_by_ref' ) ? cbaz_campaign_by_ref( $ref ) : null;

		if ( $sheet ) {
			$out['source']   = $sheet->source;
			$out['medium']   = $sheet->medium ? $sheet->medium : 'campaign';
			$out['campaign'] = $sheet->campaign;
			$out['term']     = $sheet->term;
			$out['content']  = $sheet->content;
		} else {
			// Référence inconnue : plutôt que de la perdre, on la garde
			// telle quelle. Une campagne lancée avant sa fiche, ou un
			// lien composé à la main, reste ainsi mesurée.
			$out['campaign'] = cbaz_slug_utm( $ref );
			$out['medium']   = 'campaign';
		}
	}

	/*
	 * Les paramètres UTM complets restent reconnus : les régies
	 * publicitaires et les plateformes d'emailing les ajoutent seules,
	 * et ils l'emportent sur la forme courte quand les deux coexistent.
	 */
	$map = [
		'utm_source'   => 'source',
		'utm_medium'   => 'medium',
		'utm_campaign' => 'campaign',
		'utm_term'     => 'term',
		'utm_content'  => 'content',
	];

	foreach ( $map as $param => $key ) {
		if ( ! empty( $query[ $param ] ) ) {
			$out[ $key ] = substr( sanitize_text_field( (string) $query[ $param ] ), 0, 120 );
		}
	}

	// « ig », « IG », « instagram.com » : la même chose écrite de trois
	// façons doit finir sur la même ligne de rapport.
	$out['source'] = cbaz_canon_source( $out['source'] );

	if ( '' === $out['medium'] || '(none)' === $out['medium'] ) {
		$out['medium'] = 'direct' === $out['source'] ? 'direct' : cbaz_guess_medium( $out['source'] );
	}

	return $out;
}

/**
 * Répertoire des sources connues.
 *
 * Une même provenance arrive sous dix écritures : Instagram passe par
 * « instagram.com » en lien direct, « l.instagram.com » depuis la bio,
 * « ig » quand on l'écrit à la main dans un lien de campagne. Sans
 * table de correspondance, les rapports affichent trois lignes là où
 * il n'y a qu'un canal, et chacune avec un tiers des chiffres.
 *
 * Une entrée décrit tout d'un canal — son nom lisible, son support,
 * et le domaine dont on tire le logo — pour que ces trois informations
 * ne puissent plus diverger.
 *
 * - hosts : domaines, sous-domaines compris (« l.instagram.com » suit
 *   « instagram.com » sans avoir à l'écrire).
 * - regex : pour les marques à cent extensions — google.fr, google.be…
 * - alias : ce qu'on écrit à la main dans un utm_source.
 */
function cbaz_source_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map = [
		// Les messageries d'abord : mail.google.com est un e-mail
		// ouvert, pas une recherche Google.
		[ 'label' => 'Gmail', 'color' => '#EA4335',      'medium' => 'email',    'domain' => 'google.com',    'hosts' => [ 'mail.google.com' ], 'alias' => [ 'gmail' ] ],
		[ 'label' => 'Outlook', 'color' => '#0F6CBD',    'medium' => 'email',    'domain' => 'outlook.com',   'hosts' => [ 'outlook.com', 'outlook.live.com', 'outlook.office.com', 'live.com', 'hotmail.com' ], 'alias' => [ 'hotmail' ] ],
		[ 'label' => 'Yahoo Mail', 'color' => '#6001D2', 'medium' => 'email',    'domain' => 'yahoo.com',     'hosts' => [ 'mail.yahoo.com' ], 'alias' => [] ],
		[ 'label' => 'Brevo', 'color' => '#0B996E',      'medium' => 'email',    'domain' => 'brevo.com',     'hosts' => [ 'brevo.com', 'sendinblue.com' ], 'alias' => [ 'sendinblue' ] ],
		[ 'label' => 'Mailchimp', 'color' => '#FFE01B',  'medium' => 'email',    'domain' => 'mailchimp.com', 'hosts' => [ 'mailchimp.com', 'list-manage.com' ], 'alias' => [] ],
		[ 'label' => 'Newsletter', 'color' => '#3d434e', 'medium' => 'email',    'domain' => '',              'hosts' => [], 'alias' => [ 'newsletter', 'email', 'e-mail', 'mail', 'emailing', 'infolettre' ] ],

		// Réseaux sociaux.
		[ 'label' => 'Instagram', 'color' => '#D62976',  'medium' => 'social',   'domain' => 'instagram.com', 'hosts' => [ 'instagram.com', 'ig.me', 'instagr.am' ], 'alias' => [ 'ig', 'insta', 'instagram-bio', 'instagram-story', 'story', 'stories' ] ],
		[ 'label' => 'Facebook', 'color' => '#1877F2',   'medium' => 'social',   'domain' => 'facebook.com',  'hosts' => [ 'facebook.com', 'fb.me', 'fb.com', 'fbcdn.net' ], 'alias' => [ 'fb', 'meta', 'facebook-ads', 'fb-ads' ] ],
		[ 'label' => 'Messenger', 'color' => '#0084FF',  'medium' => 'social',   'domain' => 'messenger.com', 'hosts' => [ 'messenger.com' ], 'alias' => [] ],
		[ 'label' => 'Threads', 'color' => '#101010',    'medium' => 'social',   'domain' => 'threads.net',   'hosts' => [ 'threads.net', 'threads.com' ], 'alias' => [] ],
		[ 'label' => 'TikTok', 'color' => '#010101',     'medium' => 'social',   'domain' => 'tiktok.com',    'hosts' => [ 'tiktok.com' ], 'alias' => [ 'tt', 'tiktok-ads' ] ],
		[ 'label' => 'Pinterest', 'color' => '#E60023',  'medium' => 'social',   'domain' => 'pinterest.com', 'hosts' => [ 'pin.it' ], 'regex' => '#(^|\.)pinterest\.[a-z.]{2,}$#', 'alias' => [ 'pin' ] ],
		[ 'label' => 'YouTube', 'color' => '#FF0000',    'medium' => 'social',   'domain' => 'youtube.com',   'hosts' => [ 'youtube.com', 'youtu.be' ], 'alias' => [ 'yt' ] ],
		[ 'label' => 'X', 'color' => '#101010',          'medium' => 'social',   'domain' => 'x.com',         'hosts' => [ 'x.com', 'twitter.com', 't.co' ], 'alias' => [ 'tw', 'twitter' ] ],
		[ 'label' => 'LinkedIn', 'color' => '#0A66C2',   'medium' => 'social',   'domain' => 'linkedin.com',  'hosts' => [ 'linkedin.com', 'lnkd.in' ], 'alias' => [] ],
		[ 'label' => 'Snapchat', 'color' => '#d9b800',   'medium' => 'social',   'domain' => 'snapchat.com',  'hosts' => [ 'snapchat.com' ], 'alias' => [ 'snap' ] ],
		[ 'label' => 'WhatsApp', 'color' => '#25D366',   'medium' => 'social',   'domain' => 'whatsapp.com',  'hosts' => [ 'whatsapp.com', 'wa.me' ], 'alias' => [ 'wa' ] ],
		[ 'label' => 'Reddit', 'color' => '#FF4500',     'medium' => 'social',   'domain' => 'reddit.com',    'hosts' => [ 'reddit.com', 'redd.it' ], 'alias' => [] ],
		[ 'label' => 'Bluesky', 'color' => '#0285FF',    'medium' => 'social',   'domain' => 'bsky.app',      'hosts' => [ 'bsky.app' ], 'alias' => [] ],

		// Moteurs de recherche.
		[ 'label' => 'Google', 'color' => '#4285F4',     'medium' => 'organic',  'domain' => 'google.com',    'hosts' => [ 'googleadservices.com', 'googlesyndication.com', 'googleusercontent.com' ], 'regex' => '#(^|\.)google\.[a-z.]{2,}$#', 'alias' => [ 'google-ads', 'googleads', 'adwords', 'gads' ] ],
		[ 'label' => 'Bing', 'color' => '#008373',       'medium' => 'organic',  'domain' => 'bing.com',      'hosts' => [ 'bing.com' ], 'alias' => [] ],
		[ 'label' => 'DuckDuckGo', 'color' => '#DE5833', 'medium' => 'organic',  'domain' => 'duckduckgo.com','hosts' => [ 'duckduckgo.com' ], 'alias' => [ 'ddg' ] ],
		[ 'label' => 'Ecosia', 'color' => '#0F8A4C',     'medium' => 'organic',  'domain' => 'ecosia.org',    'hosts' => [ 'ecosia.org' ], 'alias' => [] ],
		[ 'label' => 'Qwant', 'color' => '#F5325B',      'medium' => 'organic',  'domain' => 'qwant.com',     'hosts' => [ 'qwant.com' ], 'alias' => [] ],
		[ 'label' => 'Yahoo', 'color' => '#6001D2',      'medium' => 'organic',  'domain' => 'yahoo.com',     'hosts' => [], 'regex' => '#(^|\.)yahoo\.[a-z.]{2,}$#', 'alias' => [] ],
		[ 'label' => 'Yandex', 'color' => '#FC3F1D',     'medium' => 'organic',  'domain' => 'yandex.com',    'hosts' => [], 'regex' => '#(^|\.)yandex\.[a-z.]{2,}$#', 'alias' => [] ],

		// Places de marché.
		[ 'label' => 'Etsy', 'color' => '#F1641E',       'medium' => 'referral', 'domain' => 'etsy.com',      'hosts' => [ 'etsy.com' ], 'alias' => [] ],
		[ 'label' => 'Amazon', 'color' => '#FF9900',     'medium' => 'referral', 'domain' => 'amazon.com',    'hosts' => [], 'regex' => '#(^|\.)amazon\.[a-z.]{2,}$#', 'alias' => [] ],
		[ 'label' => 'eBay', 'color' => '#E53238',       'medium' => 'referral', 'domain' => 'ebay.com',      'hosts' => [], 'regex' => '#(^|\.)ebay\.[a-z.]{2,}$#', 'alias' => [] ],
	];

	return $map;
}

/**
 * L'entrée du répertoire qui correspond à une écriture donnée.
 *
 * Les domaines sont comparés par SUFFIXE et les alias à l'identique :
 * « l.instagram.com » doit tomber sur Instagram, mais « ig » ne doit
 * surtout pas attraper « craigslist ».
 */
function cbaz_source_entry( $raw ) {
	$key = strtolower( trim( remove_accents( (string) $raw ) ) );
	$key = preg_replace( '#^https?://#', '', $key );
	$key = preg_replace( '#/.*$#', '', $key );
	$key = preg_replace( '/^www\./', '', $key );

	if ( '' === $key ) {
		return null;
	}

	foreach ( cbaz_source_map() as $entry ) {
		if ( $key === strtolower( $entry['label'] ) || in_array( $key, $entry['alias'], true ) ) {
			return $entry;
		}

		foreach ( $entry['hosts'] as $host ) {
			if ( $key === $host || substr( $key, - strlen( '.' . $host ) ) === '.' . $host ) {
				return $entry;
			}
		}

		if ( ! empty( $entry['regex'] ) && preg_match( $entry['regex'], $key ) ) {
			return $entry;
		}
	}

	return null;
}

/**
 * Le nom lisible d'une source.
 *
 * Inconnue, elle est conservée telle quelle, simplement débarrassée du
 * « www. » : un site référent garde son nom de domaine, qui reste la
 * meilleure description de lui-même.
 */
function cbaz_canon_source( $raw ) {
	$raw = trim( (string) $raw );

	if ( '' === $raw || 'direct' === strtolower( $raw ) || '(direct)' === strtolower( $raw ) ) {
		return 'direct';
	}

	$entry = cbaz_source_entry( $raw );

	return $entry ? $entry['label'] : preg_replace( '/^www\./', '', $raw );
}

/** Classement grossier mais suffisant pour lire un rapport. */
function cbaz_guess_medium( $host ) {
	$entry = cbaz_source_entry( $host );

	return $entry ? $entry['medium'] : 'referral';
}

/**
 * Pays.
 *
 * On se contente de ce que l'hébergeur ou le proxy fournit déjà. Aucune
 * base GeoIP n'est embarquée : elle pèserait plusieurs mégaoctets et
 * demanderait une mise à jour mensuelle, pour un gain modeste ici.
 */
function cbaz_country() {
	// 1. L'en-tête d'un proxy ou d'un CDN, quand il y en a un.
	foreach ( [ 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY' ] as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$code = strtoupper( substr( sanitize_text_field( $_SERVER[ $key ] ), 0, 2 ) );

			if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code ) {
				return $code;
			}
		}
	}

	/*
	 * 2. La géolocalisation de WooCommerce.
	 *
	 * Beaucoup de boutiques l'ont déjà activée pour calculer la TVA ou
	 * les frais de port : la base MaxMind est alors présente et à jour.
	 * Autant s'en servir plutôt que d'en embarquer une seconde.
	 */
	if ( class_exists( 'WC_Geolocation' ) ) {
		$geo = WC_Geolocation::geolocate_ip( '', false, true );

		if ( ! empty( $geo['country'] ) && preg_match( '/^[A-Z]{2}$/', $geo['country'] ) ) {
			return $geo['country'];
		}
	}

	// 3. Le pays déclaré dans le panier, s'il y en a un.
	if ( function_exists( 'WC' ) && WC()->customer ) {
		$code = WC()->customer->get_billing_country();

		if ( $code ) {
			return substr( $code, 0, 2 );
		}
	}

	// 4. La langue du navigateur, en dernier recours.
	return cbaz_country_from_language();
}

/**
 * Pays déduit de la langue déclarée.
 *
 * « fr-BE » donne la Belgique, « nl-NL » les Pays-Bas. C'est une
 * approximation — une Française installée à Londres garde souvent
 * fr-FR — mais elle vaut mieux qu'une colonne vide, et elle ne coûte
 * rien : l'en-tête est déjà là.
 *
 * Une langue sans région n'est pas devinée : « fr » tout court ne dit
 * pas si l'on est en France, en Belgique ou au Québec.
 */
function cbaz_country_from_language() {
	$header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

	if ( ! $header ) {
		return '';
	}

	// fr-FR,fr;q=0.9,en-US;q=0.8 — on prend la première région trouvée.
	if ( preg_match( '/[a-z]{2}-([A-Z]{2})/', $header, $m ) ) {
		return strtoupper( $m[1] );
	}

	return '';
}

function cbaz_parse_agent() {
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

	$device = 'desktop';

	if ( preg_match( '/iPad|Tablet|PlayBook|Silk/i', $ua ) ) {
		$device = 'tablet';
	} elseif ( preg_match( '/Mobi|Android|iPhone|iPod/i', $ua ) ) {
		$device = 'mobile';
	}

	$browsers = [ 'Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Safari' => 'Safari', 'Firefox' => 'Firefox' ];
	$browser  = 'Autre';

	foreach ( $browsers as $needle => $label ) {
		if ( false !== strpos( $ua, $needle ) ) {
			$browser = $label;
			break;
		}
	}

	$systems = [ 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'Mac OS X' => 'macOS', 'Windows' => 'Windows', 'Linux' => 'Linux' ];
	$os      = 'Autre';

	foreach ( $systems as $needle => $label ) {
		if ( false !== strpos( $ua, $needle ) ) {
			$os = $label;
			break;
		}
	}

	return compact( 'device', 'browser', 'os' );
}

/**
 * Est-ce une machine ?
 *
 * Deux signaux suffisent en pratique. D'abord l'absence de navigateur
 * déclaré — un vrai navigateur en annonce toujours un. Ensuite les
 * mots que les robots inscrivent eux-mêmes dans leur signature : la
 * quasi-totalité s'annonce honnêtement, parce que c'est leur intérêt
 * d'être reconnus et autorisés.
 *
 * La liste couvre les familles plutôt que les noms : « bot » attrape
 * Googlebot, Bingbot, GPTBot et les mille autres sans avoir à les
 * énumérer. Elle n'a donc pas à être tenue à jour.
 *
 * Reste que la mesure passe par du JavaScript : les robots qui n'en
 * exécutent pas — l'immense majorité — n'ont jamais rien déclenché.
 * Ce filtre vise les autres : navigateurs sans tête, aspirateurs de
 * contenu, sondes de supervision, générateurs d'aperçus de lien.
 */
function cbaz_is_bot() {
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

	if ( '' === $ua ) {
		return true;
	}

	$signatures = [
		'bot', 'crawler', 'crawling', 'spider', 'scraper', 'slurp', 'archiver', 'wget', 'curl',
		'python-requests', 'httpclient', 'okhttp', 'java/', 'go-http', 'axios', 'node-fetch',
		'headless', 'phantomjs', 'puppeteer', 'playwright', 'selenium', 'chrome-lighthouse',
		'pagespeed', 'gtmetrix', 'pingdom', 'uptime', 'monitoring', 'statuscake', 'site24x7',
		'facebookexternalhit', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'twitterbot',
		'linkedinbot', 'embedly', 'quora link preview', 'preview', 'validator', 'feedfetcher',
		'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot', 'dataforseo', 'seokicks', 'screaming frog',
		'gptbot', 'claudebot', 'ccbot', 'perplexity', 'anthropic', 'openai', 'applebot',
	];

	$ua = strtolower( $ua );

	foreach ( $signatures as $signature ) {
		if ( false !== strpos( $ua, $signature ) ) {
			return true;
		}
	}

	return false;
}

// ══════════════════════════════════════════════════════════════
//  VENTES
//
//  Le chiffre d'affaires n'est pas recopié : on rattache seulement la
//  commande à la visite. Les montants restent la propriété de
//  WooCommerce, seule source de vérité.
// ══════════════════════════════════════════════════════════════

add_action( 'woocommerce_checkout_order_processed', 'cbaz_attach_order', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'cbaz_attach_order', 20 );
function cbaz_attach_order( $order ) {
	global $wpdb;

	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$table = cbaz_table( 'sessions' );
	$hash  = cbaz_visitor_hash();
	$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - CBAZ_SESSION_GAP );

	$session_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE visitor_hash = %s AND last_seen >= %s ORDER BY last_seen DESC LIMIT 1",
		$hash,
		$since
	) );

	$session = $session_id
		? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $session_id ) )
		: null;

	/* L'achat n'est marqué qu'après confirmation du paiement. Un checkout
	 * peut encore échouer, être annulé ou rester en attente. */

	/*
	 * L'attribution est recopiée SUR LA COMMANDE, et c'est décisif.
	 *
	 * Les visites sont purgées au bout de treize mois ; les commandes,
	 * jamais. Sans cette copie, le chiffre d'affaires d'une campagne
	 * s'effacerait au fil du temps et l'on ne pourrait plus comparer
	 * deux opérations à un an d'écart. C'est aussi ce qui permet de
	 * déduire les remboursements, que la table des visites ignore.
	 */
	$fallback = cbaz_recall_attribution();

	$attribution = [
		'source'         => $session->source ?? ( $fallback['source'] ?? 'direct' ),
		'medium'         => $session->medium ?? ( $fallback['medium'] ?? 'direct' ),
		'campaign'       => $session->campaign ?? ( $fallback['campaign'] ?? '' ),
		'term'           => $session->term ?? ( $fallback['term'] ?? '' ),
		'content'        => $session->content ?? ( $fallback['content'] ?? '' ),
		'first_source'   => $session->first_source ?? ( $fallback['first_source'] ?? '' ),
		'first_campaign' => $session->first_campaign ?? ( $fallback['first_campaign'] ?? '' ),
		'landing'        => $session->entry_path ?? '',
		'country'        => $session->country ?? '',
		'device'         => $session->device ?? '',
	];

	foreach ( $attribution as $key => $value ) {
		$order->update_meta_data( '_cbaz_' . $key, (string) $value );
	}

	/*
	 * Combien de visites, et combien de temps, avant l'achat.
	 *
	 * On remonte les visites de la même empreinte : c'est la seule
	 * façon de dire qu'une vente a mûri trois jours plutôt que d'être
	 * décidée en cinq minutes — et donc de juger une campagne de
	 * découverte autrement qu'au dernier clic.
	 */
	if ( $session ) {
		$touches = $wpdb->get_results( $wpdb->prepare(
			"SELECT started_at, campaign, source FROM {$table}
			WHERE visitor_hash = %s AND started_at <= %s
			ORDER BY started_at ASC LIMIT 20",
			$session->visitor_hash,
			$session->started_at
		) );

		$first = $touches ? strtotime( $touches[0]->started_at ) : strtotime( $session->started_at );
		$path  = [];

		foreach ( $touches as $touch ) {
			$step = $touch->campaign ? $touch->campaign : $touch->source;

			if ( $step && ( ! $path || end( $path ) !== $step ) ) {
				$path[] = $step;
			}
		}

		$order->update_meta_data( '_cbaz_touches', count( $touches ) );
		$order->update_meta_data( '_cbaz_delay', max( 0, strtotime( current_time( 'mysql' ) ) - $first ) );
		$order->update_meta_data( '_cbaz_path', implode( '>', array_slice( $path, 0, 8 ) ) );
	}

	if ( $session_id ) {
		$order->update_meta_data( '_cbaz_session', $session_id );
	}

	$order->save();

	/* Certains moyens de paiement synchrones confirment la commande avant
	 * le dernier hook Store API : rejouer est sûr grâce au garde-fou ci-dessous. */
	if ( $order->is_paid() ) {
		cbaz_mark_order_paid( $order );
	}
}

add_action( 'woocommerce_payment_complete', 'cbaz_mark_order_paid', 20 );
add_action( 'woocommerce_order_status_processing', 'cbaz_mark_order_paid', 20 );
add_action( 'woocommerce_order_status_completed', 'cbaz_mark_order_paid', 20 );
function cbaz_mark_order_paid( $order ) {
	global $wpdb;

	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
	if ( ! $order instanceof WC_Order || ( ! $order->is_paid() && ! $order->has_status( function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : [ 'processing', 'completed' ] ) ) ) {
		return;
	}

	$session_id = (int) $order->get_meta( '_cbaz_session', true );
	if ( ! $session_id ) {
		return;
	}

	$wpdb->update( cbaz_table( 'sessions' ), [ 'order_id' => $order->get_id(), 'revenue' => (float) $order->get_total() ], [ 'id' => $session_id ] );

	$already = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . cbaz_table( 'events' ) . " WHERE session_id = %d AND name = 'purchase' AND object_id = %d LIMIT 1",
		$session_id, $order->get_id()
	) );
	if ( ! $already ) {
		cbaz_record_event( $session_id, 'purchase', $order->get_id(), (float) $order->get_total() );
	}
}

/** Maintient le montant attribué à la visite cohérent après remboursement. */
add_action( 'woocommerce_order_refunded', 'cbaz_sync_order_refunds', 20 );
add_action( 'woocommerce_order_fully_refunded', 'cbaz_sync_order_refunds', 20 );
function cbaz_sync_order_refunds( $order_id ) {
	global $wpdb;
	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) { return; }
	$session_id = (int) $order->get_meta( '_cbaz_session', true );
	if ( $session_id ) {
		$wpdb->update( cbaz_table( 'sessions' ), [ 'revenue' => max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ) ], [ 'id' => $session_id ] );
	}
}

/**
 * Colonne « Provenance » dans la liste des commandes.
 *
 * Le chiffre agrégé dit qu'une campagne marche ; cette colonne dit
 * quelle commande précise elle a produite. C'est ce qu'on regarde
 * quand un total surprend.
 */
add_filter( 'manage_edit-shop_order_columns', 'cbaz_order_column', 20 );
add_filter( 'woocommerce_shop_order_list_table_columns', 'cbaz_order_column', 20 );
function cbaz_order_column( $columns ) {
	$columns['cbaz_origin'] = 'Provenance';

	return $columns;
}

add_action( 'manage_shop_order_posts_custom_column', 'cbaz_order_column_value', 20, 2 );
add_action( 'woocommerce_shop_order_list_table_custom_column', 'cbaz_order_column_value', 20, 2 );
function cbaz_order_column_value( $column, $order ) {
	if ( 'cbaz_origin' !== $column ) {
		return;
	}

	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$campaign = $order->get_meta( '_cbaz_campaign' );
	$source   = $order->get_meta( '_cbaz_source' );

	if ( $campaign ) {
		echo '<strong>' . esc_html( $campaign ) . '</strong><br><span style="color:#787c82;">' . esc_html( $source ) . '</span>';

		return;
	}

	echo esc_html( $source ? $source : '—' );
}
