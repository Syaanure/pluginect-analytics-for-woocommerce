<?php
/**
 * Glossaire et infobulles.
 *
 * Chaque indicateur porte un petit « i » qui dit, en deux phrases, ce
 * qu'il mesure et comment le lire. Le texte vit ici, à un seul endroit :
 * une définition corrigée l'est partout où l'indicateur apparaît.
 *
 * @package Pluginect\Analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Définitions, clé => [ titre, explication ].
 *
 * Les explications sont écrites pour quelqu'un qui n'a jamais ouvert un
 * outil de mesure : pas de jargon, et toujours la limite de l'indicateur
 * quand il y en a une — un chiffre qu'on surinterprète fait plus de mal
 * qu'un chiffre absent.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function cbaz_help_entries() {
	static $entries = null;

	if ( null !== $entries ) {
		return $entries;
	}

	$entries = [
		'visitors'         => [ __( 'Visitors', 'pluginect-analytics-for-woocommerce' ), __( 'Distinct people over the period, recognised by a pseudonymous fingerprint that resets every night. Someone who comes back tomorrow counts as a new visitor: compare trends rather than absolute numbers.', 'pluginect-analytics-for-woocommerce' ) ],
		'sessions'         => [ __( 'Visits', 'pluginect-analytics-for-woocommerce' ), __( 'A sequence of pages viewed by the same visitor with less than 30 minutes of inactivity between them. One person can make several visits in a day.', 'pluginect-analytics-for-woocommerce' ) ],
		'pageviews'        => [ __( 'Page views', 'pluginect-analytics-for-woocommerce' ), __( 'Every page displayed. A page reloaded, or seen twice during the same visit, counts twice.', 'pluginect-analytics-for-woocommerce' ) ],
		'revenue'          => [ __( 'Revenue', 'pluginect-analytics-for-woocommerce' ), __( 'Paid WooCommerce orders over the period, refunds deducted. Read from your store whatever the origin of the order — orders created by hand or imported are included.', 'pluginect-analytics-for-woocommerce' ) ],
		'cr'               => [ __( 'Conversion rate', 'pluginect-analytics-for-woocommerce' ), __( 'Share of measured visits that ended in a paid order. Only orders linked to a measured visit count, which keeps the rate honest: orders placed by hand or before tracking started are left out.', 'pluginect-analytics-for-woocommerce' ) ],
		'aov'              => [ __( 'Average basket', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue divided by the number of orders. A rising basket with fewer orders can hide a drop in volume: read both together.', 'pluginect-analytics-for-woocommerce' ) ],
		'orders'           => [ __( 'Orders', 'pluginect-analytics-for-woocommerce' ), __( 'Paid WooCommerce orders over the period. Cancelled and failed orders are excluded; refunded ones are deducted from revenue.', 'pluginect-analytics-for-woocommerce' ) ],
		'bounce'           => [ __( 'Bounce rate', 'pluginect-analytics-for-woocommerce' ), __( 'Share of visits that viewed a single page. Normal on a blog post; on a product or landing page it usually means the page did not match what the visitor expected.', 'pluginect-analytics-for-woocommerce' ) ],
		'duration'         => [ __( 'Average duration', 'pluginect-analytics-for-woocommerce' ), __( 'Average time actually spent on pages, counted only while the tab is active. Time spent in a background tab is ignored.', 'pluginect-analytics-for-woocommerce' ) ],
		'per_session'      => [ __( 'Value per visit', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue divided by visits: what one visit is worth on average. Handy to compare traffic sources of very different sizes.', 'pluginect-analytics-for-woocommerce' ) ],
		'attributed'       => [ __( 'Attributed revenue', 'pluginect-analytics-for-woocommerce' ), __( 'Part of the revenue that a measured visit explains. The rest comes from orders placed before tracking started, offline, by hand, or by visitors who block scripts.', 'pluginect-analytics-for-woocommerce' ) ],
		'pages_per_visit'  => [ __( 'Pages per visit', 'pluginect-analytics-for-woocommerce' ), __( 'Page views divided by visits — the depth of browsing. Below 1.5 on a shop, most visitors leave from the page they landed on.', 'pluginect-analytics-for-woocommerce' ) ],
		'visits_per_visitor' => [ __( 'Visits per visitor', 'pluginect-analytics-for-woocommerce' ), __( 'Visits divided by visitors, within the same day: the fingerprint that recognises a visitor resets every night.', 'pluginect-analytics-for-woocommerce' ) ],
		'funnel'           => [ __( 'Conversion funnel', 'pluginect-analytics-for-woocommerce' ), __( 'The four steps of a purchase: visit, add to cart, order started, paid order. Each percentage is read from the first step; the loss between two steps shows where visitors give up.', 'pluginect-analytics-for-woocommerce' ) ],
		'leaks'            => [ __( 'Vanishing points', 'pluginect-analytics-for-woocommerce' ), __( 'Visits lost between two consecutive steps of the funnel. The biggest gap is the first place to look.', 'pluginect-analytics-for-woocommerce' ) ],
		'sources'          => [ __( 'Traffic sources', 'pluginect-analytics-for-woocommerce' ), __( 'Where visits come from: the referring site, a UTM tag or a campaign link. “Direct” means no referrer — bookmarks, typed addresses, some apps and e-mail clients.', 'pluginect-analytics-for-woocommerce' ) ],
		'referrers'        => [ __( 'Referring sites', 'pluginect-analytics-for-woocommerce' ), __( 'Third-party sites whose links brought visitors, search engines and social networks aside.', 'pluginect-analytics-for-woocommerce' ) ],
		'channel_revenue'  => [ __( 'Revenue by channel', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue of the orders linked to a visit from each source. Orders without a measured visit do not appear here.', 'pluginect-analytics-for-woocommerce' ) ],
		'channel_cr'       => [ __( 'Conversion by channel', 'pluginect-analytics-for-woocommerce' ), __( 'Conversion rate of each source. A small source with a high rate is often worth more attention than a large one that never converts.', 'pluginect-analytics-for-woocommerce' ) ],
		'entry_pages'      => [ __( 'Entry pages', 'pluginect-analytics-for-woocommerce' ), __( 'First page of each visit. An entry page that performs poorly loses visitors before they see anything else.', 'pluginect-analytics-for-woocommerce' ) ],
		'exit_pages'       => [ __( 'Exit pages', 'pluginect-analytics-for-woocommerce' ), __( 'Last page of each visit. Every visit ends somewhere: an exit page is only a problem when it is not the natural end of a journey.', 'pluginect-analytics-for-woocommerce' ) ],
		'searches'         => [ __( 'Internal searches', 'pluginect-analytics-for-woocommerce' ), __( 'Terms typed into your store’s search box. What people look for without finding often says more than a ranking of pages.', 'pluginect-analytics-for-woocommerce' ) ],
		'journey_flow'     => [ __( 'Visitor paths', 'pluginect-analytics-for-woocommerce' ), __( 'The most frequent sequences of pages, step by step, from a chosen starting page.', 'pluginect-analytics-for-woocommerce' ) ],
		'product_views'    => [ __( 'Product views', 'pluginect-analytics-for-woocommerce' ), __( 'Product pages displayed, the visitors who saw them, and the share that added the product to the cart.', 'pluginect-analytics-for-woocommerce' ) ],
		'add_rate'         => [ __( 'Add-to-cart rate', 'pluginect-analytics-for-woocommerce' ), __( 'Add-to-cart events divided by product views. Low on a heavily viewed product, it usually points at the price, the photos, the stock or the delivery information.', 'pluginect-analytics-for-woocommerce' ) ],
		'countries'        => [ __( 'Countries', 'pluginect-analytics-for-woocommerce' ), __( 'Read from a header sent by your host or CDN, from WooCommerce geolocation when enabled, or from the browser language as a last resort. Never stored together with an address.', 'pluginect-analytics-for-woocommerce' ) ],
		'devices'          => [ __( 'Devices', 'pluginect-analytics-for-woocommerce' ), __( 'Mobile, tablet or desktop, deduced from the browser signature.', 'pluginect-analytics-for-woocommerce' ) ],
		'new_returning'    => [ __( 'New or returning', 'pluginect-analytics-for-woocommerce' ), __( '“Returning” means already seen earlier the same day, since the fingerprint resets every night. It is not a customer loyalty metric.', 'pluginect-analytics-for-woocommerce' ) ],
		'screens'          => [ __( 'Screen resolutions', 'pluginect-analytics-for-woocommerce' ), __( 'Width × height of the visitors’ screens. Test the store first on the most frequent ones.', 'pluginect-analytics-for-woocommerce' ) ],
		'realtime'         => [ __( 'Real time', 'pluginect-analytics-for-woocommerce' ), __( 'Visits active over the last 30 minutes, refreshed every 20 seconds. Useful during a campaign launch or a newsletter send.', 'pluginect-analytics-for-woocommerce' ) ],
		'campaign_revenue' => [ __( 'Campaign revenue', 'pluginect-analytics-for-woocommerce' ), __( 'Net revenue of the orders linked to a tagged visit, refunds deducted, within the attribution window set in Settings.', 'pluginect-analytics-for-woocommerce' ) ],
		'invested'         => [ __( 'Invested', 'pluginect-analytics-for-woocommerce' ), __( 'Sum of the budgets entered on the campaign sheets. Without a budget, no return can be computed.', 'pluginect-analytics-for-woocommerce' ) ],
		'roas'             => [ __( 'Return', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue divided by budget: euros earned per euro spent. Below 1, the campaign cost more than it brought in — and this is before your margin.', 'pluginect-analytics-for-woocommerce' ) ],
		'cpa'              => [ __( 'Cost per order', 'pluginect-analytics-for-woocommerce' ), __( 'Budget divided by attributed orders. Compare it with your margin on an average order.', 'pluginect-analytics-for-woocommerce' ) ],
		'campaign_orders'  => [ __( 'Attributed orders', 'pluginect-analytics-for-woocommerce' ), __( 'Orders whose visit carried this campaign, within the attribution window.', 'pluginect-analytics-for-woocommerce' ) ],
		'campaign_goal'    => [ __( 'Objective', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue target entered on the campaign sheet, compared with the net revenue actually attributed.', 'pluginect-analytics-for-woocommerce' ) ],
		'references_sold'  => [ __( 'References sold', 'pluginect-analytics-for-woocommerce' ), __( 'Distinct products with at least one paid order over the period.', 'pluginect-analytics-for-woocommerce' ) ],
		'items_sold'       => [ __( 'Items sold', 'pluginect-analytics-for-woocommerce' ), __( 'Units sold over the period, refunded units deducted.', 'pluginect-analytics-for-woocommerce' ) ],
		'products_revenue' => [ __( 'Products revenue', 'pluginect-analytics-for-woocommerce' ), __( 'Revenue of the product lines, shipping and taxes aside. It can differ slightly from the store revenue for that reason.', 'pluginect-analytics-for-woocommerce' ) ],
		'average_price'    => [ __( 'Average price', 'pluginect-analytics-for-woocommerce' ), __( 'Products revenue divided by units sold: the price actually paid per unit, promotions included.', 'pluginect-analytics-for-woocommerce' ) ],
		'journeys_shown'   => [ __( 'Routes displayed', 'pluginect-analytics-for-woocommerce' ), __( 'The visits listed below, taken among the most recent of the period and of the chosen segment. The statistics on this line describe this group only.', 'pluginect-analytics-for-woocommerce' ) ],
		'journeys_buy'     => [ __( 'Go as far as purchasing', 'pluginect-analytics-for-woocommerce' ), __( 'Visits of this group that ended in a paid order.', 'pluginect-analytics-for-woocommerce' ) ],
		'stops'            => [ __( 'Where these visits stop', 'pluginect-analytics-for-woocommerce' ), __( 'Last page seen by the visits that did not end in an order. A page that appears often here deserves a look.', 'pluginect-analytics-for-woocommerce' ) ],
	];

	/**
	 * Définitions supplémentaires — le module Pro y ajoute les siennes.
	 *
	 * @param array $entries clé => [ titre, explication ].
	 */
	$entries = apply_filters( 'cbaz_help_entries', $entries );

	return $entries;
}

/**
 * Le petit « i » d'un indicateur.
 *
 * La classe est `cbaz-help`, surtout pas `cbaz-tip` : ce nom appartient
 * déjà à l'infobulle du graphique, qui est positionnée en absolu et
 * translatée — un bouton qui héritait de ces règles sortait du flux et
 * flottait au-dessus de son intitulé.
 *
 * Un bouton, pas un simple survol : il s'ouvre aussi au clavier et au
 * doigt. Le texte est porté par des attributs ; le script d'administration
 * l'affiche dans une bulle unique, partagée par toute la page.
 *
 * @param string $key Clé du glossaire.
 * @return string HTML, vide si la clé est inconnue.
 */
function cbaz_tip( $key ) {
	$entries = cbaz_help_entries();

	if ( empty( $entries[ $key ] ) ) {
		return '';
	}

	list( $title, $text ) = $entries[ $key ];

	/*
	 * Le `title` est un repli, pas un doublon : si le script d'administration
	 * ne s'exécute pas — cache trop zélé, conflit avec une autre extension —
	 * le survol affiche au moins l'explication. Le script le retire dès qu'il
	 * prend la main.
	 */
	return sprintf(
		'<button type="button" class="cbaz-help" data-cbaz-help="%1$s" data-cbaz-help-title="%2$s" title="%2$s — %1$s" aria-label="%3$s" aria-expanded="false"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="7"/><path d="M8 7v4.5M8 4.6v.2"/></svg></button>',
		esc_attr( $text ),
		esc_attr( $title ),
		/* translators: %s: name of the indicator. */
		esc_attr( sprintf( __( 'What does “%s” mean?', 'pluginect-analytics-for-woocommerce' ), $title ) )
	);
}
