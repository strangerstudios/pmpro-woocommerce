=== PMPro WooCommerce ===
Contributors: strangerstudios, jessica o
Tags: pmpro, woocommerce, member, prices, pricing, membership, subscription
Requires at least: 3.8
Tested up to: 3.9.1
Stable tag: 1.2.4

Integrates Paid Memberships Pro with WooCommerce.

== Description ==

This plugin requires Paid Memberships Pro and WooCommerce be installed, activated, and configured.

Features:

* Use WooCommerce Products to Buy PMPro Membership Levels
* Add Specific Pricing Based on Membership Level for Each Product
* Apply global discounts based on membership level

== Installation ==

1. Upload the `pmpro-woocommerce` directory to the `/wp-content/plugins/` directory of your site.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-woocommerce/issues

== Changelog ==
= 1.2.4 =
* Fixed bug with WooCommerce Subscriptions being put "on hold".
* Fixed bug when entering a membership price > 1000.
* Fixed bug on some setups which set membership price to 0 if nothing was entered.

= 1.2.3 =
* Fixed bug when setting member price to "0" in product settings.

= 1.2.2 =
* Added option to "Apply Member Discounts to WC Subscription Products?" to the PMPro Advanced Settings tab.
* Fixed bug where membership discounts wouldn't be applied if no membership products were in the cart.
* WooCommerce now mimics PMPro checkout, creating a custom level array instead of passing the ID. So if your level has an expiration number and period, it will be used when adding the level to the user checking out in WooCommerce... i.e. expiration dates "work" now. You can filter the level information using the pmprowoo_checkout_level filter.
* Added pmprowoo_checkout_level filter to allow filtering the checkout level (to use PMPro expiration dates, etc. if Subscriptions addon is not installed)

= 1.2.1 =
* Fixed updating of WooCommerce billing address user meta when brand new users checkout with PMPro.

= 1.2 =
* Updating user meta for billing address when the Woo Commerce billing address is updated and vice versa.

= 1.1.1 =
* Fixed fatal error that would be thrown if PMPro is not also activated.

= 1.1 =
* Fixed adding/updating membership when order status is changed to completed

= 1.0 =
* Released to the WordPress repository.

= .3.2 =
* Fixed a bug where the get_price filter wasn't running when products/prices were loaded over AJAX (e.g. in the order review).
* Added code to force account creation at checkout if the cart includes a membership level.

= .3.1 =
* Fixed bug where products were erroneously counted as "subscription products" and thus discounts may not apply. You may have to edit these products and click "update" to get the settings to save correctly.

= .3 =
* Added membership products
* Added membership discounts
* Moved PMPro options to separate tab

= .2 =
* Added per level pricing to the edit product page. (Thanks, jessica o)

= .1 =
* This is the initial version of the plugin.