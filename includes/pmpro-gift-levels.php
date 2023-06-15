<?php
/*
 * Load Email Template
 */
function pmprowoo_gift_levels_email_path($default_templates, $page_name, $type = 'email', $where = 'local', $ext = 'html') {
  $default_templates[] = PMPROWC_DIR . '/email/' . sanitize_file_name( $page_name . '.' . $ext );

  return $default_templates;
}
add_filter('pmpro_email_custom_template_path', 'pmprowoo_gift_levels_email_path', 10, 5 );

/**
 * Add the levels_link data to all PMPro Emails since our template uses it.
 */
function pmprowoo_gift_levels_pmpro_email_data($data) {
	if(!isset($data['levels_link'])) {
		$data['levels_link'] = pmpro_url('levels');
	}
	
	return $data;
}
add_filter('pmpro_email_data', 'pmprowoo_gift_levels_pmpro_email_data');

/**
 * Add front-end product fields for Gift Membership.
 */
function pmprowoo_gift_levels_add_recipient_fields() {
    global $product;	
    $product_id = $product->get_id();
	
    $gift_membership_code = get_post_meta( $product_id, '_gift_membership_code', true);
    $gift_membership_email_option = get_post_meta($product_id, '_gift_membership_email_option', true);
	
    if(!empty($gift_membership_code) && !empty($gift_membership_email_option)){
      if($gift_membership_email_option == '1'){
          echo '<table class="variations gift-membership-fields" cellspacing="0">
                <tbody>
                <tr>
                <td class="label"><label for="gift-recipient-name">'. esc_html__( 'Recipient Name', 'pmpro-woocommerce' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-name" value="" style="margin:0;" /></td>
                </tr> 
                <tr>
                <td class="label"><label for="gift-recipient-email">'. esc_html__( 'Recipient Email', 'pmpro-woocommerce' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-email" value="" style="margin:0;" /></td>
                </tr>                               
                </tbody>
                </table>';
      }elseif($gift_membership_email_option == '3'){
          echo '<table class="variations gift-membership-fields" cellspacing="0">
                <tbody>
                <tr>
                <td class="label"><label for="gift-recipient-email">'. esc_html__( 'Send Email to Recipient?', 'pmpro-woocommerce' ) . '</label></td>
                <td class="value"><select id="pa_send-email" class="" name="gift-send-email"> 
                   <option selected="selected" value="">' . esc_html__( 'Choose an option', 'pmpro-woocommerce' ) . '</option>
                   <option class="attached enabled" value="1">' . esc_html( 'YES', 'pmpro-woocommerce' ) . '</option>
                   <option class="attached enabled" value="0">' . esc_html( 'NO', 'pmpro-woocommerce' ) . '</option>
                </select></td>
                </tr> 
                <tr id="recipient-name" style="display:none;">
                <td class="label"><label for="gift-recipient-name">'. esc_html__( 'Recipient Name', 'pmpro-woocommerce' ).'</label></td>
                <td class="value"><input type="text" name="gift-recipient-name" value="" style="margin:0;" /></td>
                </tr>
                <tr id="recipient-email" style="display:none;">
                <td class="label"><label for="gift-recipient-email">'. esc_html__( 'Recipient Email', 'pmpro-woocommerce' ).'</label></td>
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
add_action( 'woocommerce_before_add_to_cart_button', 'pmprowoo_gift_levels_add_recipient_fields' );


/*
	Validate Frontend fields for Gift Membership..
*/
function pmprowoo_gift_levels_recipient_fields_validation($passed, $product_id) { 
    global $woocommerce;

    $gift_membership_code = get_post_meta($product_id, '_gift_membership_code', true);
    $gift_membership_email_option = get_post_meta($product_id, '_gift_membership_email_option', true);

    if(!empty($gift_membership_code) && !empty($gift_membership_email_option)){
     if($gift_membership_email_option == '3' && ( ! isset( $_REQUEST['gift-send-email'] ) || $_REQUEST['gift-send-email'] == '' ) ){
           wc_add_notice( esc_html__( 'Please select option for Send Email to Recipient', 'pmpro-woocommerce' ), 'error' );
           return false;
     }
     if(($gift_membership_email_option == '1') || (  $_REQUEST['gift-send-email'] == '1' ) ){
       if ( empty( $_REQUEST['gift-recipient-name'] ) ) {
           wc_add_notice( esc_html__( 'Please enter a NAME of Recipient', 'pmpro-woocommerce' ), 'error' );
           return false;
       }
       if ( empty( $_REQUEST['gift-recipient-email'] ) ) {
           wc_add_notice( esc_html__( 'Please enter a EMAIL of Recipient', 'pmpro-woocommerce' ), 'error' );
           return false;
       }
     }
    }
    return true;
}
add_action( 'woocommerce_add_to_cart_validation', 'pmprowoo_gift_levels_recipient_fields_validation', 1, 2 );


/*
	Save Frontend fields for Gift Membership.
*/
function pmprowoo_gift_levels_save_recipient_fields( $cart_item_data, $product_id ) {

    if( isset( $_REQUEST['gift-recipient-name'] ) ) {
	if( !empty( $_REQUEST['gift-recipient-name'] ) ){
        $cart_item_data[ 'gift_recipient_name' ] = sanitize_text_field( $_REQUEST['gift-recipient-name'] );
        $cart_item_data['unique_key'] = md5( microtime().rand() );
	}
    }
    if( isset( $_REQUEST['gift-recipient-email'] ) ) {
	if( !empty( $_REQUEST['gift-recipient-email'] ) ){
        $cart_item_data[ 'gift_recipient_email' ] = sanitize_email( $_REQUEST['gift-recipient-email'] );
        $cart_item_data['unique_key'] = md5( microtime().rand() );
	}
    }
    return $cart_item_data;
}
add_action( 'woocommerce_add_cart_item_data', 'pmprowoo_gift_levels_save_recipient_fields', 10, 2 );

/*
	Show Frontend fields for Gift Membership in Cart.
*/
function pmprowoo_gift_levels_render_on_cart_and_checkout( $cart_data, $cart_item = null ) {
    $custom_items = array();

    if( !empty( $cart_data ) ) {
        $custom_items = $cart_data;
    }
    if( isset( $cart_item['gift_recipient_name'] ) ) {
        $custom_items[] = array( "name" => esc_html__( 'Recipient Name', 'pmpro-woocommerce' ), "value" => $cart_item['gift_recipient_name'] );

    }
    if( isset( $cart_item['gift_recipient_email'] ) ) {
        $custom_items[] = array( "name" => esc_html__( 'Recipient Email', 'pmpro-woocommerce' ), "value" => $cart_item['gift_recipient_email'] );

    }
    return $custom_items;
}
add_filter( 'woocommerce_get_item_data', 'pmprowoo_gift_levels_render_on_cart_and_checkout', 10, 2 );

/*
	Add Frontend fields for Gift Membership to Order meta.
*/
function gift_membership_order_meta_handler( $item_id, $cart_item, $order_id ) {
    
    if( isset( $cart_item->legacy_values['gift_recipient_name'] ) ) {
        wc_add_order_item_meta( $item_id, 'Recipient Name', $cart_item->legacy_values['gift_recipient_name'] );
    }
    if( isset( $cart_item->legacy_values['gift_recipient_email'] ) ) {
        wc_add_order_item_meta( $item_id, 'Recipient Email', $cart_item->legacy_values['gift_recipient_email'] );
    }
}
add_action( 'woocommerce_new_order_item', 'gift_membership_order_meta_handler', 10, 3 );

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
    $items = $order->get_items();
	
    //does the order have some products?
    if(sizeof($items) > 0)
    {
        foreach($items as $item_id => $item)
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
                   $gift_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes_levels WHERE code_id = '" . esc_sql( $gift_code_id ) . "' LIMIT 1");
                   if(!$gift_level){ 
                           //possibly add an error if coupon code doesn't exist
                           return; 
                   }
          

	           //create new gift code
	           $code = "GIFT" . rand(1, 99) . pmpro_getDiscountCode(); //added rand to code to make it unique for multiple gift orders
	           $starts = current_time( 'Y-m-d', 0 );
	           $expires = date("Y-m-d", strtotime("+1 year"));		
	           $sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes (code, starts, expires, uses) VALUES('" . esc_sql($code) . "', '" . esc_sql( $starts ) . "', '" . esc_sql( $expires ) . "', '1')";
	
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
			  
              /* Add Code to Order Meta */
              wc_add_order_item_meta( $item_id, "Gift Code", $code );
		
	           /*
		      //Email Gift Code
                      // Tag: !!gift_product!!  =>  Title of the Product
                      // Tag: !!membership_gift_code!!  => Generated Gift Code
	           */	
                  $recipient_name = wp_strip_all_tags($item['Recipient Name']);
                  $recipient_email = wp_strip_all_tags($item['Recipient Email']);

                  if(!empty($recipient_email)){

                     // Send Email to Recipient
                     $pmproemail = new PMProEmail();
                     $pmproemail->email = $recipient_email;
	             $pmproemail->subject = sprintf( esc_html__("A Gift from %s", 'pmpro-woocommerce'), get_option('blogname'));
                     $pmproemail->template = 'gift_membership_code';
                     
                     $pmproemail->data = array("subject" => $pmproemail->subject, "name" => $recipient_name, "user_login" => '', "sitename" => get_option("blogname"), "membership_id" => '', "membership_level_name" => '', "siteemail" => get_option("pmpro_from_email"), "login_link" => '', "enddate" => '', "display_name" => $recipient_name, "user_email" => $recipient_email, "gift_product" => $item['name'], "membership_gift_code" => $code, "body" => pmpro_loadTemplate('gift_membership_code','local','email','html'));		
			
	            if($pmproemail->sendEmail() == false){
                       $message = sprintf( __( 'Gift Email FAILED To Recipient %s. Contact Site Admin.', 'pmpro-woocommerce' ), $recipient_email );
                       global $phpmailer;
                       if (isset($phpmailer)) {
                          $message .= ' ' . $phpmailer->ErrorInfo;
                       }
                    } else {
                       $message = sprintf( esc_html__( 'Gift Email Sent To Recipient %s', 'pmpro-woocommerce' ), $recipient_email );
                    }

                    if ( function_exists('wc_add_notice') ) {
                        wc_add_notice( $message, $notice_type = 'success' );
                    }

                  } else {

                     // If no Recipient Send Email to Customer
                     $pmproemail = new PMProEmail();
                     $pmproemail->email = $order->get_billing_email();
                     $pmproemail->subject = sprintf( esc_html__("A Gift from %s", 'pmpro-woocommerce'), get_option("blogname"));
                     $pmproemail->template = 'gift_membership_code';
                     
                     $pmproemail->data = array("subject" => $pmproemail->subject, "name" => $order->get_billing_first_name(), "user_login" => '', "sitename" => get_option("blogname"), "membership_id" => '', "membership_level_name" => '', "siteemail" => get_option("pmpro_from_email"), "login_link" => '', "enddate" => '', "display_name" => $order->get_billing_first_name(), "user_email" => $order->get_billing_email(), "gift_product" => $item['name'], "membership_gift_code" => $code, "body" => pmpro_loadTemplate('gift_membership_code','local','email','html'));		
			
	            if($pmproemail->sendEmail() == false){
                       $message = sprintf( __( 'Gift Email FAILED To %s. Contact Site Admin.', 'pmpro-woocommerce' ), $order->get_billing_email() );
                       global $phpmailer;
                       if (isset($phpmailer)) {
                          $message .= ' ' . $phpmailer->ErrorInfo;
                       }
                    } else {
                       $message = sprintf( esc_html__( 'Gift Email Sent To %s', 'pmpro-woocommerce' ), $order->get_billing_email() );
                    }
                   
                    if ( function_exists('wc_add_notice') ) {
                        wc_add_notice( $message, $notice_type = 'success' );
                    }
                    
                  }

   	        }

                }
            }
        }
    }
}
add_action("woocommerce_order_status_completed", "pmprowoo_add_gift_code_from_order");

/**
 * Add gift level-related options in the PMPro WooCommerce tab of the edit product page.
 */
function pmprowoo_extra_tab_options_for_gift_levels() {	
	global $wpdb;
	
	// Get Discount Codes List
	$codes_sqlQuery = "SELECT *, UNIX_TIMESTAMP(starts) as starts, UNIX_TIMESTAMP(expires) as expires FROM $wpdb->pmpro_discount_codes ";
	$codes_sqlQuery .= "WHERE `code` NOT LIKE 'GIFT%' ORDER BY id ASC";
	$codes = $wpdb->get_results($codes_sqlQuery, OBJECT);
		
	$gift_membership_code_options = array();
	if(!$codes) {
		$gift_membership_code_options[0] = esc_html__( 'No Discount Codes Created', 'pmpro-woocommerce' );
	} else {
		$gift_membership_code_options[0] = esc_html__( 'None', 'pmpro-woocommerce' );
		foreach($codes as $code) {
			$key = $code->id;
			$gift_membership_code_options[$key] = $code->code;            
		}
	}	
?>
<div class="options_group">
	<p class="form-field">
		<strong><?php esc_html_e('Gift a Membership', 'pmpro-woocommerce');?></strong><br />
		<?php                   
			// Load Membership Discounts
			woocommerce_wp_select(
				array(
				'id'      => '_gift_membership_code',
				'label'   => esc_html__( 'Select Discount Code', 'pmpro-woocommerce' ),
				'options' => $gift_membership_code_options, //Escaped further up.
				'desc_tip' => 'true',
		'description' => esc_html__( 'Upon each purchase a new one-time use code (which start with "GIFT") will be created from the code selected here.', 'pmpro-woocommerce' ) 
				)
			);

			// Recipient Email
			woocommerce_wp_select(
				array(
				'id'      => '_gift_membership_email_option',
				'label'   => esc_html__( 'Email Recipient', 'pmpro-woocommerce' ),
				'options' => array(
				 '1' => esc_html__( 'Yes', 'pmpro-woocommerce' ),
				 '0' => esc_html__( 'No', 'pmpro-woocommerce' ),
				 '3' => esc_html__( 'Customer Decides', 'pmpro-woocommerce' )
							 ),
				'desc_tip' => 'true',
		'description' => esc_html__( 'Decide if a recipient email is entered and the code is emailed upon successful purchase.', 'pmpro-woocommerce' ) 
				)
			);
		?>
	</p>
</div>
<?php
}
add_action('pmprowoo_extra_tab_options', 'pmprowoo_extra_tab_options_for_gift_levels');

/**
 * Save gift level-related options when editing a product.
 */
function pmprowoo_process_product_meta_for_gift_levels() {
	global $post_id, $pmprowoo_gift_codes;
	
    // If the fields aren't present. Bail. (Add On is probably not activated.)
    if ( ! isset( $_POST['_gift_membership_code'] ) ) {
        return;
    }
    
    // Get the code setting.    
    $code = intval( $_POST['_gift_membership_code'] );

    // Update array of gift codes.
    if( ! empty( $code ) ) {
        $pmprowoo_gift_codes[$post_id] = $code;
    } elseif ( isset( $pmprowoo_gift_codes[$post_id] ) ) {
        unset($pmprowoo_gift_codes[$post_id]);
    }		

    // Save gift membership discount.
    update_post_meta( $post_id, '_gift_membership_code', esc_attr( $code ) );
    update_option('_pmprowoo_gift_codes', $pmprowoo_gift_codes);    

    // Save gift membership email option.   
    update_post_meta( $post_id, '_gift_membership_email_option', intval( $_POST['_gift_membership_email_option'] ) );
}
add_action( 'woocommerce_process_product_meta', 'pmprowoo_process_product_meta_for_gift_levels' );
