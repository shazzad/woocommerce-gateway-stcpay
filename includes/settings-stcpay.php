<?php
/**
 * Settings for Stcpay Gateway.
 *
**/

defined( 'ABSPATH' ) || exit;

$environments = array();
$environment_fileds = array();

foreach ( WC_Stcpay::$environments as $environment_id => $environment ) {
	$environments[ $environment_id ] = $environment['name'];

	$environment_fileds[$environment_id . '_ssl_cert_file'] = array(
		'title'       => sprintf( __( '%s SSL Cert File:', 'stcpay' ), $environment['name'] ),
		'type'        => 'text',
		'description' => __( 'SSL Certificate file path.', 'stcpay' ),
		'desc_tip'    => true
	);
	$environment_fileds[$environment_id . '_ssl_key_file'] = array(
		'title'       => sprintf( __( '%s SSL Key File:', 'stcpay' ), $environment['name'] ),
		'type'        => 'text',
		'description' => __( 'SSL Private key file path.', 'stcpay' ),
		'desc_tip'    => true
	);
	$environment_fileds[$environment_id . '_ssl_password'] = array(
		'title'       => sprintf( __( '%s SSL Key Password:', 'stcpay' ), $environment['name'] ),
		'type'        => 'text',
		'description' => __( 'SSL Private key password.', 'stcpay' ),
		'desc_tip'    => true
	);
}

return array_merge(
	array(
		'enabled' => array(
			'title'       => __( 'Enable:', 'stcpay' ),
			'type'        => 'checkbox',
			'label'       => ' ',
			'description' => __( 'If you do not already have Stcpay merchant account, <a href="https://stcpay.com.sa/" target="_blank">please register one</a>.', 'stcpay' ),
			'default'     => 'no',
			'desc_tip'    => true
		),
		'title' => array(
			'title'       => __( 'Title:', 'stcpay' ),
			'type'        => 'text',
			'description' => __( 'Title of Stcpay Payment Gateway that users see on Checkout page.', 'stcpay' ),
			'default'     => __( 'Stcpay', 'stcpay' ),
			'desc_tip'    => true
		),
		'description' => array(
			'title'       => __( 'Description:', 'stcpay' ),
			'type'        => 'textarea',
			'description' => __( 'Description of Stcpay Payment Gateway that users sees on Checkout page.', 'stcpay' ),
			'default'     => __( 'Pay securely by Stcpay wallet.', 'stcpay' ),
			'desc_tip'    => true
		),
		'advanced_settings' => array(
			'title' => __( 'Advanced options', 'stcpay' ),
			'type'  => 'title'
		),
		'merchant_id' => array(
			'title'       => __( 'Mertchant ID:', 'stcpay' ),
			'type'        => 'text',
			'description' => __( 'Stcpay Merchant id.', 'stcpay' ),
			'desc_tip'    => true
		),
		'branch_id' => array(
			'title'       => __( 'Branch ID:', 'stcpay' ),
			'type'        => 'text',
			'description' => __( 'Branch id used for direct payment authorization.', 'stcpay' ),
			'default'     => 'Main Branch',
			'desc_tip'    => true
		),
		'teller_id' => array(
 			'title'       => __( 'Teller ID:', 'stcpay' ),
 			'type'        => 'text',
 			'description' => __( 'Teller id used for direct payment authorization.', 'stcpay' ),
			'default'     => 'WooCommerce',
 			'desc_tip'    => true
 		),
		'debug' => array(
			'title'       => __( 'Debug log', 'stcpay' ),
			'type'        => 'checkbox',
			'label'       => 'Enable logging',
			'description' => sprintf( __( 'Log Stcpay events, such as Webhook requests, inside %1$s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished. <a href="%2$s">View logs here</a>', 'stcpay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'stcpay' ) . '</code>', admin_url( 'admin.php?page=wc-status&tab=logs' ) ),
			'default'     => 'no',
		),
		'api_details' => array(
			'title' => __( 'API Settings', 'stcpay' ),
			'type'  => 'title',
		),
		'environment' => array(
			'title'   => __( 'Environment', 'stcpay' ),
			'type'    => 'select',
			'default' => 'staging',
			'options' => $environments
		)
	),
	$environment_fileds
 );
