<?php
/**
 * Plugin Name: Paid Memberships Pro - WooCommerce Add On
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-woocommerce/
 * Description: Integrate WooCommerce with Paid Memberships Pro.
 * Version: 1.7.3
 * WC requires at least: 3.3
 * WC tested up to: 5.6.0
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com/
 * Text Domain: pmpro-woocommerce
 * Domain Path: /languages
 */

//constants
define( 'PMPROWC_DIR', dirname( __FILE__ ) );
define( 'PMPROWC_BASENAME', plugin_basename( __FILE__ ) );

// Includes
require_once( PMPROWC_DIR . '/includes/functions.php' );
require_once( PMPROWC_DIR . '/includes/admin.php' );

/**
 * Loads modules and globals on init
 */
function pmprowoo_init() {
	// Get all Product Membership Levels
	global $pmprowoo_product_levels;
	$pmprowoo_product_levels = get_option( '_pmprowoo_product_levels' );
	if ( empty( $pmprowoo_product_levels ) ) {
		$pmprowoo_product_levels = array();
	}
	
	// Get all Gift Membership Codes
	global $pmprowoo_gift_codes;
	$pmprowoo_gift_codes = get_option( '_pmprowoo_gift_codes' );
	if ( empty( $pmprowoo_gift_codes ) ) {
		$pmprowoo_gift_codes = array();
	}
	
	// Get all Membership Discounts
	global $pmprowoo_member_discounts;
	$pmprowoo_member_discounts = get_option( '_pmprowoo_member_discounts' );
	if ( empty( $pmprowoo_member_discounts ) ) {
		$pmprowoo_member_discounts = array();
	}
	
	// Apply Discounts to Subscriptions
	global $pmprowoo_discounts_on_subscriptions;
	$pmprowoo_discounts_on_subscriptions = get_option( 'pmpro_pmprowoo_discounts_on_subscriptions' );	
	if ( empty( $pmprowoo_discounts_on_subscriptions ) ) {
		$pmprowoo_discounts_on_subscriptions = false;
	}

	// Load gift levels module if that addon is active.
	if ( function_exists( 'pmprogl_plugin_row_meta' ) ) {
		require_once( dirname( __FILE__ ) . '/includes/pmpro-gift-levels.php' );
	}

	// If MMPU is active, allow for multiple membership products in your cart
	if ( defined( 'PMPROMMPU_VER') ) {
		remove_filter( 'woocommerce_is_purchasable', 'pmprowoo_is_purchasable', 10, 2 );
	}
}
add_action( 'init', 'pmprowoo_init' );

/**
 * Disable other membership products if a membership product is in the cart already
 *
 * @param bool        $is_purchasable
 * @param \WC_Product $product
 *
 * @return bool
 */
function pmprowoo_is_purchasable( $is_purchasable, $product ) {
	
	global $pmprowoo_product_levels;
	
	// Not purchasable for some other reason.
	if( ! $is_purchasable ) {
		return $is_purchasable;
	}

	$has_membership = pmprowoo_cart_has_membership();
	$product_id     = $product->get_id();
	$is_purchasable = ( in_array( $product_id, array_keys( $pmprowoo_product_levels ) ) && true === $has_membership ? false : $is_purchasable );
	
	if ( false === $is_purchasable ) {
		// Add the warning message for the single product summary page(s)
		add_action( 'woocommerce_single_product_summary', 'pmprowoo_purchase_disabled' );
	}
	
	return $is_purchasable;
}
add_filter( 'woocommerce_is_purchasable', 'pmprowoo_is_purchasable', 10, 2 );

/**
 * Info message when attempting to add a 2nd membership level to the cart
 */
function pmprowoo_purchase_disabled() {
	$cart_url = wc_get_cart_url();
	?>
    <div class="woocommerce">
        <div class="woocommerce-info wc-nonpurchasable-message">
			<?php printf(
				__( "You may only add one membership to your %scart%s.", 'pmpro-woocommerce' ),
				sprintf( '<a href="%1$s" title="%2$s">', $cart_url, __( 'Cart', 'pmpro-woocommerce' ) ),
				'</a>'
			);
			?>
        </div>
    </div>
	<?php
}

/**
 * Add users to membership levels after order is completed.
 *
 * @param int $order_id
 */
function pmprowoo_add_membership_from_order( $order_id ) {
	global $wpdb, $pmprowoo_product_levels;
	
	// quitely exit if PMPro isn't active
	if ( ! defined( 'PMPRO_DIR' ) && ! function_exists( 'pmpro_init' ) ) {
		return;
	}
	
	//don't bother if array is empty
	if ( empty( $pmprowoo_product_levels ) ) {
		return;
	}
	
	/*
		does this order contain a membership product?
	*/
	//membership product ids
	$membership_product_ids = pmprowoo_get_membership_products_from_order( $order_id );
	
	//get order
	$order = new WC_Order( $order_id );
	
	//does the order have a user id and some products?
	$user_id = $order->get_user_id();
	if ( ! empty( $user_id ) && sizeof( $order->get_items() ) > 0 ) {
		foreach ( $order->get_items() as $item ) {
			if ( ! empty( $item['product_id'] ) &&
			     in_array( $item['product_id'], $membership_product_ids ) )    //not sure when a product has id 0, but the Woo code checks this
			{
				//is there a membership level for this product?
				//get user id and level
				$pmpro_level = pmpro_getLevel( $pmprowoo_product_levels[ $item['product_id'] ] );
				
				//if checking out for the same level they have, keep their old start date
				$sqlQuery = $wpdb->prepare(
					"SELECT startdate
                                FROM {$wpdb->pmpro_memberships_users}
                                WHERE user_id = %d
                                  AND membership_id = %d
                                  AND status = 'active'
                                ORDER BY id DESC
                                LIMIT 1",
					$user_id,
					$pmpro_level->id
				);
				
				$old_startdate = $wpdb->get_var( $sqlQuery );
				if ( ! empty( $old_startdate ) ) {
					$startdate = "'" . $old_startdate . "'";
				} else {
					$startdate = "'" . current_time( 'mysql' ) . "'";
				}
				
				//create custom level to mimic PMPro checkout
				$custom_level = array(
					'user_id'         => $user_id,
					'membership_id'   => $pmpro_level->id,
					'code_id'         => '', //will support PMPro discount codes later
					'initial_payment' => $item['line_total'],
					'billing_amount'  => '',
					'cycle_number'    => '',
					'cycle_period'    => '',
					'billing_limit'   => '',
					'trial_amount'    => '',
					'trial_limit'     => '',
					'startdate'       => $startdate,
					'enddate'         => '0000-00-00 00:00:00',
				);
				
				//set enddate
				if ( ! empty( $pmpro_level->expiration_number ) ) {
					$custom_level['enddate'] = date( "Y-m-d H:i:00", strtotime( "+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time( 'timestamp' ) ) );
				}

				/** 
				 * Filter for the level object.
				 */
				$custom_level = apply_filters( 'pmprowoo_checkout_level', $custom_level );
				
				// Is MMPU activated?
				if ( function_exists( 'pmprommpu_addMembershipLevel' ) ) {
					// Allow filter to force add levels (ignore MMPU group level settings).
					$mmpu_force_add_level = apply_filters( 'pmprowoo_mmpu_force_add_level', false );
					pmprommpu_addMembershipLevel( $custom_level, $user_id, $mmpu_force_add_level );
				} else {
					// Only add the first membership level found.
					pmpro_changeMembershipLevel( $custom_level, $user_id );
					break;
				}
			}
		}
	}
}
add_action( 'woocommerce_order_status_completed', 'pmprowoo_add_membership_from_order' );

/**
 * Cancel memberships when orders go into pending, processing, refunded, failed, or on hold.
 *
 * @param int $order_id
 */
function pmprowoo_cancel_membership_from_order( $order_id ) {
	global $pmprowoo_product_levels;

	// quitely exit if PMPro isn't active
	if ( ! defined( 'PMPRO_DIR' ) && ! function_exists( 'pmpro_init' ) ) {
		return;
	}
	
	//don't bother if array is empty
	if ( empty( $pmprowoo_product_levels ) ) {
		return;
	}
	
	//membership product ids
	$membership_product_ids = pmprowoo_get_membership_products_from_order( $order_id );
	
	//get order
	$order = new WC_Order( $order_id );
	
	//does the order have a user id and some products?
	$user_id = $order->get_user_id();
	if ( ! empty( $user_id ) && sizeof( $order->get_items() ) > 0 ) {
		foreach ( $order->get_items() as $item ) {
			//not sure when a product has id 0, but the Woo code checks this
			if ( ! empty( $item['product_id'] ) && in_array( $item['product_id'], $membership_product_ids ) ) {
				//check if another active subscription exists
				if ( ! pmprowoo_user_has_active_membership_product_for_level( $user_id, $pmprowoo_product_levels[ $item['product_id'] ] ) ) {
					//is there a membership level for this product?
					//remove the user from the level
					pmpro_cancelMembershipLevel($pmprowoo_product_levels[$item['product_id']], $user_id);
				}
			}
		}
	}
}
add_action( "woocommerce_order_status_refunded", "pmprowoo_cancel_membership_from_order" );
add_action( "woocommerce_order_status_failed", "pmprowoo_cancel_membership_from_order" );
add_action( 'woocommerce_subscription_status_on-hold_to_active', 'pmprowoo_activated_subscription' );
add_action( "woocommerce_order_status_cancelled", "pmprowoo_cancel_membership_from_order" );

/**
 * Activate memberships when WooCommerce subscriptions change status.
 *
 * @param \WC_Subscription $subscription
 */
function pmprowoo_activated_subscription( $subscription ) {
	global $pmprowoo_product_levels;

	// quitely exit if PMPro isn't active
	if ( ! defined( 'PMPRO_DIR' ) && ! function_exists( 'pmpro_init' ) ) {
		return;
	}
	
	//don't bother if array is empty
	if ( empty( $pmprowoo_product_levels ) ) {
		return;
	}
	
	if ( is_numeric( $subscription ) ) {
		$subscription = wcs_get_subscription( $subscription );
	}
	/*
		Does this order contain a membership product?
		Since v2 of WCSubs, we need to check all line items
	*/
	$order_id = $subscription->get_last_order();
	if( version_compare( get_option( 'woocommerce_subscriptions_active_version' ), '2.0', '>' ) ) {
		$user_id = $subscription->get_user_id();
		$items = $subscription->get_items();
	} else {
		$order    = wc_get_order( $order_id );
		$items    = $order->get_items();
		$user_id  = $order->get_user_id();
	}
	
	if ( ! empty( $items ) && ! empty( $user_id ) ) {
		//membership product ids
		$membership_product_ids = pmprowoo_get_membership_products_from_order( $order_id );
		
		//does the order item have a user id and a product?
		foreach ( $items as $item ) {
			
			if ( ! empty( $item['product_id'] ) && in_array( $item['product_id'], $membership_product_ids ) ) {
				// Is MMPU activated?
				if ( function_exists( 'pmprommpu_addMembershipLevel' ) ) {
					// Allow filter to force add levels (ignore MMPU group level settings).
					$mmpu_force_add_level = apply_filters( 'pmprowoo_mmpu_force_add_level', false );
					pmprommpu_addMembershipLevel( $pmprowoo_product_levels[ $item['product_id'] ], $user_id, $mmpu_force_add_level );
				} else {
					// Only add the first membership level found.
					pmpro_changeMembershipLevel( $pmprowoo_product_levels[ $item['product_id'] ], $user_id );
					break;
				}
			}
		}
	}
}
add_action( 'woocommerce_subscription_status_active', 'pmprowoo_activated_subscription' );
add_action( 'woocommerce_subscription_status_on-hold_to_active', 'pmprowoo_activated_subscription' );

/**
 * Cancel memberships when WooCommerce subscriptions change status.
 *
 * @param \WC_Subscription $subscription
 */
function pmprowoo_cancelled_subscription( $subscription ) {
	global $pmprowoo_product_levels;

	// quitely exit if PMPro isn't active
	if ( ! defined( 'PMPRO_DIR' ) && ! function_exists( 'pmpro_init' ) ) {
		return;
	}
	
	//don't bother if array is empty
	if ( empty( $pmprowoo_product_levels ) ) {
		return;
	}
	
	if ( is_numeric( $subscription ) ) {
        $subscription = wcs_get_subscription( $subscription );
    }
      
	/*
		Does this order contain a membership product?
		Since v2 of WCSubs, we need to check all line items
	*/
	$order_id = $subscription->get_last_order();
	if( version_compare( get_option( 'woocommerce_subscriptions_active_version' ), '2.0', '>' ) ) {
		$user_id = $subscription->get_user_id();
		$items = $subscription->get_items();
	} else {
		$order    = wc_get_order( $order_id );
		$items    = $order->get_items();
		$user_id  = $order->get_user_id();
	}
	
	if ( ! empty( $items ) && ! empty( $user_id ) ) {
		//membership product ids
		$membership_product_ids = pmprowoo_get_membership_products_from_order( $order_id );
		
		foreach ( $items as $item ) {
			//does the order have a user id and some products?
			if ( ! empty( $item['product_id']  && in_array($item['product_id'], $membership_product_ids)) ) {
				//check if another active subscription exists
				if (  ! pmprowoo_user_has_active_membership_product_for_level( $user_id, $pmprowoo_product_levels[ $item['product_id'] ] ) ) {	
					//is there a membership level for this product?
					if( !$has_sub && in_array($item['product_id'], $membership_product_ids) ){
						//remove the user from the level
						pmpro_cancelMembershipLevel($pmprowoo_product_levels[$item['product_id']], $user_id);
					}
				}
			}
		}
	}
}

//WooCommerce Subscriptions v2 hooks
add_action( 'woocommerce_subscription_status_cancelled', 'pmprowoo_cancelled_subscription', 10 );
add_action( 'woocommerce_subscription_status_trash', 'pmprowoo_cancelled_subscription', 10 );
add_action( 'woocommerce_subscription_status_expired', 'pmprowoo_cancelled_subscription', 10 );
add_action( 'woocommerce_subscription_status_on-hold', 'pmprowoo_cancelled_subscription', 10 );
add_action( 'woocommerce_scheduled_subscription_end_of_prepaid_term', 'pmprowoo_cancelled_subscription', 10 );

/**
 * Update Product Prices with Membership Price and/or Discount
 *
 * @param float      $price
 * @param WC_Product $product
 *
 * @return string
 */
function pmprowoo_get_membership_price( $price, $product ) {
	global $current_user, $pmprowoo_member_discounts, $pmprowoo_product_levels, $pmprowoo_discounts_on_subscriptions;
	
	// quitely exit if PMPro isn't active
	if ( ! defined( 'PMPRO_DIR' ) && ! function_exists( 'pmpro_init' ) ) {
		return $price;
	}

	// make sure $product is a product object
	if (! is_object( $product ) ) {
		$product = wc_get_product( $product );
	}

	// no product? bail
	if ( empty( $product ) ) {
		return $price;
	}
	
	$discount_price = $price;
	
	$membership_product_ids = array_keys( $pmprowoo_product_levels );
	$items       = is_object( WC()->cart ) ? WC()->cart->get_cart_contents() : array(); // items in the cart
	
	//ignore membership products and subscriptions if we are set that way
	if ( ( ! $pmprowoo_discounts_on_subscriptions || $pmprowoo_discounts_on_subscriptions == 'No' ) && ( $product->get_type() == "subscription" || $product->get_type() == "variable-subscription" || in_array( $product->get_id(), array_keys( $pmprowoo_product_levels ), false ) ) ) {
		return $price;
	}
	
	// Search for any membership level products. IF found, use first one as the cart membership level.
	foreach ( $items as $item ) {
		if ( in_array( $item['product_id'], $membership_product_ids ) ) {
			$cart_membership_level = $pmprowoo_product_levels[ $item['product_id'] ];
			break;
		}
	}
	
	// use cart membership level price if set, otherwise use current member level
	if ( isset( $cart_membership_level ) ) {
		$level_price = '_level_' . $cart_membership_level . '_price';
		$level_id    = $cart_membership_level;
	} else if ( pmpro_hasMembershipLevel() ) {
		$level_price = '_level_' . $current_user->membership_level->id . '_price';
		$level_id    = $current_user->membership_level->id;
	} else {
		return $price;
	}
	
	// use this level to get the price
	if ( isset( $level_price ) ) {
		if( $product->get_type() === 'variation' ){
			$product_id = $product->get_parent_id(); //for variations	
		} else {
			$product_id = $product->get_id();
		}
		$level_price = get_post_meta( $product_id, $level_price, true );
		if ( ! empty( $level_price ) || $level_price === '0' || $level_price === '0.00' || $level_price === '0,00' ) {
			$discount_price = $level_price;
		}
		
		// apply discounts if there are any for this level
		if ( isset( $level_id ) && ! empty( $pmprowoo_member_discounts ) && ! empty( $pmprowoo_member_discounts[ $level_id ] ) ) {
			$discount_price = $discount_price - ( $discount_price * $pmprowoo_member_discounts[ $level_id ] );
		}
	}

	$discount_price = apply_filters( 'pmprowoo_get_membership_price', $discount_price, $level_id, $price, $product );

	return $discount_price;
}


// only change price if this is on the front end
if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
	add_filter( "woocommerce_product_get_price", "pmprowoo_get_membership_price", 10, 2 );
	add_filter( "woocommerce_product_variation_get_price", "pmprowoo_get_membership_price", 10, 5 );
	add_filter( "woocommerce_variable_price_html", "pmprowoo_woocommerce_variable_price_html", 10, 2 );
}

/**
 * Update Variable Product Price Range
 *
 * @param string               $variation_range_html
 * @param \WC_Product_Variable $product
 *
 * @return string
 */
function pmprowoo_woocommerce_variable_price_html( $variation_range_html, $product ) {
	$prices = $product->get_variation_prices( true );
	$prices_product_ids = array_keys( $prices['price']) ;

	$min_price = current( $prices['price'] );
	$min_price_product_id = current( $prices_product_ids );
	$max_price = end( $prices['price'] );
	$max_price_product_id = end( $prices_product_ids );
	
	$member_min_price = pmprowoo_get_membership_price( $min_price, $min_price_product_id );
	$member_max_price = pmprowoo_get_membership_price( $max_price, $max_price_product_id );
	
	// If variation price for min and max price are identical, show one price only.
	if ( $member_min_price === $member_max_price ) {
		return wc_price( $member_max_price );
	}

	return wc_format_price_range( $member_min_price, $member_max_price );
}

/**
 * Add PMPro Tab to Edit Products page
 */
function pmprowoo_tab_options_tab() {
	?>
    <li class="pmprowoo_tab"><a href="#pmprowoo_tab_data"><span><?php esc_html_e( 'Membership', 'pmpro-woocommerce' ); ?></span></a></li>
	<?php
}

add_action( 'woocommerce_product_write_panel_tabs', 'pmprowoo_tab_options_tab' );

/**
 * Add Fields to PMPro Tab
 */
function pmprowoo_tab_options() {
	
	global $membership_levels, $post;
	
	$membership_level_options = array( 'None' );
	
	foreach ( $membership_levels as $option ) {
		$key                              = $option->id;
		$membership_level_options[ $key ] = $option->name;
	}
	?>
    <div id="pmprowoo_tab_data" class="panel woocommerce_options_panel">

		<div class="options_group pmprowoo_options_group-membership_product">
			<h3><?php _e( 'Give Customers a Membership Level', 'pmpro-woocommerce' ); ?></h3>
			<?php
			// Membership Product
			woocommerce_wp_select(
				array(
					'id'      => '_membership_product_level',
					'label'   => __( 'Membership Product', 'pmpro-woocommerce' ),
					'options' => $membership_level_options,
				)
			);

			// Membership Product
			if( !empty( $post->ID ) ) {
				$cbvalue = get_post_meta( $post->ID, '_membership_product_autocomplete', true );
			}
			if( empty( $cbvalue ) ) {
				$cbvalue = NULL;
			}

			woocommerce_wp_checkbox(
				array(
					'id'          => '_membership_product_autocomplete',
					'label'       => __( 'Autocomplete Order Status', 'pmpro-woocommerce' ),
					'description' => __( "Check this to mark the order as completed immediately after checkout to activate the associated membership.", 'pmpro-woocommerce' ),
					'cbvalue'	  => $cbvalue,
				)
			);
			?>
        </div> <!-- end pmprowoo_options_group-membership_product -->
		<div class="options-group pmprowoo_options_group-membership_discount">
			<h3><?php _e( 'Member Discount Pricing', 'pmpro-woocommerce' ); ?></h3>
			<p><?php printf( __( 'Set the custom price based on Membership Level. <a href="%s">Edit your membership levels</a> to set a global percent discount for all products.', 'pmpro-woocommerce' ), admin_url( 'admin.php?page=pmpro-membershiplevels' ) ); ?></p>
            <?php
			// For each membership level, create respective price field
			foreach ( $membership_levels as $level ) {
				woocommerce_wp_text_input(
					array(
						'id'          => '_level_' . $level->id . '_price',
						'label'       => __( $level->name . " Price", 'pmpro-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'placeholder' => '',
						'type'        => 'text',
						'desc_tip'    => 'true',
						'data_type'   => 'price',
					)
				);
			}
			?>
        </div> <!-- end pmprowoo_options_group-membership_discount -->
		<?php do_action( 'pmprowoo_extra_tab_options' ); ?>
    </div>
	<?php
}

add_action( 'woocommerce_product_data_panels', 'pmprowoo_tab_options' );

/**
 * Process PMPro Meta
 */
function pmprowoo_process_product_meta() {
	
	global $membership_levels, $post_id, $pmprowoo_product_levels;
	
	// get values from post
	if ( isset( $_POST['_membership_product_level'] ) ) {
		$level = intval( $_POST['_membership_product_level'] );
	}

	if ( isset( $_POST['_membership_product_autocomplete'] ) 
		&& !empty($_POST['_membership_product_autocomplete'] )
		&& $_POST['_membership_product_autocomplete'] !== 'no' ) {
		$autocomplete = 1;
	} else {
		$autocomplete = 0;
	}
	
	// update array of product levels
	if ( ! empty( $level ) ) {
		$pmprowoo_product_levels[ $post_id ] = $level;
	} else if ( isset( $pmprowoo_product_levels[ $post_id ] ) ) {
		unset( $pmprowoo_product_levels[ $post_id ] );
	}
	
	// update post meta for level and prices
	if ( isset( $level ) ) {
		update_post_meta( $post_id, '_membership_product_level', $level );
		update_option( '_pmprowoo_product_levels', $pmprowoo_product_levels );
		
		// Save each membership level price
		$decimal_separator = wc_get_price_decimal_separator();
		foreach ( $membership_levels as $level ) {
			$price = str_replace( $decimal_separator, '.', $_POST[ '_level_' . $level->id . "_price" ] );
			update_post_meta( $post_id, '_level_' . $level->id . '_price', $price );
		}
	}
	
	// update post meta for the autocomplete option
	if ( isset ( $autocomplete ) ) {
		update_post_meta( $post_id, '_membership_product_autocomplete', $autocomplete );
	}
}
add_action( 'woocommerce_process_product_meta', 'pmprowoo_process_product_meta' );

/**
 * Add Membership Discount Field to Edit Membership Page
 */
function pmprowoo_add_membership_discount() {
	global $pmprowoo_member_discounts;
	$level_id = intval( $_REQUEST['edit'] );
	if ( $level_id > 0 && ! empty( $pmprowoo_member_discounts ) && ! empty( $pmprowoo_member_discounts[ $level_id ] ) ) {
		$membership_discount = $pmprowoo_member_discounts[ $level_id ] * 100;
	} //convert back to %
	else {
		$membership_discount = '';
	}
	?>
	<hr />
    <h2 class="title"><?php _e( "Set Membership Discount", "pmpro-woocommerce" ); ?></h2>
    <p><?php _e( "Set a membership discount for this level which will be applied when a user with this membership level is logged in. The discount is applied to the product's regular price, sale price, or level-specific price set on the edit product page.", "pmpro-woocommerce" ); ?></p>
    <table>
        <tbody class="form-table">
        <tr>
            <th scope="row" valign="top"><label
                        for="membership_discount"><?php _e( "Membership Discount (%):", "pmpro-woocommerce" ); ?></label>
            </th>
            <td>
                <input type="number" min="0" max="100" name="membership_discount"
                       value="<?php echo esc_attr( $membership_discount ); ?>"/>
            </td>
        </tr>
        </tbody>
    </table>
	<?php
}
add_action( "pmpro_membership_level_after_other_settings", "pmprowoo_add_membership_discount" );

/**
 * Update Membership Level Discount
 *
 * @param int $level_id
 */
function pmprowoo_save_membership_level( $level_id ) {
	global $pmprowoo_member_discounts;
	
	//convert % to decimal
	$member_discount = ! empty( $_POST['membership_discount'] ) ? ( (float) sanitize_text_field( $_POST['membership_discount'] ) / 100 ) : 0;
	$pmprowoo_member_discounts[$level_id] = $member_discount;
	update_option( '_pmprowoo_member_discounts', $pmprowoo_member_discounts );
}
add_action( "pmpro_save_membership_level", "pmprowoo_save_membership_level" );

/**
 *  Add Discounts on Subscriptions to PMPro Advanced Settings - will uncomment when filter is added to core
 *
 * @param mixed $fields
 *
 * @return array
 */
function pmprowoo_custom_settings( $fields ) {
	if ( ! is_array( $fields ) ) {
		$fields = array();
	}
	
	$fields[] = array(
		'field_name' => 'pmprowoo_discounts_on_subscriptions',
		'field_type' => 'select',
		'label'      => __( 'Apply Member Discounts to WooCommerce Subscription and Membership Products?', 'pmpro-woocommerce' ),
		'value'      => __( 'No', 'pmpro-woocommerce' ),
		'options'    => array( __( 'Yes', 'pmpro-woocommerce' ), __( 'No', 'pmpro-woocommerce' ) ),
	);
	
	return $fields;
}
add_filter( 'pmpro_custom_advanced_settings', 'pmprowoo_custom_settings' );

/**
 * Force account creation at WooCommerce checkout if the cart includes a membership product.
 */
function pmprowoo_woocommerce_after_checkout_registration_form() {
	global $woocommerce, $pmprowoo_product_levels;
	
	// grab items from the cart	
	$items = $woocommerce->cart->cart_contents;
	
	//membership product ids
	$membership_product_ids = array_keys( $pmprowoo_product_levels );
	
	// Search for any membership level products. IF found, use first one as the cart membership level.
	foreach ( $items as $item ) {
		if ( in_array( $item['product_id'], $membership_product_ids ) ) {
			$cart_membership_level = $pmprowoo_product_levels[ $item['product_id'] ];
			break;
		}
	}
	
	if ( ! empty( $cart_membership_level ) ) {
		?>
        <script>
            jQuery('#createaccount').prop('checked', true);
            jQuery('#createaccount').parent().hide();
        </script>
		<?php
	}
}
add_action( 'woocommerce_after_checkout_registration_form', 'pmprowoo_woocommerce_after_checkout_registration_form' );

/**
 * When the Woo Commerce Billing Address fields are updated, update the equivalent PMPro Fields
 *
 * @param int    $meta_id
 * @param int    $object_id
 * @param string $meta_key
 * @param mixed  $meta_value
 */
function pmprowoo_update_user_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
	//tracks updates that are made
	global $pmprowoo_updated_user_meta;
	if ( empty( $pmprowoo_updated_user_meta ) ) {
		$pmprowoo_updated_user_meta = array();
	}
	if ( empty( $pmprowoo_updated_user_meta[ $object_id ] ) ) {
		$pmprowoo_updated_user_meta[ $object_id ] = array();
	}
	
	//array of user meta to mirror
	$um = array(
		"billing_first_name" => "pmpro_bfirstname",
		"billing_last_name"  => "pmpro_blastname",
		"billing_address_1"  => "pmpro_baddress1",
		"billing_address_2"  => "pmpro_baddress2",
		"billing_city"       => "pmpro_bcity",
		"billing_postcode"   => "pmpro_bzipcode",
		"billing_state"      => "pmpro_bstate",
		"billing_country"    => "pmpro_bcountry",
		"billing_phone"      => "pmpro_bphone",
		"billing_email"      => "pmpro_bemail",
		"pmpro_bfirstname"   => "billing_first_name",
		"pmpro_blastname"    => "billing_last_name",
		"pmpro_baddress1"    => "billing_address_1",
		"pmpro_baddress2"    => "billing_address_2",
		"pmpro_bcity"        => "billing_city",
		"pmpro_bzipcode"     => "billing_postcode",
		"pmpro_bstate"       => "billing_state",
		"pmpro_bcountry"     => "billing_country",
		"pmpro_bphone"       => "billing_phone",
		"pmpro_bemail"       => "billing_email",
	);
	
	//check if this user meta is to be mirrored
	foreach ( $um as $left => $right ) {
		if ( $meta_key == $left && ! in_array( $left, $pmprowoo_updated_user_meta[ $object_id ] ) ) {
			$pmprowoo_updated_user_meta[ $object_id ][] = $left;
			update_user_meta( $object_id, $right, $meta_value );
		}
	}
}
add_action( 'update_user_meta', 'pmprowoo_update_user_meta', 10, 4 );

/**
 * Need to add the meta_id for add filter
 *
 * @param int    $object_id
 * @param string $meta_key
 * @param mixed  $meta_value
 */
function pmprowoo_add_user_meta( $object_id, $meta_key, $meta_value ) {
	pmprowoo_update_user_meta( null, $object_id, $meta_key, $meta_value );
}
add_action( 'add_user_meta', 'pmprowoo_add_user_meta', 10, 3 );

/**
 * Apply end date extension filter to woo commerce checkouts as well
 *
 * $level_obj in the function is an object with the stored values for the level
 *
 * @param array $level_array - custom_level array for the pmpro_changeMembershipLevel call
 *
 * @return array
 */
function pmprowoo_checkout_level_extend_memberships( $level_array ) {
	$level_obj = pmpro_getLevel( $level_array['membership_id'] );
	
	//does this level expire? are they an existing user of this level?
	if ( ! empty( $level_obj ) && ! empty( $level_obj->expiration_number ) && pmpro_hasMembershipLevel( $level_obj->id, $level_array['user_id'] ) ) {
		//get the current enddate of their membership
		$user                   = get_userdata( $level_array['user_id'] );
		$user->membership_level = pmpro_getMembershipLevelForUser( $user->ID );
		$expiration_date        = $user->membership_level->enddate;
		
		//calculate days left
		$todays_date = current_time( 'timestamp' );
		$time_left   = $expiration_date - $todays_date;
		
		//time left?
		if ( $time_left > 0 ) {
			//convert to days and add to the expiration date (assumes expiration was 1 year)
			$days_left = floor( $time_left / ( 60 * 60 * 24 ) );
			
			$date_string = "Day";
			//figure out days based on period
			if ( $level_obj->expiration_period == "Hour" ) {
				$date_string = "Hour";
				$total_days = $level_obj->expiration_number;
			} else if ( $level_obj->expiration_period == "Day" ) {
				$total_days = $days_left + $level_obj->expiration_number;
			} else if ( $level_obj->expiration_period == "Week" ) {
				$total_days = $days_left + $level_obj->expiration_number * 7;
			} else if ( $level_obj->expiration_period == "Month" ) {
				$total_days = $days_left + $level_obj->expiration_number * 30;
			} else if ( $level_obj->expiration_period == "Year" ) {
				$total_days = $days_left + $level_obj->expiration_number * 365;
			}

			//update the end date
			$level_array['enddate'] = date( "Y-m-d H:i:00", strtotime( "+ $total_days $date_string", $expiration_date ) );
		}
	}
	
	return $level_array;
}
add_filter( 'pmprowoo_checkout_level', 'pmprowoo_checkout_level_extend_memberships' );

/**
 * Enqueue Admin CSS
 */
function pmprowoo_admin_enqueue_css() {
	wp_register_style( 'pmpro-woocommerce-admin', plugins_url( '/css/admin.css', __FILE__ ), null );
	wp_enqueue_style( 'pmpro-woocommerce-admin' );
}
add_action( 'admin_enqueue_scripts', 'pmprowoo_admin_enqueue_css' );

/**
 * Check if Autocomplete setting is active for the product.
 *
 * @param int $order_id
 */
function pmprowoo_order_autocomplete( $order_id ) {
	//get the existing order
	$order = new WC_Order( $order_id );
	
	//assume we won't autocomplete
	$autocomplete = false;
	
	//get line items
	if ( count( $order->get_items() ) > 0 ) {
		foreach ( $order->get_items() as $item ) {
			if ( $item['type'] == 'line_item' ) {
				//get product info and check if product is marked to autocomplete
				$_product = $item->get_product();
				$product_id = $_product->get_id();
				$product_autocomplete = get_post_meta( $product_id, '_membership_product_autocomplete', true );
				
				//if any product is not virtual and not marked for autocomplete, we won't autocomplete
				if ( ! $_product->is_virtual() && ! $product_autocomplete ) {
					//found a non-virtual, non-membership product in the cart
					$autocomplete = false;
					break;
				} else if ( $product_autocomplete ) {
					//found a membership product in the cart marked to autocomplete
					$autocomplete = true;
				}
			}
		}
	}
	
	//change status if needed
	if ( ! empty( $autocomplete ) ) {
		$order->update_status( 'completed', __( 'Autocomplete via PMPro WooCommerce.', 'pmpro-woocommerce' ) );
	}
}
add_filter( 'woocommerce_order_status_processing', 'pmprowoo_order_autocomplete' );

function pmpro_woocommerce_load_textdomain() {
  load_plugin_textdomain( 'pmpro-woocommerce', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmpro_woocommerce_load_textdomain' );