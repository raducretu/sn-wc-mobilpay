<?php
	/*
	Plugin Name: SN MobilPay - WooCommerce Gateway
	Plugin URI: http://songnguyen.com.vn/
	Description: Extends WooCommerce by Adding the MobilPay Gateway.
	Version: 1.1
	Author: SN Thuynguyen
	Author URI: http://songnguyen.com.vn/
	License: GPLv2 or later
	Text Domain: sn-wc-mobilpay
	*/

	function mobilpay_plugin_load_plugin_textdomain() {
	    load_plugin_textdomain( 'sn-wc-mobilpay', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}
	add_action( 'plugins_loaded', 'mobilpay_plugin_load_plugin_textdomain' );

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'sn_wc_mobilpay_init', 0 );
function sn_wc_mobilpay_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	DEFINE ('SN_PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );

	// If we made it this far, then include our Gateway Class
	include_once( 'wc-mobilpay-gateway.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'sn_add_mobilpay_gateway' );
	function sn_add_mobilpay_gateway( $methods ) {
		$methods[] = 'SN_WC_MobilPay';
		return $methods;
	}
}

// Add custom action links
/*add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spyr_authorizenet_aim_action_links' );
function spyr_authorizenet_aim_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-authorizenet-aim' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}*/
//[snwcstatus]
function snwcstatus_func( $atts ){
	if(isset($_GET['order_id'])){
		$order_id = $_GET['order_id'];
		$order = new WC_Order( $order_id);
		$order_status = $order->status;
		$message='';
		if(in_array($order_status,array('cancelled', 'failed' ))) $message .= '<p>'.__('Plata a fost respinsa, te rugam sa reincerci.', 'sn-wc-mobilpay').'</p>';
		else{
			$message .= '<p>'.__('Plata a fost finalizata cu succes.', 'sn-wc-mobilpay')."</p>";
			$message .= '<p>'.__('ID comanda', 'sn-wc-mobilpay').': '.$order_id."</p>";
			$message .= '<p>Stare comanda: <strong>'.wc_get_order_status_name($order_status).'</strong></p>';
		}

		return $message;
	}
	return '';
}
add_shortcode( 'snwcstatus', 'snwcstatus_func' );
