<?php
/**
 * Helper functions for PMPro WooCommerce
 * DO NOT place any custom code in this file since
 * it will be overwritten when the plugin is updated.
 */

/**
 * Return an array of membership product IDs for an order.
 *
 * @param $order_id
 *
 * @return array
 */
function pmprowoo_get_membership_products_from_order( $order_id ) {
	global $pmprowoo_product_levels;
	
	// Create an empty array to store membership products.
	$membership_products = array();
	
	// Get the order object.
	$order = new WC_Order($order_id);
	$order_user_id = is_object( $order ) ? $order->get_user_id() : null;
	$order_items = is_object( $order ) ? $order->get_items() : array();
	
	// If there are no membership products or items in the order, return an empty array.
	if( empty( $pmprowoo_product_levels ) || ( empty( $order_user_id ) && !empty( $order_items ) ) ) {
		return $membership_products;
	}

	// Get membership product IDs.
	$membership_product_ids = array_keys($pmprowoo_product_levels);
	
	// Are there any membership products?
	foreach( $order_items as $item ) {
		if( $item['product_id'] > 0 && in_array( $item['product_id'], $membership_product_ids) ) 	//not sure when a product has id 0, but the Woo code checks this
			$membership_products[] = $item['product_id'];
	}

	return $membership_products;
}

/**
 * Search the cart for previously selected membership product
 *
 * @return bool
 */
function pmprowoo_cart_has_membership() {
	
	global $pmprowoo_product_levels;
	$has_membership = false;
	
	$cart_items = is_object( WC()->cart ) ? WC()->cart->get_cart_contents() : array();
	
	foreach ( $cart_items as $cart_item ) {
		$has_membership = $has_membership || in_array( $cart_item['product_id'], array_keys( $pmprowoo_product_levels ) );
	}
	
	return $has_membership;
}
