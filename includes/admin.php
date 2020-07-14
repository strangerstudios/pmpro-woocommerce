<?php
/**
 * Function to add links to the plugin row meta
 *
 * @param array  $links
 * @param string $file
 *
 * @return array
 */
function pmprowoo_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-woocommerce.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/pmpro-woocommerce/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-woocommerce' ) ) . '">' . __( 'Docs', 'pmpro-woocommerce' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-woocommerce' ) ) . '">' . __( 'Support', 'pmpro-woocommerce' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	
	return $links;
}

add_filter( 'plugin_row_meta', 'pmprowoo_plugin_row_meta', 10, 2 );