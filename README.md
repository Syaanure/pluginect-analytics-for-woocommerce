# Pluginect Analytics for WooCommerce

Audience, sales and campaign analytics for WooCommerce, hosted on your own
server. No third-party service, and no cookie with the default settings.

A shop asks three questions, always the same ones: where do people come from,
what do they do once they are here, and how much does it bring in. The usual
tools answer by sending your data to a third party and dropping cookies — which
forces a consent banner that drives part of your visitors away, so they end up
measuring less accurately what they claim to measure.

This plugin answers those questions while keeping the measurement data inside
your own WordPress database. **No measurement data is ever sent anywhere**, and
the plugin loads no remote script, font or stylesheet.

## What it measures

| Screen | What you get |
|---|---|
| Overview | Visits, revenue, orders, conversion rate, compared with the previous period |
| Real time | Traffic and active pages over the last thirty minutes |
| Acquisition | Sources, mediums, referring sites, and which of them convert |
| Behaviour | Entry and exit pages, typical paths, internal searches, drop-off points |
| E-commerce | The funnel from visitor to order, and where conversions are lost |
| Products | Views, add-to-cart rate and sales for each product |
| Campaigns | UTM links and short links, built and copied in one click |
| Geography | Conversion and revenue per country, on an interactive globe |
| Visitors | Devices, browsers, operating systems, languages, resolutions, loyalty |
| Journeys | The ten most recent visits, unrolled page by page |

Every figure carries a small **i** that explains, in two sentences, what it
measures and how to read it. A **Leads worth checking** panel applies simple
rules to the period's figures — never below 200 measured visits, always with the
numbers behind each lead, and always with the reminder that it proves nothing
and does not replace an analysis of your store.

## Privacy

The pseudonymous identifier that links two pages of the same visit is renewed
every night: a visitor who comes back the next day is a new person to the
system. That is a deliberate limitation, not an oversight. IP addresses are
never stored. Retention is configurable, and uninstalling removes every table
the plugin created.

## Requirements

WordPress 6.0+, WooCommerce, PHP 7.4+. Tested up to WordPress 7.1.

## Pro module

A separate paid extension adds the full journey history and filters, campaign
performance and attribution, real-time detail, historical comparison and CSV
exports. It is distributed on its own and is **not required**: everything in
this repository works alone, and the shared collection keeps working without it.

See <https://pluginect.com/pluginect-analytics/>.

## Licence

GPL-2.0-or-later. See `LICENSE`, and `licenses/` for the third-party assets.
