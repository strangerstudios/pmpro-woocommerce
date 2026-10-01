<?php
/**
 * Helper functions for PMPro WooCommerce
 * DO NOT place any custom code in this file since
 * it will be overwritten when the plugin is updated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the product ID for an order/subscription line item.
 *
 * WooCommerce's WC_Order_Item_Product::set_product_id() validates that the
 * referenced post still exists as a 'product'. When a product has been
 * hard-deleted, that validation fails on read and get_product_id() returns 0,
 * even though the original ID is still stored in the '_product_id' item meta.
 * Fall back to that stored meta so memberships tied to deleted products can
 * still be resolved (and cancelled).
 *
 * @since 1.10.2
 *
 * @param WC_Order_Item $item
 *
 * @return int
 */
function pmprowoo_get_order_item_product_id( $item ) {
	if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
		return 0;
	}

	$product_id = (int) $item->get_product_id();

	// WC zeroes out get_product_id() when the product post is gone. Read the stored
	// meta directly via the order-item meta API; the WC_Data get_meta() getter warns
	// against fetching internal keys like "_product_id".
	if ( $product_id <= 0 && method_exists( $item, 'get_id' ) && function_exists( 'wc_get_order_item_meta' ) ) {
		$product_id = (int) wc_get_order_item_meta( $item->get_id(), '_product_id', true );
	}

	return $product_id;
}

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
		$product_id = pmprowoo_get_order_item_product_id( $item );
		if( $product_id > 0 && in_array( $product_id, $membership_product_ids ) ) 	//not sure when a product has id 0, but the Woo code checks this
			$membership_products[] = $product_id;
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

/**
 * Returns whether a user has an active WooCommerce product that gives membership
 * access to a given PMPro level.
 *
 * @param  int $user_id  user whose orders to check.
 * @param  int $level_id to search for in active orders.
 * @return boolean
 */
function pmprowoo_user_has_active_membership_product_for_level( $user_id, $level_id ) {
	global $pmprowoo_product_levels;
	if ( ! empty( $pmprowoo_product_levels ) ) {
		$user = get_userdata( intval( $user_id ) );
		// No such user? They can't have an active membership product.
		if ( ! $user ) {
			return false;
		}
		foreach ( $pmprowoo_product_levels as $product_id => $product_level_id ) {
			if ( intval( $level_id ) === intval( $product_level_id ) ) {
				$product = wc_get_product( $product_id );
				if ( ! empty( $product ) && is_object( $product ) && method_exists( $product, 'is_type' ) ) {
					if ( $product->is_type( array( 'subscription', 'variable-subscription', 'subscription_variation' ) ) ) {
						if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( $user_id, $product_id, array( 'active', 'pending-cancel' ) ) ) {
							return true;
						}
					} else {
						if ( wc_customer_bought_product( $user->data->user_email, $user->ID, intval( $product_id ) ) ) {
							return true;
						}
					}
				}
			}
		}
	}
	return false;
}
