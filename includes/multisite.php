<?php
/**
 * Compatibility file for Multisite installations.
 */

/**
 * Get Membership Discounts from the main site settings where possible.
 * @since TBD
 */
function pmprowoo_globals_subsite() {
	global $pmprowoo_product_levels, $pmprowoo_gift_codes, $pmprowoo_member_discounts, $pmprowoo_discounts_on_subscriptions;

	// Let's only do this in a multisite environment if we're not on the main site.
	if ( ! is_multisite() || is_main_site() ) {
		return;
	}

	// Is PMPro network subsite installed?
	if ( ! function_exists( 'pmpro_multisite_membership_init' ) ) {
		return;
	}

	// Is the WooCommerce integration active?
	if ( ! function_exists( 'pmprowoo_init' ) ) {
		return;
	}

	// Let's make sure these plugins are not network activated.
	$subsite_only_plugins = array(
		'pmpro-network-subsite',
		'pmpro-woocommerce',
	);

	foreach ( $subsite_only_plugins as $plugin ) {
		if ( is_plugin_active_for_network( $plugin . '/' . $plugin . '.php' ) ) {
			return;
		}
	}
	
	// Get the main site ID.
	$main_site_id = pmpro_multisite_get_main_site_ID();

	// No main site found, let's bail.
	if ( empty( $main_site_id ) ) {
		return;
	}

	

	// Get all Product Membership Levels
	if ( empty( $pmprowoo_product_levels ) ) {
		$pmprowoo_product_levels = get_blog_option( $main_site_id, '_pmprowoo_product_levels' );
		if ( empty( $pmprowoo_product_levels ) ) {
			$pmprowoo_product_levels = array();
		}
	}

	// Get all Gift Membership Codes
	if ( empty( $pmprowoo_gift_codes ) ) {
		$pmprowoo_gift_codes = get_blog_option( $main_site_id, '_pmprowoo_gift_codes' );
		if ( empty( $pmprowoo_gift_codes ) ) {
			$pmprowoo_gift_codes = array();
		}
	}

	// Get all Membership Discounts
	if ( empty( $pmprowoo_member_discounts ) ) {
		$pmprowoo_member_discounts = get_blog_option( $main_site_id, '_pmprowoo_member_discounts' );
		if ( empty( $pmprowoo_member_discounts ) ) {
			$pmprowoo_member_discounts = array();
		}
	}

	// Apply Discounts to Subscriptions
	if ( empty( $pmprowoo_discounts_on_subscriptions ) ) {
		$pmprowoo_discounts_on_subscriptions = get_blog_option( $main_site_id, '_pmprowoo_discounts_on_subscriptions' );
		if ( empty( $pmprowoo_discounts_on_subscriptions ) ) {
			$pmprowoo_discounts_on_subscriptions = array();
		}
	}
}
add_action( 'init', 'pmprowoo_globals_subsite', 20 );