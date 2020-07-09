<?php
/**
 * Settings for Stcpay Gateway.
 *
**/

defined( 'ABSPATH' ) || exit;

$environments = array();
$environment_fileds = array();

foreach ( WC_Stcpay_Client::$environments as $environment_id => $environment ) {
	$environments[ $environment_id ] = $environment['name'];

	$environment_fileds[ $environment_id . '_ssl_cert_file'] = array(
		'title'       => sprintf(
			/* translators: %s: Gateway Environment - Staging, Production */
			__( '%s SSL Cert File:', 'woocommerce-gateway-stcpay' ),
			$environment['name']
		),
		'type'        => 'text',
		'description' => __( 'SSL Certificate file path.', 'woocommerce-gateway-stcpay' ),
		'desc_tip'    => true
	);
	$environment_fileds[ $environment_id . '_ssl_key_file'] = array(
		'title'       => sprintf(
			/* translators: %s: Gateway Environment - Staging, Production */
			__( '%s SSL Key File:', 'woocommerce-gateway-stcpay' ),
			$environment['name']
		),
		'type'        => 'text',
		'description' => __( 'SSL Private key file path.', 'woocommerce-gateway-stcpay' ),
		'desc_tip'    => true
	);
	$environment_fileds[ $environment_id . '_ssl_password'] = array(
		'title'       => sprintf(
			/* translators: %s: Gateway Environment - Staging, Production */
			__( '%s SSL Key Password:', 'woocommerce-gateway-stcpay' ),
			$environment['name']
		),
		'type'        => 'text',
		'description' => __( 'SSL Private key password.', 'woocommerce-gateway-stcpay' ),
		'desc_tip'    => true
	);
}

return array_merge(
	array(
		'enabled' => array(
			'title'       => __( 'Enable:', 'woocommerce-gateway-stcpay' ),
			'type'        => 'checkbox',
			'label'       => ' ',
			'description' => __( 'If you do not already have Stcpay merchant account, <a href="https://stcpay.com.sa/" target="_blank">please register one</a>.', 'stcpay' ),
			'default'     => 'no',
			'desc_tip'    => true
		),
		'title' => array(
			'title'       => __( 'Title:', 'woocommerce-gateway-stcpay' ),
			'type'        => 'text',
			'description' => __( 'Title of Stcpay Payment Gateway that users see on Checkout page.', 'woocommerce-gateway-stcpay' ),
			'default'     => __( 'Stcpay', 'woocommerce-gateway-stcpay' ),
			'desc_tip'    => true
		),
		'description' => array(
			'title'       => __( 'Description:', 'woocommerce-gateway-stcpay' ),
			'type'        => 'textarea',
			'description' => __( 'Description of Stcpay Payment Gateway that users sees on Checkout page.', 'woocommerce-gateway-stcpay' ),
			'default'     => __( 'Pay securely by Stcpay wallet.', 'woocommerce-gateway-stcpay' ),
			'desc_tip'    => true
		),
		'advanced_settings' => array(
			'title' => __( 'Advanced options', 'woocommerce-gateway-stcpay' ),
			'type'  => 'title'
		),
		'merchant_id' => array(
			'title'       => __( 'Mertchant ID:', 'woocommerce-gateway-stcpay' ),
			'type'        => 'text',
			'description' => __( 'Stcpay Merchant id.', 'woocommerce-gateway-stcpay' ),
			'desc_tip'    => true
		),
		'branch_id' => array(
			'title'       => __( 'Branch ID:', 'woocommerce-gateway-stcpay' ),
			'type'        => 'text',
			'description' => __( 'Branch id used for direct payment authorization.', 'woocommerce-gateway-stcpay' ),
			'default'     => 'Main Branch',
			'desc_tip'    => true
		),
		'teller_id' => array(
 			'title'       => __( 'Teller ID:', 'woocommerce-gateway-stcpay' ),
 			'type'        => 'text',
 			'description' => __( 'Teller id used for direct payment authorization.', 'woocommerce-gateway-stcpay' ),
			'default'     => 'WooCommerce',
 			'desc_tip'    => true
 		),
		'device_id' => array(
 			'title'       => __( 'Device ID:', 'woocommerce-gateway-stcpay' ),
 			'type'        => 'text',
 			'description' => __( 'Device id used for direct payment authorization.', 'woocommerce-gateway-stcpay' ),
			'default'     => get_bloginfo( 'name' ),
 			'desc_tip'    => true
 		),
		'merchant_note' => array(
 			'title'       => __( 'Merchant Note:', 'woocommerce-gateway-stcpay' ),
 			'type'        => 'text',
 			'description' => __( 'Merchant Note used on direct payment authorization.', 'woocommerce-gateway-stcpay' ),
			'default'     => 'Make payments for ' . get_bloginfo( 'name' ),
 			'desc_tip'    => true
 		),
		'debug' => array(
			'title'       => __( 'Debug log', 'woocommerce-gateway-stcpay' ),
			'type'        => 'checkbox',
			'label'       => 'Enable logging',
			'description' => sprintf(
				/* translators: %1$s: Login file path. %2$s: Login file url. */
				__( 'Log Stcpay events, such as Webhook requests, inside %1$s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished. <a href="%2$s">View logs here</a>', 'woocommerce-gateway-stcpay' ),
				'<code>' . WC_Log_Handler_File::get_log_file_path( 'stcpay' ) . '</code>',
				admin_url( 'admin.php?page=wc-status&tab=logs' )
			),
			'default'     => 'no',
		),
		'api_details' => array(
			'title' => __( 'API Settings', 'woocommerce-gateway-stcpay' ),
			'type'  => 'title',
		),
		'environment' => array(
			'title'   => __( 'Environment', 'woocommerce-gateway-stcpay' ),
			'type'    => 'select',
			'default' => 'staging',
			'options' => $environments
		)
	),
	$environment_fileds
 );
