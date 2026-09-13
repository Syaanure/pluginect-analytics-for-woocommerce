=== Pluginect Analytics – Statistics & Reports for WooCommerce ===
Contributors: pluginect
Tags: analytics, woocommerce, statistics, utm, gdpr
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.31.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audience and sales analytics hosted on your own server. No third-party service, no cookie by default.

== Description ==

An online shop asks three questions, always the same ones: where do people come from, what do they do once they are here, and how much does it bring in. The usual tools answer by sending your data to a third party, dropping cookies, and forcing a consent banner that drives part of your visitors away — thus measuring less accurately what they claim to measure.

This plugin answers those questions while keeping the measurement data inside your WordPress database. No measurement data is ever sent to a third-party service. No cookie is dropped with the default settings.

= What it measures =

* **Audience** — visits, visitors, page views, duration, bounce rate, devices, browsers, operating systems, languages, screen resolutions.
* **Acquisition** — sources, mediums, referring sites, search engines, with the logo of each channel.
* **Behaviour** — page views, entry and exit pages, typical paths, internal searches, drop-off points.
* **E-commerce** — revenue, orders, average order value, conversion rate, complete funnel, read directly from WooCommerce.
* **Products** — views, add-to-cart, add-to-cart rate, sales per product.
* **Geography** — countries, with conversion and revenue per country, on an interactive globe.
* **Journeys** — the ten most recent visits, unrolled page by page.
* **Real time** — traffic and active pages over the last thirty minutes.
* **Campaigns** — creation of UTM links and short links.
* **Explanations and leads** — every figure explains itself, and cautious rules point at what deserves a look once there are enough visits.

= Separate Pro extension =

The paid module, distributed separately and absent from this package, adds the full journey history and journey filters, campaign performance and attribution, real-time details, historical comparisons and CSV exports. The shared collection keeps working without the Pro module.

Everything in this free plugin works on its own and stays free. Where a Pro feature would appear, the interface says so in one line and links to [the Pro presentation page](https://pluginect.com/pluginect-analytics/) — no pop-up, no blurred content, no nagging notice.

= What it does not do =

The pseudonymous identifier that links two pages of the same visit changes every night: a visitor who comes back the next day is someone else to the system. This is a deliberate limitation.

== Privacy ==

= No cookie =

Default setting: no cookie, no local storage, no session storage. The plugin writes nothing in the browser. The legal qualification nevertheless depends on your configuration, on your other plugins and on your jurisdiction; document the measurement in your privacy policy.

A single optional cookie exists: the attribution memory, disabled by default, which links a purchase to a campaign click that happened several days earlier. It only contains the origin of the visit, never an identifier. The Settings screen always states, plainly, whether a cookie is dropped or not.

= The IP address is never stored =

It is used for a fraction of a second to compute an identifier, then forgotten. It is **truncated before that computation**: only the network prefix is kept — three octets in IPv4, three groups in IPv6. The result is a daily pseudonymous identifier based on that prefix and the browser signature. It reduces precision compared with a full address, but must not be presented as guaranteed anonymisation in every context.

= The point to be aware of =

A visit that ends in an order is linked to that order; otherwise there would be neither revenue per campaign nor return on investment. For those visits — and only those — a link exists to a customer identifiable through the order. This is personal data under the GDPR, and it is what justifies the automatic purge of the detailed data.

We would rather write it down plainly than display "no personal data" without nuance.

= Two retention periods =

* **The detail** — visits, pages, events — is purged automatically. The default is 24 months, configurable from 1 to 120 months.
* **The consolidated history** — a daily summary without any individual visit — is kept for up to ten years by default, with a configurable duration.

Consolidation always happens **before** the purge: a day deleted without having been summarised would be lost twice.

= What remains your responsibility =

Describe the measurement, the data, the purposes and the retention periods in your privacy policy. Determine with your counsel the legal basis and the obligations that apply to your configuration and your jurisdiction.

== Security ==

= The collection endpoint =

It is public, and it has to be: an anonymous browser calls it. It is therefore treated as an attack surface.

* Bots are filtered by signature — around fifty families, configurable.
* A visit stops being recorded beyond five hundred pages: no real visit reaches that number.
* Throughput is capped per network and per hour, which prevents inflating the tables by changing the browser signature on every call.
* Received parameters are bounded in number and length before any processing.

= The administration =

* Every write action checks a capability **and** an anti-CSRF token.
* Maintenance tasks only run for an authorised account, never from an arbitrary AJAX call.
* Every parameterised query goes through `$wpdb->prepare()`. The only interpolated values are table names built by the plugin.
* Every output is escaped when displayed.

= CSV exports =

A spreadsheet executes any cell starting with `=`, `+`, `-` or `@`. Our exports contain values coming from the outside — a campaign name, a page path, a search term. A visitor arriving with `?utm_campaign==1+1` would have been enough to trigger a formula on the computer of whoever opens the export.

Those cells are neutralised with a leading apostrophe. Negative amounts, on the other hand, are recognised as numbers and left untouched: a safe but uncomputable export would have been useless.

= Uninstallation =

Deactivation touches no data. Deletion also keeps it by default. To request a complete wipe, first enable the corresponding option in **Analytics → Settings → Data**, then delete the plugin.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate this plugin.
3. Review **Analytics → Settings**: retention periods, excluded roles, excluded paths.

Measurement starts immediately. The consolidated history, on the other hand, builds up night after night.

== Frequently Asked Questions ==

= Is a consent banner required? =

The default setting drops no cookie, but that characteristic alone does not allow a universal legal conclusion. Check your complete configuration and your jurisdiction, and mention the measurement in your privacy policy.

= Why do the figures differ from Google Analytics? =

The scopes differ: the tag is served from your own domain, excluded bots and roles are configurable, the identifier changes every day, and the definitions of sessions, bounces and conversions are not necessarily those of another tool.

= Is a visitor who comes back tomorrow recognised? =

No, and that is intentional. The fingerprint changes every night. "Returning" here means: came back during the same day.

= WordPress scheduled tasks are disabled on my server =

The purge and the consolidation will not run on their own. Consolidation catches up every time the administration is opened; the purge does not. Schedule a system task on `wp-cron.php`.

== For developers ==

Available filters:

* `cbaz_hits_per_hour` — cap on calls to the collection endpoint, per network and per hour. Default: 600.
* `cbaz_insights_min_sessions` — measured visits required over the period before any lead is shown. Default: 200.
* `cbaz_help_entries` — add or reword glossary entries; `cbaz_insights` and `cbaz_insights_facts` — add your own leads.
* `cbaz_hide_admin_notices` — return `true` to deliberately hide other plugins' notices on our screens (not recommended).

Tables created, all prefixed with `{$wpdb->prefix}cbaz_`: `sessions`, `views`, `events`, `campaigns`, `daily`, `daily_dim`.

== Screenshots ==

1. Overview — visits, revenue, orders and conversion rate for the period, compared with the previous one, and the leads worth checking.
2. Every figure carries a small “i” explaining, in two sentences, what it measures and how to read it.
3. Acquisition — where visitors come from, and which sources actually convert.
4. E-commerce — the funnel from visitor to order, and where conversions are lost.
5. Products — views, add-to-cart rate and sales for each product in the catalogue.
6. Campaigns — build the UTM link and the short link of each operation, and copy them in one click.
7. Geography — conversion and revenue per country, on an interactive globe.
8. Real time — traffic and active pages over the last thirty minutes.
9. Journeys — each visit unrolled page by page, from entry to exit.
10. Settings — tracking, privacy and retention, with no account to create.

== Upgrade Notice ==

= 4.31.4 =
Clearer links to the Pro module and a fix for a real-time notice whose translatable string contained a line break. No database change.

== Changelog ==

= 4.31.4 =
* New: the plugins list now carries real links — the author name opens pluginect.com, and “Visit plugin site” opens the plugin page. Both were missing because the header declared no Plugin URI and no Author URI.
* Fix: four country names were wrong or had never been translated — “Swiss”, “The Netherlands”, “Suede” and “UNITED STATES” are now “Switzerland”, “Netherlands”, “Sweden” and “United States”.
* Fix: three labels were shouted in capitals (“LANGUAGES”, “DELETE”, “END”).
* Fix: the visit list said “1 pages”; the counter now has a proper plural.
* Fix: the last-save date printed a stray “'à'” — the connecting word was frozen in French inside the date format, which is now translatable.
* Fix: chart axes repeated a value when the figures were small integers — a real-time chart peaking at 3 was labelled “3 2 2 1 0”. Steps are now whole numbers, and the scale is often tighter than before.
* Pro links: every mention of the Pro module now leads to the same presentation page and says which screen it comes from; the export button no longer vanishes without Pro — it states that CSV exports belong to Pro and links there. The real-time screen uses the same discreet notice as the other screens. “Settings” and “Pro” links added under the plugin name in the extensions list.
* Fix: a real-time notice carried a line break and tabs inside its translatable string.
* Housekeeping: leftover references to the former internal project name removed from source comments and file headers.

= 4.31.3 =
* Naming: the plugin slug is now `pluginect-analytics-for-woocommerce` — WooCommerce asks integrations to carry its name as a “for WooCommerce” suffix, never as a bare word attached to the product name.
* Fix: the small “i” tooltips were unusable — their CSS class collided with the chart tooltip, which is positioned absolutely, so every button floated above its label; and the admin script died on the first line whenever `wp-i18n` had not loaded yet, leaving no tooltip at all. The glossary now uses its own class, the script survives a missing `wp-i18n`, and each button keeps a native tooltip as a fallback.
* Fix: the French plural of the hidden-notices message was empty, so “36 notices” displayed as “36 notice”.
* Admin menu renamed from “Analytics” to “Pluginect Analytics”.
* New: every indicator and section carries a small “i” that explains, in two sentences, what it measures and how to read it — keyboard and touch friendly.
* New: “Leads worth checking” — simple rules applied to the period’s figures (mobile conversion gap, abandoned carts, entry page that bounces, source that never converts, product viewed but never added to the cart, repeated searches, country without orders, attribution memory off…). Nothing is shown below 200 measured visits; every lead shows the numbers behind it and a reminder that it proves nothing. Can be switched off in Settings.
* Fix: the “Visitor paths” view logged a PHP warning for the first page of every visit.
* Fix: a comment placed inside the `CREATE TABLE` statement of the events table was read by `dbDelta()` as a column to add, which logged an invalid `ALTER TABLE` on every activation and upgrade.
* Compliance: readme written in English, "Tested up to" raised to WordPress 7.1, WooCommerce compatibility headers added.
* Hardening: every `$_SERVER` read goes through a single sanitising helper; request parameters are unslashed and sanitised consistently; every direct database query is documented and reviewed against the WordPress coding standards.
* Packaging: development documents removed from the plugin root; the third-party licence inventory now lives in `licenses/`.
* Internationalisation: source strings are now in English; the French interface ships as a bundled `fr_FR` catalogue (PHP and JavaScript) and the interface follows the site language.

= 4.31.2 =
* Fix: the `cbaz_tabs` filter was placed after a `return` and was therefore never applied. Screens added by an extension — Attribution, History and Reports from the Pro module — remained invisible in the menu.

= 4.31.1 =
* Accuracy: a page view is no longer incremented by each of its events; engaged time is now actually visible.
* WooCommerce: HPOS, time zones, refunds, classic cart and blocks brought under the same definitions.
* Security: public events bounded and persistent attribution signed.
* Free/Pro: the ten-journey limit is applied at the data level and compatibility is checked before unlocking.
* Data: kept by default on uninstallation, complete wipe on explicit choice.

= 4.31.0 =
* Fix: times displayed two hours ahead in summer. Our tables store site time, but it was read back as UTC before being converted again — a 5:47 pm visit showed as 7:47 pm. Durations were not affected.
* The product name is captured at the time of viewing: it stays readable in the journey even after a rename or a deletion.
* Journeys: the product viewed or added to cart is named, shows its price, and links to its page.

= 4.14.0 =
* Security: formula injection neutralised in CSV exports.
* Security: throughput cap per network on the collection endpoint.
* Security: maintenance tasks restricted to authorised accounts.
* Complete uninstallation (tables, settings, transients, order metadata).
* Icons on indicators and block headers.
* Complete documentation.

= 4.13.0 =
* IP address truncated before computing the fingerprint: it can no longer be recomputed.
* Installation-specific secret in the fingerprint computation.
* Cap of five hundred pages per visit.
* Custom date range in the period selector.

= 4.12.0 =
* Cards laid out in columns: no more empty space between blocks.
* Variations always displayed, arrow after the percentage.

= 4.11.0 =
* Ten-year consolidated history, with a month-by-month and year-by-year comparison screen.

= 4.10.0 =
* Brand logos embedded, no outgoing request.
* Bot exclusion setting.

= 4.9.0 =
* Source directory: "l.instagram.com", "instagram.com" and "ig" finally count as a single channel.
* Medium "direct" instead of "(none)".

= 4.8.0 =
* Journeys screen: each visit unrolled page by page.
* No cookie by default.
