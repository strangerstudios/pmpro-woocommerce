<?php
/*
Plugin Name: Paid Memberships Pro - WooCommerce Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-woocommerce/
Description: Integrate WooCommerce with Paid Memberships Pro.
Version: 1.2.11
Author: Stranger Studios
Author URI: http://www.strangerstudios.com

General Idea:

	1. Connect WooCommerce products to PMPro Membership Levels.
	2. If a user purchases a certain product, give them the corresponding membership level.
	3. If WooCommerce subscriptions are installed, and a subscription is cancelled, cancel the corresponding PMPro membership level.
	
	NOTE: You can still only have one level per user with PMPro.
*/

// quitely exit if PMPro isn't active
if (! defined('PMPRO_DIR') && ! function_exists('pmpro_init'))
	return;
 	
include_once(dirname(__FILE__)) . '/css/style.css';

/*
 * Load Email Template
 */
function pmpro_gift_email_path($default_templates, $page_name, $type = 'email', $where = 'local', $ext = 'html') {
  $default_templates[] = dirname(__FILE__) . "/email/{$page_name}.{$ext}";
  return $default_templates;
}
add_filter('pmpro_email_custom_template_path', 'pmpro_gift_email_path', 10, 5 );

/*
 * Global Settings
 */

// Get all Product Membership Levels
global $pmprowoo_product_levels;
$pmprowoo_product_levels = get_option('_pmprowoo_product_levels');
if (empty($pmprowoo_product_levels)) {
    $pmprowoo_product_levels = array();
}

// Get all Gift Membership Codes
global $pmprowoo_gift_codes;
$pmprowoo_gift_codes = get_option('_pmprowoo_gift_codes');
if (empty($pmprowoo_gift_codes)) {
    $pmprowoo_gift_codes = array();
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
    global $wpdb, $pmprowoo_product_levels;

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

					//if checking out for the same level they have, keep their old start date
					$sqlQuery = "SELECT startdate FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . esc_sql($user_id) . "' AND membership_id = '" . esc_sql($pmpro_level->id) . "' AND status = 'active' ORDER BY id DESC LIMIT 1";		
					$old_startdate = $wpdb->get_var($sqlQuery);					
					if(!empty($old_startdate))
						$startdate = "'" . $old_startdate . "'";
					else
						$startdate = "'" . current_time('mysql') . "'";
					
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
                        'startdate' => $startdate,
                        'enddate' => '0000-00-00 00:00:00'
                    );

					//set enddate
					if(!empty($pmpro_level->expiration_number))
						$custom_level['enddate'] = date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time('timestamp')));
										
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
	Add front-end product fields for Gift Membership.
*/
function add_gift_membership_recipient_fields() {
    global $product;
    $gift_membership_code = get_post_meta($product->id, '_gift_membership_code', true);
    $gift_membership_email_option = get_post_meta($product->id, '_gift_membership_email_option', true);
    if(!empty($gift_membership_code) && !empty($gift_membership_email_option)){
      if($gift_membership_email_option == '1'){
          echo '<table class="variations gift-membership-fields" cellspacing="0">
                <tbody>
                <tr>
                <td class="label"><label for="gift-recipient-name">'. __( 'Recipient Name', 'pmprowoo' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-name" value="" style="margin:0;" /></td>
                </tr> 
                <tr>
                <td class="label"><label for="gift-recipient-email">'. __( 'Recipient Email', 'pmprowoo' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-email" value="" style="margin:0;" /></td>
                </tr>                               
                </tbody>
                </table>';
      }elseif($gift_membership_email_option == '3'){
          echo '<table class="variations gift-membership-fields" cellspacing="0">
                <tbody>
                <tr>
                <td class="label"><label for="gift-recipient-email">'. __( 'Send Email to Recipient?', 'pmprowoo' ).'</label></td>
                <td class="value"><select id="pa_send-email" class="" name="gift-send-email"> 
                   <option selected="selected" value="">Choose an option</option>
                   <option class="attached enabled" value="1">YES</option>
                   <option class="attached enabled" value="0">NO</option>
                </select></td>
                </tr> 
                <tr id="recipient-name" style="display:none;">
                <td class="label"><label for="gift-recipient-name">'. __( 'Recipient Name', 'pmprowoo' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-name" value="" style="margin:0;" /></td>
                </tr>
                <tr id="recipient-email" style="display:none;">
                <td class="label"><label for="gift-recipient-email">'. __( 'Recipient Email', 'pmprowoo' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-email" value="" style="margin:0;" /></td>
                </tr>                               
                </tbody>
                </table>';
         echo "<script type='text/javascript'>
               jQuery(document).ready(function($){
                 if ( $('#pa_send-email').val() == '1') {
                    $('#recipient-name').show();
                    $('#recipient-email').show();
                 }
                 $('#pa_send-email').on('change', function() {
                  if ( this.value == '1') {
                    $('#recipient-name').show();
                    $('#recipient-email').show();
                  } else {
                    $('#recipient-name').hide();
                    $('#recipient-email').hide();
                  }
                 });
               });
               </script>";
      }
    }

}
add_action( 'woocommerce_before_add_to_cart_button', 'add_gift_membership_recipient_fields' );


/*
	Validate Frontend fields for Gift Membership..
*/
function gift_membership_recipient_fields_validation($passed, $product_id) { 
    global $woocommerce;

    $gift_membership_code = get_post_meta($product_id, '_gift_membership_code', true);
    $gift_membership_email_option = get_post_meta($product_id, '_gift_membership_email_option', true);

    if(!empty($gift_membership_code) && !empty($gift_membership_email_option)){
     if(($gift_membership_email_option == '1') || ($_REQUEST['gift-send-email'] == '1')){
       if ( empty( $_REQUEST['gift-recipient-name'] ) ) {
           wc_add_notice( __( 'Please enter a NAME of Recipient', 'pmprowoo' ), 'error' );
           return false;
       }
       if ( empty( $_REQUEST['gift-recipient-email'] ) ) {
           wc_add_notice( __( 'Please enter a EMAIL of Recipient', 'pmprowoo' ), 'error' );
           return false;
       }
     }
    }
    return true;
}
add_action( 'woocommerce_add_to_cart_validation', 'gift_membership_recipient_fields_validation', 1, 2 );


/*
	Save Frontend fields for Gift Membership.
*/
function save_gift_membership_recipient_fields( $cart_item_data, $product_id ) {

    if( isset( $_REQUEST['gift-recipient-name'] ) ) {
        $cart_item_data[ 'gift_recipient_name' ] = $_REQUEST['gift-recipient-name'];
        $cart_item_data['unique_key'] = md5( microtime().rand() );
    }
    if( isset( $_REQUEST['gift-recipient-email'] ) ) {
        $cart_item_data[ 'gift_recipient_email' ] = $_REQUEST['gift-recipient-email'];
        $cart_item_data['unique_key'] = md5( microtime().rand() );
    }
    return $cart_item_data;
}
add_action( 'woocommerce_add_cart_item_data', 'save_gift_membership_recipient_fields', 10, 2 );


/*
	Show Frontend fields for Gift Membership in Cart.
*/
function render_gift_membership_on_cart_and_checkout( $cart_data, $cart_item = null ) {
    $custom_items = array();

    if( !empty( $cart_data ) ) {
        $custom_items = $cart_data;
    }
    if( isset( $cart_item['gift_recipient_name'] ) ) {
        $custom_items[] = array( "name" => __( 'Recipient Name', 'pmprowoo' ), "value" => $cart_item['gift_recipient_name'] );
    }
    if( isset( $cart_item['gift_recipient_email'] ) ) {
        $custom_items[] = array( "name" => __( 'Recipient Email', 'pmprowoo' ), "value" => $cart_item['gift_recipient_email'] );
    }
    return $custom_items;
}
add_filter( 'woocommerce_get_item_data', 'render_gift_membership_on_cart_and_checkout', 10, 2 );


/*
	Add Frontend fields for Gift Membership to Order meta.
*/
function gift_membership_order_meta_handler( $item_id, $values, $cart_item_key ) {
    if( isset( $values['gift_recipient_name'] ) ) {
        wc_add_order_item_meta( $item_id, "Recipient Name", $values['gift_recipient_name'] );
    }
    if( isset( $values['gift_recipient_email'] ) ) {
        wc_add_order_item_meta( $item_id, "Recipient Email", $values['gift_recipient_email'] );
    }
}
add_action( 'woocommerce_add_order_item_meta', 'gift_membership_order_meta_handler', 1, 3 );


/*
	Add gift membership code after order is completed.
*/
function pmprowoo_add_gift_code_from_order($order_id)
{
    global $wpdb, $pmprowoo_gift_codes;

    //don't bother if array is empty
    if(empty($pmprowoo_gift_codes))
        return;

    /*
        does this order contain a gift membership code?
    */
    //gift membership code product ids
    $product_ids = array_keys($pmprowoo_gift_codes);

    //get order
    $order = new WC_Order($order_id);

    //does the order have some products?
    if(sizeof($order->get_items()) > 0)
    {
        foreach($order->get_items() as $item)
        {
            if($item['product_id'] > 0) 	//not sure when a product has id 0, but the Woo code checks this
            {
                //is there a gift membership code for this product?
                if(in_array($item['product_id'], $product_ids))
                {

	            /*
		      Create Gift Code
	            */	

	           //get selected gifted discount code id
	           $gift_code_id = $pmprowoo_gift_codes[$item['product_id']];

                   // get discount code level to copy
                   $gift_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes_levels WHERE code_id = '" . $gift_code_id . "' LIMIT 1");
                   if(!$gift_level){ 
                           //possibly add an error if coupon code doesn't exist
                           return; 
                   }
          

	           //create new gift code
	           $code = "GIFT" . rand(1, 99) . pmpro_getDiscountCode(); //added rand to code to make it unique for multiple gift orders
	           $starts = current_time( 'Y-m-d', 0 );
	           $expires = date("Y-m-d", strtotime("+1 year"));		
	           $sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes (code, starts, expires, uses) VALUES('" . esc_sql($code) . "', '" . $starts . "', '" . $expires . "', '1')";
	
  	          if($wpdb->query($sqlQuery) !== false){
		      //get id of new code
		      $code_id = $wpdb->insert_id;
		
		      //add code to level
		      $sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes_levels (code_id, level_id, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, expiration_number, expiration_period) VALUES(
           '" . esc_sql($code_id) . "',
           '" . esc_sql($gift_level->level_id) . "',
           '" . esc_sql($gift_level->initial_payment) . "',
           '" . esc_sql($gift_level->billing_amount) . "',
           '" . esc_sql($gift_level->cycle_number) . "',
           '" . esc_sql($gift_level->cycle_period) . "',
           '" . esc_sql($gift_level->billing_limit) . "',
           '" . esc_sql($gift_level->trial_amount) . "',
           '" . esc_sql($gift_level->trial_limit) . "',
           '" . esc_sql($gift_level->expiration_number) . "',
           '" . esc_sql($gift_level->expiration_period) . "')";
		$wpdb->query($sqlQuery);
		
	           /*
		      //Email Gift Code
                      // Tag: !!gift_product!!  =>  Title of the Product
                      // Tag: !!membership_gift_code!!  => Generated Gift Code
	           */	
                  $recipient_name = wp_strip_all_tags($item['gift_recipient_name']);
                  $recipient_email = wp_strip_all_tags($item['gift_recipient_email']);

                  if(!empty($recipient_email)){

                     // Send Email to Recipient
                     $pmproemail = new PMProEmail();
                     $pmproemail->email = $recipient_email;
	             $pmproemail->subject = sprintf(__("A Gift from %s", "pmpro"), get_option("blogname"));
                     $pmproemail->template = 'gift_membership_code';
                     
                     $pmproemail->data = array("subject" => $pmproemail->subject, "name" => $recipient_name, "user_login" => '', "sitename" => get_option("blogname"), "membership_id" => '', "membership_level_name" => '', "siteemail" => pmpro_getOption("from_email"), "login_link" => '', "enddate" => '', "display_name" => $recipient_name, "user_email" => $recipient_email, "gift_product" => $item['name'], "membership_gift_code" => $code, "body" => pmpro_loadTemplate('gift_membership_code','local','email','html'));		
			
	            if($pmproemail->sendEmail() == false){
                       $message = "Gift Email FAILED To Recipient ". $recipient_email .". Contact Site Admin. ";
                       global $phpmailer;
                       if (isset($phpmailer)) {
                          $message .= $phpmailer->ErrorInfo;
                       }
                    } else {
                       $message = "Gift Email Sent To Recipient ". $recipient_email;
                    }
                    wc_add_notice( $message, $notice_type = 'success' );

                  } else {

                     // If no Recipient Send Email to Customer
                     $pmproemail = new PMProEmail();
                     $pmproemail->email = $order->billing_email;
	             $pmproemail->subject = sprintf(__("A Gift from %s", "pmpro"), get_option("blogname"));
                     $pmproemail->template = 'gift_membership_code';
                     
                     $pmproemail->data = array("subject" => $pmproemail->subject, "name" => $order->billing_first_name, "user_login" => '', "sitename" => get_option("blogname"), "membership_id" => '', "membership_level_name" => '', "siteemail" => pmpro_getOption("from_email"), "login_link" => '', "enddate" => '', "display_name" => $order->billing_first_name, "user_email" => $order->billing_email, "gift_product" => $item['name'], "membership_gift_code" => $code, "body" => pmpro_loadTemplate('gift_membership_code','local','email','html'));		
			
	            if($pmproemail->sendEmail() == false){
                       $message = "Gift Email FAILED To ". $order->billing_email .". Contact Site Admin. ";
                       global $phpmailer;
                       if (isset($phpmailer)) {
                          $message .= $phpmailer->ErrorInfo;
                       }
                    } else {
                       $message = "Gift Email Sent To ". $order->billing_email;
                    }
                    wc_add_notice( $message, $notice_type = 'success' );
                  }

   	        }

                }
            }
        }
    }
}
add_action("woocommerce_order_status_completed", "pmprowoo_add_gift_code_from_order");


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
//add_action("woocommerce_order_status_pending", "pmprowoo_cancel_membership_from_order");
//add_action("woocommerce_order_status_processing", "pmprowoo_cancel_membership_from_order");
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
add_action("scheduled_subscription_end_of_prepaid_term", "pmprowoo_cancelled_subscriptions", 10, 2);

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
    if(!$pmprowoo_discounts_on_subscriptions && ($product->product_type == "subscription" || $product->product_type == "variable-subscription" || in_array($product->id, array_keys($pmprowoo_product_levels), false)))
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

   global $wpdb, $membership_levels, $pmprowoo_product_levels;

   $membership_level_options[0] = 'None';
   foreach ($membership_levels as $option) {
       $key = $option->id;
       $membership_level_options[$key] = $option->name;
   }
   
   // Get Discount Codes List
   $codes_sqlQuery = "SELECT *, UNIX_TIMESTAMP(starts) as starts, UNIX_TIMESTAMP(expires) as expires FROM $wpdb->pmpro_discount_codes ";
   $codes_sqlQuery .= "WHERE code NOT LIKE 'GIFT%' ORDER BY id ASC";
   $codes = $wpdb->get_results($codes_sqlQuery, OBJECT);
   if(!$codes){
      $gift_membership_code_options[0] = 'No Discount Codes Created';
   }else{
      $gift_membership_code_options[0] = 'None';
      foreach($codes as $code){
          $key = $code->id;
          $gift_membership_code_options[$key] = $code->code;            
      }
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
        <div class="options_group">
            <p class="form-field">
                <?php
                    echo '<b>'.__( 'GIFT A MEMBERSHIP', 'pmprowoo' ).'</b>';

                    // Load Membership Discounts
                    woocommerce_wp_select(
                        array(
                        'id'      => '_gift_membership_code',
                        'label'   => __( 'Select Discount Code', 'pmprowoo' ),
                        'options' => $gift_membership_code_options,
                        'desc_tip' => 'true',
		        'description' => __( 'Upon each purchase a new one-time use code (which start with "GIFT") will be created from the code selected here.', 'pmprowoo' ) 
                        )
                    );

                    // Recipient Email
                    woocommerce_wp_select(
                        array(
                        'id'      => '_gift_membership_email_option',
                        'label'   => __( 'Email Recipient', 'pmprowoo' ),
                        'options' => array(
			             '1' => 'Yes',
			             '0' => 'No',
			             '3' => 'Customer Decides'
                                     ),
                        'desc_tip' => 'true',
		        'description' => __( 'Decide if a recipient email is entered and the code is emailed upon successful purchase.', 'pmprowoo' ) 
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

    global $membership_levels, $post_id, $pmprowoo_product_levels, $pmprowoo_gift_codes;

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

    // Save gift membership discount
    $code = $_POST['_gift_membership_code'];

    // update array of gift codes
    if(!empty($code))
		$pmprowoo_gift_codes[$post_id] = $code;
	elseif(isset($pmprowoo_gift_codes[$post_id]))
		unset($pmprowoo_gift_codes[$post_id]);

    if( isset( $code ) ) {
        update_post_meta( $post_id, '_gift_membership_code', esc_attr( $code ));
        update_option('_pmprowoo_gift_codes', $pmprowoo_gift_codes);
    }

    // Save gift membership email option
    $email_opt = $_POST['_gift_membership_email_option'];

    if( isset( $email_opt ) ) {
        update_post_meta( $post_id, '_gift_membership_email_option', esc_attr( $email_opt ));
    }    
}
add_action( 'woocommerce_process_product_meta', 'pmprowoo_process_product_meta' );

/*
 * Add Membership Discount Field to Edit Membership Page
 */

function pmprowoo_add_membership_discount() {

    global $pmprowoo_member_discounts;
    $level_id = intval($_REQUEST['edit']);
    if($level_id > 0 && !empty($pmprowoo_member_discounts) && !empty($pmprowoo_member_discounts[$level_id]))
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
			$level_array['enddate'] = date("Y-m-d", strtotime("+ $total_days Days", $todays_date));
		}
	}
		
	return $level_array;
}
add_filter('pmprowoo_checkout_level', 'pmprowoo_checkout_level_extend_memberships');

/*
Function to add links to the plugin row meta
*/
function pmprowoo_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-woocommerce.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/third-party-integration/pmpro-woocommerce/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprowoo_plugin_row_meta', 10, 2);
