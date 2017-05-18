<?php

/**
 * Helper functions for PMPro WooCommerce
 */

/**
 * Return an array of membership product IDs for an order.
 *
 * @param $order_id
 *
 * @return array
 */
function pmprowoo_getMembershipProductsFromOrder($order_id) {

	global $pmprowoo_product_levels;

	// Create an empty array to store membership products.
	$membership_products = array();

	// Get the order object.
	$order = new WC_Order($order_id);

	// If there are no membership products or items in the order, return an empty array.
	if(empty($pmprowoo_product_levels) || (empty($order->customer_user) && sizeof($order->get_items()) > 0))
		return $membership_products;

	// Get membership product IDs.
	$product_ids = array_keys($pmprowoo_product_levels);

	// Are there any membership products?
	foreach($order->get_items() as $item) {
		if($item['product_id'] > 0 && in_array($item['product_id'], $product_ids)) 	//not sure when a product has id 0, but the Woo code checks this
			$membership_products[] = $item['product_id'];
	}

	return $membership_products;
}