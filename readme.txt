=== PMPro WooCommerce ===
Contributors: strangerstudios, jessicaoros
Tags: pmpro, woocommerce, member, prices, pricing, membership, subscription
Requires at least: 3.8
Tested up to: 3.8
Stable tag: .2

Integrates Paid Memberships Pro with WooCommerce.

== Description ==

This plugin requires Paid Memberships Pro and WooCommerce be installed, activated, and configured.

If a user purchases a certain product, give them the cooresponding membership level. Set the $pmprowoo_product_levels global array.

If WooCommerce subscriptions are installed, and a subscription is cancelled, cancel the cooresponding PMPro membership level. Set the $pmprowoo_product_levels global array.

Can give members discounts. Either set member pricing on the edit products page or set the $pmprowoo_member_discounts global array.

== Installation ==

1. Upload the `pmpro-woocommerce` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Set the $pmprowoo_product_levels global array in a custom plugin or your active theme's functions.php.
1. Optionally set the $pmprowoo_member_discounts global array in a custom plugin or your active theme's functions.php.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-woocommerce/issues

== Changelog ==

= .2 =
* Added per level pricing to the edit product page. (Thanks, jessicaoros)

= .1 =
* This is the initial version of the plugin.