<?php
/*
 * Plugin Name: WooCommerce Stcpay Gateway
 * Description: Take payments using Stcpay mobile wallet service.
 * Version: 0.0.2
 * Author: Shazzad Hossain Khan
 * Requires at least: 5.4.2
 * Tested up to: 5.4.2
 * WC requires at least: 4.0.0
 * WC tested up to: 4.2.0
 * Text Domain: woocommerce-gateway-stcpay
 * Domain Path: /languages/
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define base file
if ( ! defined( 'WC_STCPAY_PLUGIN_FILE' ) ) {
	define( 'WC_STCPAY_PLUGIN_FILE', __FILE__ );
}

/**
 * WooCommerce missing fallback notice.
 *
 * @return string
 */
function wc_stcpay_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stcpay requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-stcpay' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce version fallback notice.
 *
 * @return string
 */
function wc_stcpay_version_wc_notice() {
	echo '<div class="error"><p><strong>' . esc_html__( 'Stcpay requires mimumum WooCommerce 3.0. Please upgrade.', 'woocommerce-gateway-stcpay' ) . '</strong></p></div>';
}

/**
 * Intialize everything after plugins_loaded action
 */
add_action( 'plugins_loaded', 'wc_stcpay_init', 5 );
function wc_stcpay_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_stcpay_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		add_action( 'admin_notices', 'wc_stcpay_version_wc_notice' );
		return;
	}

	// Load the main plug class
	if ( ! class_exists( 'WC_Stcpay' ) ) {
		require dirname( __FILE__ ) . '/includes/class-wc-stcpay.php';
	}

	wc_stcpay();
}

/**
 * Plugin instance
 *
 * @return WC_Stcpay Main class instance.
 */
function wc_stcpay() {
	return WC_Stcpay::get_instance();
}
