<?php
/**
Plugin Name: PMPro WooCommerce
Plugin URI: http://www.paidmembershipspro.com/pmpro-woocommerce/
Description: Integrate WooCommerce with Paid Memberships Pro.
Version: 1.2.5.2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com

General Idea:

	1. Connect WooCommerce products to PMPro Membership Levels.
	2. If a user purchases a certain product, give them the corresponding membership level.
	3. If WooCommerce subscriptions are installed, and a subscription is cancelled, cancel the corresponding PMPro membership level.
	
	NOTE: You can still only have one level per user with PMPro.
*/

include_once(dirname(__FILE__)) . '/css/style.css';

/*
 * Global Settings
 */

// Get all Product Membership Levels
global $pmprowoo_product_levels;
$pmprowoo_product_levels = get_option('_pmprowoo_product_levels');
if (empty($pmprowoo_product_levels)) {
    $pmprowoo_product_levels = array();
}

// Get all Membership Discounts
global $pmprowoo_member_discounts;
$pmprowoo_member_discounts = get_option('_pmprowoo_member_discounts');
if (empty($pmprowoo_member_discounts)) {
    $pmprowoo_member_discounts = array();
}


// Apply Discounts to Subscriptions
global $pmprowoo_discounts_on_memberships;
$pmprowoo_discounts_on_memberships = get_option('pmpro_custom_pmprowoo_discounts_on_memberships');
if (empty($pmprowoo_discounts_on_memberships)) {
    $pmprowoo_discounts_on_memberships = false;
}

// Get all PMPro Membership Levels
global $membership_levels;
/*
	Add users to membership levels after order is completed.
*/
function pmprowoo_add_membership_from_order($order_id)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    //membership product ids
    $product_ids = array_keys($pmprowoo_product_levels);

    //get order
    $order = new WC_Order($order_id);

    //does the order have a user id and some products?
    if(!empty($order->customer_user) && sizeof($order->get_items()) > 0)
    {
        foreach($order->get_items() as $item)
        {
            if($item['product_id'] > 0) 	//not sure when a product has id 0, but the Woo code checks this
            {
                //is there a membership level for this product?
                if(in_array($item['product_id'], $product_ids))
                {

                    //get user id and level
                    $user_id = $order->customer_user;
                    $pmpro_level = pmpro_getLevel($pmprowoo_product_levels[$item['product_id']]);

                    //create custom level to mimic PMPro checkout
                    $custom_level = array(
                        'user_id' => $user_id,
                        'membership_id' => $pmpro_level->id,
                        'code_id' => '', //will support PMPro discount codes later
                        'initial_payment' => $item['line_total'],
                        'billing_amount' => '',
                        'cycle_number' => '',
                        'cycle_period' => '',
                        'billing_limit' => '',
                        'trial_amount' => '',
                        'trial_limit' => '',
                        'startdate' => 'NOW()',
                        'enddate' => '0000-00-00 00:00:00'
                    );

					//set enddate
					if(!empty($pmpro_level->expiration_number))
						$custom_level['enddate'] = date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period));
										
                    //let woocommerce handle everything but we can filter if we want to
                    pmpro_changeMembershipLevel(apply_filters('pmprowoo_checkout_level', $custom_level), $user_id);
					
                    //only going to process the first membership product, so break the loop
                    break;
                }
            }
        }
    }
}
add_action("woocommerce_order_status_completed", "pmprowoo_add_membership_from_order");

/*
	Cancel memberships when orders go into pending, processing, refunded, failed, or on hold.
*/
function pmprowoo_cancel_membership_from_order($order_id)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    //membership product ids
    $product_ids = array_keys($pmprowoo_product_levels);

    //get order
    $order = new WC_Order($order_id);

    //does the order have a user id and some products?
    if(!empty($order->customer_user) && sizeof($order->get_items()) > 0)
    {
        foreach($order->get_items() as $item)
        {
            if($item['product_id'] > 0) 	//not sure when a product has id 0, but the Woo code checks this
            {
                //is there a membership level for this product?
                if(in_array($item['product_id'], $product_ids))
                {
                    //add the user to the level
                    pmpro_changeMembershipLevel(0, $order->customer_user);

                    //only going to process the first membership product, so break the loop
                    break;
                }
            }
        }
    }
}
add_action("woocommerce_order_status_pending", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_processing", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_refunded", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_failed", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_on_hold", "pmprowoo_cancel_membership_from_order");

/*
	Activate memberships when WooCommerce subscriptions change status.
*/
function pmprowoo_activated_subscription($user_id, $subscription_key)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    $subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
    if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) )
    {
        $product_id = $subscription['product_id'];
        $order_id = $subscription['order_id'];

        //membership product ids
        $product_ids = array_keys($pmprowoo_product_levels);

        //get order
        $order = new WC_Order($order_id);

        //does the order have a user id and some products?
        if(!empty($order->customer_user) && !empty($product_id))
        {
            //is there a membership level for this product?
            if(in_array($product_id, $product_ids))
            {
                //add the user to the level
                pmpro_changeMembershipLevel($pmprowoo_product_levels[$product_id], $order->customer_user);
            }
        }
    }
}
add_action("activated_subscription", "pmprowoo_activated_subscription", 10, 2);
add_action("reactivated_subscription", "pmprowoo_activated_subscription", 10, 2);

/*
	Cancel memberships when WooCommerce subscriptions change status.
*/
function pmprowoo_cancelled_subscription($user_id, $subscription_key)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    $subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
    if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) )
    {
        $product_id = $subscription['product_id'];
        $order_id = $subscription['order_id'];

        //membership product ids
        $product_ids = array_keys($pmprowoo_product_levels);

        //get order
        $order = new WC_Order($order_id);

        //does the order have a user id and some products?
        if(!empty($order->customer_user) && !empty($product_id))
        {
            //is there a membership level for this product?
            if(in_array($product_id, $product_ids))
            {
                //add the user to the level
                pmpro_changeMembershipLevel(0, $order->customer_user);
            }
        }
    }
}
add_action("cancelled_subscription", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_trashed", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_expired", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_put_on-hold", "pmprowoo_cancelled_subscription", 10, 2);

/*
 * Update Product Prices with Membership Price and/or Discount
 */
function pmprowoo_get_membership_price($price, $product)
{
    global $current_user, $pmprowoo_member_discounts, $pmprowoo_product_levels, $woocommerce, $pmprowoo_discounts_on_subscriptions;

    $discount_price = $price;

    $product_ids = array_keys($pmprowoo_product_levels); // membership product levels
    $items = $woocommerce->cart->cart_contents; // items in the cart
	
    //ignore membership products and subscriptions if we are set that way
    if((!$pmprowoo_discounts_on_subscriptions && ($product->product_type == "subscription" || $product->product_type == "variable-subscription")) || in_array($product->id, array_keys($pmprowoo_product_levels), false))
        return $price;

    // Search for any membership level products. IF found, use first one as the cart membership level.
    foreach($items as $item)
    {
        if (in_array($item['product_id'], $product_ids)) {
            $cart_membership_level = $pmprowoo_product_levels[$item['product_id']];
            break;
        }
    }
	
    // use cart membership level price if set, otherwise use current member level
    if (isset($cart_membership_level)) {
        $level_price = '_level_' . $cart_membership_level . '_price';
        $level_id = $cart_membership_level;
    }
    elseif (pmpro_hasMembershipLevel()) {
        $level_price = '_level_' . $current_user->membership_level->id . '_price';
        $level_id = $current_user->membership_level->id;
    }
    else
        return $price;

    // use this level to get the price
    if (isset($level_price) ) {
        if (get_post_meta($product->id, $level_price, true))
            $discount_price =  get_post_meta($product->id, $level_price, true);

        // apply discounts if there are any for this level
        if(isset($level_id)) {
            $discount_price  = $discount_price - ( $discount_price * $pmprowoo_member_discounts[$level_id]);
        }
    }

    return $discount_price;
}


// only change price if this is on the front end
if (!is_admin() || defined('DOING_AJAX')) {    
	add_filter("woocommerce_get_price", "pmprowoo_get_membership_price", 10, 2);
}

/*
 * Add PMPro Tab to Edit Products page
 */

function pmprowoo_tab_options_tab() {
    ?>
    <li class="pmprowoo_tab"><a href="#pmprowoo_tab_data"><?php _e('Membership', 'pmprowoo'); ?></a></li>
<?php
}
add_action('woocommerce_product_write_panel_tabs', 'pmprowoo_tab_options_tab');

/*
 * Add Fields to PMPro Tab
 */
function pmprowoo_tab_options() {

   global $membership_levels, $pmprowoo_product_levels;

   $membership_level_options[0] = 'None';
   foreach ($membership_levels as $option) {
       $key = $option->id;
       $membership_level_options[$key] = $option->name;
   }
   ?>

    <div id="pmprowoo_tab_data" class="panel woocommerce_options_panel">

        <div class="options_group">
            <p class="form-field">                <?php
                    // Membership Product
                    woocommerce_wp_select(
                        array(
                        'id'      => '_membership_product_level',
                        'label'   => __( 'Membership Product', 'pmprowoo' ),
                        'options' => $membership_level_options
                        )
                    );
                ?>
            </p>
        </div>
        <div class="options-group">
            <p class="form-field">
                <?php
                    // For each membership level, create respective price field
                    foreach ($membership_levels as $level) {
                        woocommerce_wp_text_input(
                            array(
                                'id'                 => '_level_' . $level->id . '_price',
                                'label'              => __(  $level->name . " Price", 'pmprowoo' ),
                                'placeholder'        => '',
                                'type'               => 'text',
                                'desc_tip'           => 'true',
                                'data_type'          => 'price'
                            )
                        );
                    }
                ?>
            </p>
        </div>
    </div>
<?php
}
add_action('woocommerce_product_write_panels', 'pmprowoo_tab_options');

/*
 * Process PMPro Meta
 */
function pmprowoo_process_product_meta() {

    global $membership_levels, $post_id, $pmprowoo_product_levels;

    // Save membership product level
    $level = $_POST['_membership_product_level'];

    // update array of product levels
    if(!empty($level))
		$pmprowoo_product_levels[$post_id] = $level;
	elseif(isset($pmprowoo_product_levels[$post_id]))
		unset($pmprowoo_product_levels[$post_id]);

    if( isset( $level ) ) {
        update_post_meta( $post_id, '_membership_product_level', esc_attr( $level ));
        update_option('_pmprowoo_product_levels', $pmprowoo_product_levels);

        // Save each membership level price
        foreach ($membership_levels as $level) {
            $price = $_POST['_level_' . $level->id . "_price"];
            update_post_meta( $post_id, '_level_' . $level->id . '_price', $price);
        }
    }
}
add_action( 'woocommerce_process_product_meta', 'pmprowoo_process_product_meta' );

/*
 * Add Membership Discount Field to Edit Membership Page
 */

function pmprowoo_add_membership_discount() {

    global $pmprowoo_member_discounts;
    $level_id = intval($_REQUEST['edit']);
    if($level_id > 0)
        $membership_discount = $pmprowoo_member_discounts[$level_id] * 100; //convert back to %
    else
        $membership_discount = '';
    ?>
    <h3 class="topborder">Set Membership Discount</h3>
    <p>Set a membership discount for this level which will be applied when a user with this membership level is logged in.</p>
    <table>
        <tbody class="form-table">
        <tr>
            <th scope="row" valign="top"><label for="membership_discount">Membership Discount (%):</label></th>
            <td>
                <input type="number" min="0" max="100" name="membership_discount" value="<?php echo esc_attr($membership_discount);?>" />
            </td>
        </tr>
        </tbody>
    </table>

<?php
}

add_action("pmpro_membership_level_after_other_settings", "pmprowoo_add_membership_discount");

/*
 * Update Membership Level Discount
 */
function pmprowoo_save_membership_level($level_id) {
    global $pmprowoo_member_discounts;

    //convert % to decimal
    $member_discount = $_POST['membership_discount']/100;
    $pmprowoo_member_discounts[$level_id] = $member_discount;
    update_option('_pmprowoo_member_discounts', $pmprowoo_member_discounts);
}
add_action("pmpro_save_membership_level", "pmprowoo_save_membership_level");

/*
 *  Add Discounts on Subscriptions to PMPro Advanced Settings - will uncomment when filter is added to core
 */
function pmprowoo_custom_settings() {
    $fields = array(
        'field1' => array(
            'field_name' => 'pmprowoo_discounts_on_subscriptions',
            'field_type' => 'select',
            'label' => 'Apply Member Discounts to WC Subscription Products?',
            'value' => 'No',
            'options' => array('Yes','No')
        )
    );
    return $fields;
}
add_filter('pmpro_custom_advanced_settings', 'pmprowoo_custom_settings');

/*
	Force account creation at WooCommerce checkout if the cart includes a membership product.
*/
function pmprowoo_woocommerce_after_checkout_registration_form()
{
	global $woocommerce, $pmprowoo_product_levels;
	
	// grab items from the cart	
	$items = $woocommerce->cart->cart_contents;
	
	//membership product ids
    $product_ids = array_keys($pmprowoo_product_levels);
	
	// Search for any membership level products. IF found, use first one as the cart membership level.
    foreach($items as $item)
    {
        if(in_array($item['product_id'], $product_ids)) {
            $cart_membership_level = $pmprowoo_product_levels[$item['product_id']];
            break;
        }
    }
	
	if(!empty($cart_membership_level))
	{
?>
<script>	
	jQuery('#createaccount').prop('checked', true);
	jQuery('#createaccount').parent().hide();	
</script>
<?php
	}
}
add_action('woocommerce_after_checkout_registration_form', 'pmprowoo_woocommerce_after_checkout_registration_form');

/*
	When the Woo Commerce Billing Address fields are updated, update the equivalent PMPro Fields
*/
function pmprowoo_update_user_meta($meta_id, $object_id, $meta_key, $meta_value)
{	
	//tracks updates that are made
	global $pmprowoo_updated_user_meta;	
	if(empty($pmprowoo_updated_user_meta))
		$pmprowoo_updated_user_meta = array();
	if(empty($pmprowoo_updated_user_meta[$object_id]))
		$pmprowoo_updated_user_meta[$object_id] = array();
	
	//array of user meta to mirror
	$um = array(
		"billing_first_name" => "pmpro_bfirstname",
		"billing_last_name" => "pmpro_blastname",
		"billing_address_1" => "pmpro_baddress1",
		"billing_address_2" => "pmpro_baddress2",
		"billing_city" => "pmpro_bcity",
		"billing_postcode" => "pmpro_bzipcode",
		"billing_state" => "pmpro_bstate",
		"billing_country" => "pmpro_bcountry",
		"billing_phone" => "pmpro_bphone",
		"billing_email" => "pmpro_bemail",
		"pmpro_bfirstname" => "billing_first_name",
		"pmpro_blastname" => "billing_last_name",
		"pmpro_baddress1" => "billing_address_1",
		"pmpro_baddress2" => "billing_address_2",
		"pmpro_bcity" => "billing_city",
		"pmpro_bzipcode" => "billing_postcode",
		"pmpro_bstate" => "billing_state",
		"pmpro_bcountry" => "billing_country",
		"pmpro_bphone" => "billing_phone",
		"pmpro_bemail" => "billing_email"
	);		
		
	//check if this user meta is to be mirrored
	foreach($um as $left => $right)
	{
		if($meta_key == $left && !in_array($left, $pmprowoo_updated_user_meta[$object_id]))
		{			
			$pmprowoo_updated_user_meta[$object_id][] = $left;
			update_user_meta($object_id, $right, $meta_value);			
		}
	}
}
add_action('update_user_meta', 'pmprowoo_update_user_meta', 10, 4);

//need to add the meta_id for add filter
function pmprowoo_add_user_meta($object_id, $meta_key, $meta_value)
{
	pmprowoo_update_user_meta(NULL, $object_id, $meta_key, $meta_value);
}
add_action('add_user_meta', 'pmprowoo_add_user_meta', 10, 3);

/*
	apply end date extension filter to woo commerce checkouts as well
	
	$level_array is a custom_level array for the pmpro_changeMembershipLevel call
	$level_obj in the function is an object with the stored values for the level
*/
function pmprowoo_checkout_level_extend_memberships($level_array)
{
	$level_obj = pmpro_getLevel($level_array['membership_id']);
		
	//does this level expire? are they an existing user of this level?
	if(!empty($level_obj) && !empty($level_obj->expiration_number) && pmpro_hasMembershipLevel($level_obj->id, $level_array['user_id']))
	{
		//get the current enddate of their membership
		$user = get_userdata($level_array['user_id']);
		$user->membership_level = pmpro_getMembershipLevelForUser($user->ID);
		$expiration_date = $user->membership_level->enddate;
		
		//calculate days left
		$todays_date = current_time('timestamp');
		$time_left = $expiration_date - $todays_date;
		
		//time left?
		if($time_left > 0)
		{
			//convert to days and add to the expiration date (assumes expiration was 1 year)
			$days_left = floor($time_left/(60*60*24));
			
			//figure out days based on period
			if($level_obj->expiration_period == "Day")
				$total_days = $days_left + $level_obj->expiration_number;
			elseif($level_obj->expiration_period == "Week")
				$total_days = $days_left + $level_obj->expiration_number * 7;
			elseif($level_obj->expiration_period == "Month")
				$total_days = $days_left + $level_obj->expiration_number * 30;
			elseif($level_obj->expiration_period == "Year")
				$total_days = $days_left + $level_obj->expiration_number * 365;
			
			//update the end date
			$level_array['enddate'] = date("Y-m-d", strtotime("+ $total_days Days"));
		}
	}
		
	return $level_array;
}
add_filter('pmprowoo_checkout_level', 'pmprowoo_checkout_level_extend_memberships');