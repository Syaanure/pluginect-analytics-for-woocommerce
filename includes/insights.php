<?php
/**
 * Pistes à explorer.
 *
 * Des règles simples, appliquées aux chiffres de la période, qui signalent
 * ce qui mérite un regard : une page d'entrée qui fait fuir, un canal qui
 * ne convertit jamais, un produit très vu et jamais ajouté au panier.
 *
 * Ce ne sont PAS des conclusions. Une règle voit une corrélation, jamais
 * une cause ; elle ignore la saison, la marge, le stock, la promotion en
 * cours. L'interface le dit à chaque affichage, et chaque piste est
 * accompagnée des chiffres qui l'ont déclenchée pour qu'on puisse la
 * contester. Rien ne s'affiche tant qu'il n'y a pas assez de visites :
 * sur cinquante visites, tout est du bruit.
 *
 * @package Pluginect\Analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nombre de visites en dessous duquel aucune piste n'est proposée.
 *
 * @return int
 */
function cbaz_insights_min_sessions() {
	/**
	 * Seuil de visites mesurées sur la période avant toute recommandation.
	 *
	 * @param int $sessions 200 par défaut.
	 */
	return max( 20, (int) apply_filters( 'cbaz_insights_min_sessions', 200 ) );
}

/**
 * Les faits sur lesquels les règles raisonnent.
 *
 * Séparés du raisonnement pour une raison précise : les règles deviennent
 * de simples fonctions sur un tableau, qu'on peut tester avec des chiffres
 * inventés sans base de données.
 *
 * @param array $range Période.
 * @return array
 */
function cbaz_insights_facts( array $range ) {
	static $cache = [];

	$key = $range['from'] . '|' . $range['to'] . '|' . wp_json_encode( cbaz_filters() );

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$now  = cbaz_totals( $range['from'], $range['to'] );
	$then = cbaz_totals( $range['prev_from'], $range['prev_to'] );

	$facts = [
		'range'     => $range,
		'now'       => $now,
		'then'      => $then,
		'devices'   => [],
		'sources'   => [],
		'countries' => [],
		'funnel'    => [],
		'entries'   => [],
		'products'  => [],
		'searches'  => [],
		'attribution_days' => (int) cbaz_opt( 'attribution_days' ),
		'track_events'     => (int) cbaz_opt( 'track_events' ),
	];

	// Rien d'autre n'est lu tant que le volume ne justifie pas une lecture.
	if ( (int) $now['sessions'] >= cbaz_insights_min_sessions() ) {
		$facts['devices']   = cbaz_group( 'device', $range, 5 );
		$facts['sources']   = cbaz_sources( $range, 15 );
		$facts['countries'] = cbaz_countries( $range, 10 );
		$facts['funnel']    = cbaz_funnel( $range );
		$facts['entries']   = cbaz_page_bounces( $range );
		$facts['products']  = cbaz_product_funnel( $range );
		$facts['searches']  = cbaz_searches( $range, 3 );
	}

	/**
	 * Faits supplémentaires — le module Pro y ajoute campagnes et attribution.
	 *
	 * @param array $facts Faits collectés.
	 * @param array $range Période.
	 */
	$cache[ $key ] = apply_filters( 'cbaz_insights_facts', $facts, $range );

	return $cache[ $key ];
}

/**
 * Applique les règles.
 *
 * Chaque piste : id, screens (où l'afficher), tone (warn | info | good),
 * title, text, evidence (les chiffres), action (quoi regarder).
 *
 * @param array $facts Voir cbaz_insights_facts().
 * @return array[]
 */
function cbaz_insights_evaluate( array $facts ) {
	$now   = $facts['now'];
	$then  = $facts['then'];
	$out   = [];
	$total = (int) $now['sessions'];

	if ( $total < cbaz_insights_min_sessions() ) {
		return [];
	}

	$add = function ( $id, array $screens, $tone, $title, $text, $evidence, $action ) use ( &$out ) {
		$out[] = compact( 'id', 'screens', 'tone', 'title', 'text', 'evidence', 'action' );
	};

	// ── Tendance : la comparaison avec la période précédente ──
	if ( (int) $then['sessions'] >= 100 && (int) $then['tracked_orders'] >= 10 ) {
		$traffic = cbaz_delta( $now['sessions'], $then['sessions'] );
		$cr      = cbaz_delta( $now['cr'], $then['cr'] );

		if ( null !== $traffic && null !== $cr && abs( $traffic ) <= 10 && $cr <= -25 ) {
			$add( 'cr_drop', [ 'overview', 'ecommerce' ], 'warn',
				__( 'Conversion fell while traffic held', 'pluginect-analytics-for-woocommerce' ),
				__( 'About as many visits as during the previous period, but noticeably fewer of them ended in an order. That points at the store rather than at the traffic: a checkout change, a stock or delivery issue, a price move, a technical error on a device.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: current conversion rate, 2: previous conversion rate, 3: traffic change in percent. */
				sprintf( __( 'Conversion %1$s vs %2$s before · visits %3$s', 'pluginect-analytics-for-woocommerce' ), cbaz_pct( $now['cr'] ), cbaz_pct( $then['cr'] ), cbaz_signed( $traffic ) ),
				__( 'Place a test order on mobile and desktop, then check the funnel screen for the step that lost the most.', 'pluginect-analytics-for-woocommerce' )
			);
		} elseif ( null !== $traffic && $traffic <= -25 ) {
			$add( 'traffic_drop', [ 'overview', 'acquisition' ], 'warn',
				__( 'Traffic dropped sharply', 'pluginect-analytics-for-woocommerce' ),
				__( 'A quarter fewer visits than during the previous period. Before worrying, check whether the previous period included a campaign, a newsletter or a seasonal peak — a drop after a peak is not a decline.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: current visits, 2: previous visits. */
				sprintf( __( '%1$s visits vs %2$s before', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $now['sessions'] ), cbaz_int( $then['sessions'] ) ),
				__( 'Compare the sources on the Acquisition screen with the previous period to see which one fell.', 'pluginect-analytics-for-woocommerce' )
			);
		}
	}

	// ── Commandes sans visite mesurée ──
	if ( (int) $now['orders'] >= 10 ) {
		$share = $now['tracked_orders'] / $now['orders'];

		if ( $share < 0.5 ) {
			$add( 'untracked_orders', [ 'overview', 'ecommerce' ], 'info',
				__( 'Half of the orders have no measured visit', 'pluginect-analytics-for-woocommerce' ),
				__( 'Conversion rates and campaign figures only see orders linked to a visit. Orders created by hand, imported, placed by an excluded role, or by visitors who block scripts stay invisible to them — without being lost for the store.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: orders linked to a visit, 2: total orders. */
				sprintf( __( '%1$s of %2$s orders linked to a visit', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $now['tracked_orders'] ), cbaz_int( $now['orders'] ) ),
				__( 'If most orders come from the site itself, check the excluded roles and paths in Settings, and whether a cache or consent tool blocks the tracking script.', 'pluginect-analytics-for-woocommerce' )
			);
		}
	}

	// ── Mobile qui convertit nettement moins ──
	$dev = [];
	foreach ( $facts['devices'] as $d ) {
		$dev[ $d->label ] = [ 'sessions' => (int) $d->sessions, 'orders' => (int) $d->orders ];
	}

	if ( isset( $dev['mobile'], $dev['desktop'] ) && $dev['mobile']['sessions'] >= 100 && $dev['desktop']['sessions'] >= 100 && $dev['desktop']['orders'] >= 10 ) {
		$cr_m = $dev['mobile']['orders'] / $dev['mobile']['sessions'];
		$cr_d = $dev['desktop']['orders'] / $dev['desktop']['sessions'];

		if ( $cr_m < 0.5 * $cr_d ) {
			$add( 'mobile_cr', [ 'overview', 'ecommerce', 'visiteurs' ], 'warn',
				__( 'Mobile converts much less than desktop', 'pluginect-analytics-for-woocommerce' ),
				__( 'A gap is normal — people browse on the phone and buy on the computer — but a rate below half of the desktop one often hides a practical obstacle: a form that is hard to fill, a payment method missing on mobile, a slow page, a popup covering the button.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: mobile conversion rate, 2: desktop conversion rate, 3: mobile visits. */
				sprintf( __( 'Mobile %1$s vs desktop %2$s, over %3$s mobile visits', 'pluginect-analytics-for-woocommerce' ), cbaz_pct( $cr_m * 100 ), cbaz_pct( $cr_d * 100 ), cbaz_int( $dev['mobile']['sessions'] ) ),
				__( 'Go through the whole purchase on a real phone, from the product page to the payment confirmation.', 'pluginect-analytics-for-woocommerce' )
			);
		}
	}

	// ── Entonnoir : où l'on perd le plus ──
	$f = array_column( $facts['funnel'], 'value' );

	if ( count( $f ) === 4 && $facts['track_events'] ) {
		list( , $carts, $checkouts, $purchases ) = $f;

		if ( $carts >= 30 && $purchases / $carts < 0.3 ) {
			$add( 'cart_abandon', [ 'overview', 'ecommerce' ], 'warn',
				__( 'Most carts are abandoned', 'pluginect-analytics-for-woocommerce' ),
				__( 'Fewer than three carts out of ten turn into an order. The usual suspects: delivery costs discovered late, a mandatory account, a long form, a doubt about the return policy. It can also be visitors comparing prices — carts are not commitments.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: add-to-cart count, 2: paid orders. */
				sprintf( __( '%1$s carts · %2$s paid orders', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $carts ), cbaz_int( $purchases ) ),
				__( 'Show delivery costs and delays before the cart, and allow ordering without an account.', 'pluginect-analytics-for-woocommerce' )
			);
		} elseif ( $checkouts >= 20 && $purchases / $checkouts < 0.5 ) {
			$add( 'checkout_drop', [ 'overview', 'ecommerce' ], 'warn',
				__( 'Half of the started orders never complete', 'pluginect-analytics-for-woocommerce' ),
				__( 'Visitors who reach the checkout have decided to buy; losing one in two there points at the checkout itself: a payment method that fails, an error message, a field that rejects a valid address.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: started checkouts, 2: paid orders. */
				sprintf( __( '%1$s checkouts started · %2$s paid orders', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $checkouts ), cbaz_int( $purchases ) ),
				__( 'Check the failed and pending orders in WooCommerce for a repeated payment error.', 'pluginect-analytics-for-woocommerce' )
			);
		}
	}

	// ── Page d'entrée qui fait fuir ──
	$worst = null;
	foreach ( $facts['entries'] as $path => $row ) {
		$entries = (int) $row->entries;
		if ( $entries < 50 ) {
			continue;
		}
		$rate = $row->bounces / $entries;
		if ( $rate >= 0.7 && $rate >= 1.3 * ( $now['bounce_rate'] / 100 ) && ( ! $worst || $entries > $worst['entries'] ) ) {
			$worst = [ 'path' => $path, 'entries' => $entries, 'rate' => $rate ];
		}
	}

	if ( $worst ) {
		$add( 'entry_bounce', [ 'overview', 'comportement' ], 'warn',
			__( 'An entry page loses most of its visitors at once', 'pluginect-analytics-for-woocommerce' ),
			__( 'Visitors who land on this page leave without viewing anything else, far more often than elsewhere on the store. Either the page does not match what brought them there, or something is wrong with it — loading time, layout on mobile, missing call to action.', 'pluginect-analytics-for-woocommerce' ),
			/* translators: 1: page path, 2: bounce rate, 3: number of entries. */
			sprintf( __( '%1$s · %2$s bounce over %3$s entries', 'pluginect-analytics-for-woocommerce' ), cbaz_pretty_path( $worst['path'] ), cbaz_pct( $worst['rate'] * 100, 0 ), cbaz_int( $worst['entries'] ) ),
			__( 'Open the page from the source that sends the most visitors to it, and read it as they would.', 'pluginect-analytics-for-woocommerce' )
		);
	}

	// ── Sources : celle qui ne convertit jamais, celle qui convertit le mieux ──
	$overall_cr = $now['cr'] / 100;
	$dead       = null;
	$best       = null;

	foreach ( $facts['sources'] as $s ) {
		$sess = (int) $s->sessions;
		$ord  = (int) $s->orders;

		if ( $sess >= 100 && 0 === $ord && $overall_cr >= 0.005 && 'direct' !== strtolower( (string) $s->source ) && ( ! $dead || $sess > $dead['sessions'] ) ) {
			$dead = [ 'label' => $s->source . ' / ' . $s->medium, 'sessions' => $sess ];
		}

		if ( $sess >= 50 && $ord >= 5 && $ord / $sess >= 1.5 * $overall_cr && ( ! $best || $ord / $sess > $best['cr'] ) ) {
			$best = [ 'label' => $s->source . ' / ' . $s->medium, 'sessions' => $sess, 'orders' => $ord, 'cr' => $ord / $sess ];
		}
	}

	if ( $dead ) {
		$add( 'source_no_orders', [ 'overview', 'acquisition' ], 'warn',
			__( 'A busy source never converts', 'pluginect-analytics-for-woocommerce' ),
			__( 'Plenty of visits, not a single order, while the store converts elsewhere. Either the visitors it sends are not looking for what you sell, or they land on a page that does not lead to the catalogue. If you pay for this traffic, this is where the money goes first.', 'pluginect-analytics-for-woocommerce' ),
			/* translators: 1: source label, 2: number of visits. */
			sprintf( __( '%1$s · %2$s visits · 0 orders', 'pluginect-analytics-for-woocommerce' ), $dead['label'], cbaz_int( $dead['sessions'] ) ),
			__( 'Look at the entry pages of this source on the Journeys screen, then at what these visitors do next.', 'pluginect-analytics-for-woocommerce' )
		);
	}

	if ( $best ) {
		$add( 'source_best', [ 'overview', 'acquisition' ], 'good',
			__( 'One source converts far better than the rest', 'pluginect-analytics-for-woocommerce' ),
			__( 'Its visitors buy much more often than average. That is usually a sign of a good match between the audience and the offer — and an argument for sending more of them, if the source can scale.', 'pluginect-analytics-for-woocommerce' ),
			/* translators: 1: source label, 2: conversion rate, 3: store conversion rate. */
			sprintf( __( '%1$s · %2$s conversion vs %3$s for the store', 'pluginect-analytics-for-woocommerce' ), $best['label'], cbaz_pct( $best['cr'] * 100 ), cbaz_pct( $now['cr'] ) ),
			__( 'Check whether the volume can grow before drawing conclusions: a small, well-targeted source rarely stays that efficient when it is scaled up.', 'pluginect-analytics-for-woocommerce' )
		);
	}

	// ── Produit très vu, jamais ajouté au panier ──
	if ( $facts['products'] && $facts['track_events'] ) {
		$views_total = 0;
		$carts_total = 0;
		foreach ( $facts['products'] as $p ) {
			$views_total += (int) $p->views;
			$carts_total += (int) $p->carts;
		}
		$avg_rate = $views_total ? $carts_total / $views_total : 0;
		$cold     = null;

		if ( $avg_rate > 0 ) {
			foreach ( $facts['products'] as $id => $p ) {
				$views = (int) $p->views;
				if ( $views >= 50 && ( (int) $p->carts / $views ) < 0.25 * $avg_rate && ( ! $cold || $views > $cold['views'] ) ) {
					$cold = [ 'id' => (int) $id, 'views' => $views, 'carts' => (int) $p->carts ];
				}
			}
		}

		if ( $cold ) {
			$name = function_exists( 'wc_get_product' ) && wc_get_product( $cold['id'] ) ? wc_get_product( $cold['id'] )->get_name() : '#' . $cold['id'];
			$add( 'product_cold', [ 'overview', 'produits' ], 'warn',
				__( 'A popular product is almost never added to the cart', 'pluginect-analytics-for-woocommerce' ),
				__( 'Many visitors look at it, very few take the next step, far below what your other products achieve. The page itself is the first suspect: price, photos, description, stock message, delivery information, or a variation that cannot be selected.', 'pluginect-analytics-for-woocommerce' ),
				/* translators: 1: product name, 2: views, 3: add-to-cart count. */
				sprintf( __( '%1$s · %2$s views · %3$s added to cart', 'pluginect-analytics-for-woocommerce' ), $name, cbaz_int( $cold['views'] ), cbaz_int( $cold['carts'] ) ),
				__( 'Open the product page on a phone and try to add it to the cart, variation included.', 'pluginect-analytics-for-woocommerce' )
			);
		}
	}

	// ── Ce que l'on cherche ──
	if ( $facts['searches'] && (int) $facts['searches'][0]->sessions >= 10 ) {
		$top = $facts['searches'][0];
		$add( 'top_search', [ 'overview', 'comportement' ], 'info',
			__( 'Visitors keep searching for the same thing', 'pluginect-analytics-for-woocommerce' ),
			__( 'A term typed again and again in the search box is a demand expressed in the visitors’ own words. If you sell it, make it easier to find; if you do not, it is a product idea served on a plate.', 'pluginect-analytics-for-woocommerce' ),
			/* translators: 1: search term, 2: number of searches. */
			sprintf( __( '“%1$s” · %2$s searches', 'pluginect-analytics-for-woocommerce' ), $top->label, cbaz_int( $top->sessions ) ),
			__( 'Type the term in your own search box and look at what comes out.', 'pluginect-analytics-for-woocommerce' )
		);
	}

	// ── Pays qui visite sans acheter ──
	if ( (int) $now['orders'] >= 10 ) {
		foreach ( $facts['countries'] as $c ) {
			$sess = (int) $c->sessions;
			if ( $sess >= 100 && $sess / $total >= 0.1 && 0 === (int) $c->orders && '' !== (string) $c->label ) {
				$add( 'country_no_orders', [ 'overview', 'geographie' ], 'info',
					__( 'A whole country visits without ever buying', 'pluginect-analytics-for-woocommerce' ),
					__( 'At least a tenth of the visits come from a country that never orders. Sometimes it is simply that you do not deliver there — then the visits are wasted and the message could say so earlier. Sometimes a payment method, a currency or a language is missing.', 'pluginect-analytics-for-woocommerce' ),
					/* translators: 1: country name, 2: number of visits. */
					sprintf( __( '%1$s · %2$s visits · 0 orders', 'pluginect-analytics-for-woocommerce' ), cbaz_country_name( $c->label ), cbaz_int( $sess ) ),
					__( 'Check the shipping zones and payment methods available for this country in WooCommerce.', 'pluginect-analytics-for-woocommerce' )
				);
				break;
			}
		}
	}

	// ── Mémoire d'attribution éteinte alors que des campagnes tournent ──
	$tagged = 0;
	foreach ( $facts['sources'] as $s ) {
		if ( ! in_array( strtolower( (string) $s->medium ), [ 'direct', 'organic', 'referral', '' ], true ) ) {
			$tagged += (int) $s->sessions;
		}
	}

	if ( 0 === $facts['attribution_days'] && $tagged >= 100 ) {
		$add( 'attribution_off', [ 'overview', 'campagnes' ], 'info',
			__( 'Campaigns run, but nothing remembers them past the visit', 'pluginect-analytics-for-woocommerce' ),
			__( 'Without the attribution memory, an order is only credited to a campaign if it happens during the very visit that came from it. Someone who clicks today and buys tomorrow counts as direct — campaign figures end up lower than reality.', 'pluginect-analytics-for-woocommerce' ),
			/* translators: %1$s: number of tagged visits. */
			sprintf( __( '%1$s tagged visits over the period · attribution memory off', 'pluginect-analytics-for-woocommerce' ), cbaz_int( $tagged ) ),
			__( 'Enable the attribution memory in Settings → Privacy if a small origin cookie is acceptable for your store.', 'pluginect-analytics-for-woocommerce' )
		);
	}

	/**
	 * Pistes supplémentaires — le module Pro ajoute campagnes et attribution.
	 *
	 * @param array $out   Pistes déjà trouvées.
	 * @param array $facts Faits.
	 */
	return apply_filters( 'cbaz_insights', $out, $facts );
}

/**
 * Variation signée, en pourcentage, pour les phrases.
 *
 * @param float $delta Variation.
 * @return string
 */
function cbaz_signed( $delta ) {
	return ( $delta > 0 ? '+' : '' ) . number_format_i18n( $delta, 0 ) . ' %';
}

/**
 * Pistes de la période, pour un écran donné.
 *
 * @param array  $range  Période.
 * @param string $screen Écran (slug d'onglet) ; vide = toutes.
 * @return array[]
 */
function cbaz_insights( array $range, $screen = '' ) {
	$all = cbaz_insights_evaluate( cbaz_insights_facts( $range ) );

	if ( '' === $screen ) {
		return $all;
	}

	return array_values( array_filter( $all, fn( $i ) => in_array( $screen, $i['screens'], true ) ) );
}

/**
 * L'encart « Pistes à explorer ».
 *
 * Toujours précédé de l'avertissement : ces phrases sont produites par
 * des règles, pas par quelqu'un qui connaît la boutique.
 *
 * @param array  $range  Période.
 * @param string $screen Écran courant.
 */
function cbaz_insights_box( array $range, $screen = '' ) {
	if ( ! cbaz_opt( 'insights' ) ) {
		return;
	}

	$facts = cbaz_insights_facts( $range );
	$min   = cbaz_insights_min_sessions();
	$total = (int) $facts['now']['sessions'];
	$items = cbaz_insights( $range, $screen );
	?>
	<section class="cbaz-card cbaz-insights" aria-labelledby="cbaz-insights-title">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'bolt', 6 ); // phpcs:ignore ?>
			<div>
				<h2 id="cbaz-insights-title"><?php echo esc_html__( 'Leads worth checking', 'pluginect-analytics-for-woocommerce' ); ?><?php echo cbaz_tip( 'insights' ); // phpcs:ignore ?></h2>
				<p><?php echo esc_html__( 'Computed automatically from the period’s figures. They point at things to look at — they prove nothing, and they do not replace a proper analysis of your store.', 'pluginect-analytics-for-woocommerce' ); ?></p>
			</div>
		</header>

		<?php if ( $total < $min ) : ?>
			<div class="cbaz-insights__wait">
				<div class="cbaz-insights__progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $min; ?>" aria-valuenow="<?php echo (int) $total; ?>"><span style="width:<?php echo (float) min( 100, ( $total / $min ) * 100 ); ?>%"></span></div>
				<p>
					<?php
					/* translators: 1: measured visits, 2: visits needed. */
					printf( esc_html__( 'Not enough data yet: %1$s visits measured over this period, %2$s needed before a lead means anything. Come back later, or widen the period.', 'pluginect-analytics-for-woocommerce' ), esc_html( cbaz_int( $total ) ), esc_html( cbaz_int( $min ) ) );
					?>
				</p>
			</div>
		<?php elseif ( ! $items ) : ?>
			<p class="cbaz-empty"><?php echo esc_html__( 'Nothing stands out over this period. That is good news — or a sign that the period is too short to tell.', 'pluginect-analytics-for-woocommerce' ); ?></p>
		<?php else : ?>
			<ol class="cbaz-insights__list">
				<?php foreach ( $items as $i ) : ?>
					<li class="cbaz-insight cbaz-insight--<?php echo esc_attr( $i['tone'] ); ?>">
						<span class="cbaz-insight__dot" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html( $i['title'] ); ?></h3>
							<p class="cbaz-insight__evidence"><?php echo esc_html( $i['evidence'] ); ?></p>
							<p><?php echo esc_html( $i['text'] ); ?></p>
							<p class="cbaz-insight__action"><strong><?php echo esc_html__( 'Worth doing:', 'pluginect-analytics-for-woocommerce' ); ?></strong> <?php echo esc_html( $i['action'] ); ?></p>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="cbaz-insights__caveat"><?php echo esc_html__( 'Take these with a pinch of salt: a rule sees a correlation, never a cause. It knows nothing of the season, your margins, your stock or the promotion running this week.', 'pluginect-analytics-for-woocommerce' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

add_filter( 'cbaz_help_entries', 'cbaz_insights_help' );
/**
 * L'infobulle de l'encart lui-même.
 *
 * @param array $entries Glossaire.
 * @return array
 */
function cbaz_insights_help( array $entries ) {
	$entries['insights'] = [ __( 'Leads worth checking', 'pluginect-analytics-for-woocommerce' ), __( 'Simple rules applied to the period’s figures: a page that loses its visitors, a source that never converts, a product viewed a lot and never added to the cart. Each lead shows the numbers that triggered it, so you can disagree with it.', 'pluginect-analytics-for-woocommerce' ) ];

	return $entries;
}
