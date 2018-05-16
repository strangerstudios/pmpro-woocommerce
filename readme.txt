=== Paid Memberships Pro - WooCommerce Add On ===
Contributors: strangerstudios, jessica o
Tags: pmpro, paid memberships pro, woocommerce, member, prices, pricing, membership, subscription
Requires at least: 3.8
Tested up to: 4.7.3
Stable tag: 1.5

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

== Screenshots ==

1. The "Membership" meta box on a single product. Optionally use this WooCommerce Product to buy a PMPro Membership Level or set specific pricing based on membership level for each product.
2. The "Set Membership Discount" field on the "Edit Membership Level" page (Memberships > Membership Levels > Edit).

== Changelog ==
= 1.5 =
* BUG/FIX: Various PHP Warning messages (Deprecated functionality)
* ENHANCEMENT: Prevents a user from adding more than a single membership product to the shopping cart
* ENHANCEMENT: Improved function documentation

= 1.4.5 =
* BUG: Fixed issue where since WC v3.0 variable products were not having their prices adjusted properly based on the membership pricing settings.

= 1.4.4 =
* BUG: No longer cancelling out other fields set via the pmpro_custom_advanced_settings filter. (Thanks, Nurul Umbhiya)

= 1.4.3 =
* BUG: Now using the woocommerce_product_get_price filter instead of woocommerce_get_price.

= 1.4.2 =
* BUG: Fixed bug with loading our CSS. (Thanks, Hogash and VR51 on GitHub)

= 1.4.1 =
* BUG: Fixed typo in our add_action call so PMPro memberships are cancelled when the WooCommerce Subscriptions woocommerce_scheduled_subscription_end_of_prepaid_term hook fires.

= 1.4 =
* FEATURE: If the PMPro Gift Levels Addon is also active, adds settings to set a product to generate and email a gift certificate after purchase. (Thanks, Ted Barnett)
* BUG/FIX: Updated to fully support the new WooCommerce v2+ Subscriptions hooks for activation and cancelling. No longer supporting older versions of WC Subscriptions.
* BUG/FIX: Moved CSS load to proper WordPress action hook
* BUG/ENHANCEMENT: Configure proper text domain for translation
* BUG/ENHANCEMENT: Updated action hook for deprecated WooCommerce hooks
* ENHANCEMENT: Wrapping all strings for translation and using the proper text domain (pmpro-woocommerce) to support GlotPress translations.

= 1.3.1 =
* BUG: Fixed issue where products with blank membership pricing were being marked as free for members. Use "0", "0.00", or "0,00" to mark something as free. Use blank ("") to have a product use the main price or sale price.
* ENHANCEMENT: Made the wording of the member discount a bit more clear on the edit level page.

= 1.3 =
* FEATURE: Added a setting to the membership section of the edit product page with a checkbox to "mark the order as completed immediately after checkout to activate the associated membership".
* BUG: Fixed bug when setting membership price to 0.
* BUG: Fixed PHP notices on WooCommerce single product page when PMPro membership price discount was empty.
* BUG: Fixed issue where member prices were not being applied to products for members.

= 1.2.11 =
* BUG: Fixed bug where site would crash (PHP whitecreen) if Paid Memberships Pro was not active.

= 1.2.10 =
* BUG: Fixed bug when applying membership discounts to membership products and subscriptoins.
* BUG: Fixed warnings on edit membership level page.

= 1.2.9 =
* Hooking into scheduled_subscription_end_of_prepaid_term to cancel PMPro memberships for manually renewing WooCommerce Subscriptions when they hit expiration.

= 1.2.8 =
* Using current_time('timestamp') in a couple strtotime calls.
* Added links to docs and support in the "plugin row meta".

= 1.2.7 = 
* Fixed bug where startdate was not being set correctly for new users. (Thanks, liferaft) This script can be used to fix startdates for old members: https://gist.github.com/strangerstudios/4604f62e9812cf3afde7

= 1.2.6 =
* Commented out filters on "woocommerce_order_status_pending" and "woocommerce_order_status_processing" hooks. This keeps PMPro from removing a user's membership level when they are renewing which can cause issues. (Thanks, Trisha Cupra and others.)

= 1.2.5.2 =
* Fixed bug with getting the expiration_number for levels with an X months expiration. (Thanks, Arnaud Devic)

= 1.2.5.1 =
* Fixed the pmprowoo_checkout_level_extend_memberships() filter added in 1.2.5.

= 1.2.5 =
* Now applying end date extension filter to woo commerce checkouts as well. So if an existing member purchases a product for their level that has an end date, their end date will be extended from the old end date. (Thanks, trishacupra)

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

== Upgrade Notice ==

= 1.4 =
Fixes bugs related to the WooCommerce Subscriptions v2 update. Added support for translations. PLEASE NOTE that PMPro WooCommerce will no longer support older versions of WooCommerce Subscriptions. Make sure all plugins are up to date.
