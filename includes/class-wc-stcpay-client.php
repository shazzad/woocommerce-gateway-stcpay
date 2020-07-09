<?php
/**
 * WooCommerce Stcpay Api Client.
 *
 * Handle outbound requests to Stcpay API
 *
 * @class       WC_Stcpay_Client
 */

class WC_Stcpay_Client {

	protected $environments = array(
		'staging'    => array(
			'name'     => 'Staging',
			'endpoint' => 'https://b2btest.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
		'production' => array(
			'name'     => 'Production',
			'endpoint' => 'https://b2b.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
	);

	protected $environment;
	protected $endpoint;
	protected $ssl_cert_file;
	protected $ssl_key_file;
	protected $ssl_key_password;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->environment      = $this->get_option( 'environment', 'staging' );
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->ssl_cert_file    = $this->get_option( $this->environment . '_ssl_cert_file' );
		$this->ssl_key_file     = $this->get_option( $this->environment . '_ssl_key_file' );
		$this->ssl_key_password = $this->get_option( $this->environment . '_ssl_key_password' );
		$this->endpoint         = $this->environments[ $this->environment ]['endpoint'];
	}

	/**
	 * Check if client is available to be used.
	 *
	 * @return boolean True if available.
	 */
	public function is_available() {
		return $this->ssl_cert_file && $this->ssl_key_file && $this->merchant_id;
	}

	/**
	 * Get option value.
	 *
	 * @return mixed Option value.
	 */
	public function get_option( $name, $default = '' ) {
		$options = get_option( 'woocommerce_stcpay_settings', array() );
		if ( array_key_exists( $name, $options ) ) {
			return $options[ $name ];
		}

		return $default;
	}

	/**
	 * Get the Stcpay request URL for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string $mobile_no Customer stcpay wallet mobile number.
	 * @return string
	 */
	public function request_payment( $order, $mobile_no ) {
		return array(
			'OtpReference' => 'MruEmuIQSDYDuFMEeWNN',
			'STCPayPmtReference' => '3495370164',
			'ExpiryDuration' => 300,
		);

		$merchange_note = __( 'Payment requests for your order.' );
		foreach ( $order->get_items() as $item ) {
			$merchange_note .= $item['name'] . " x" . $item['qty'] . '.';
		}

		$data = array(
			'BranchID'     => $this->get_option( 'branch_id' ),
			'TellerID'     => $this->get_option( 'teller_id' ),
			'DeviceID'     => 'Device',
			'MobileNo'     => $mobile_no,
			'RefNum'       => $order->get_order_key(),
			'BillNumber'   => $order->get_id(),
			'Amount'       => $order->get_total(),
			'MerchantNote' => $merchange_note,
		);

		return $this->request( 'DirectPaymentAuthorize', $data );
	}


	/**
	 * Get the Stcpay request URL for an order.
	 *
	 * @param  string $otp_value OTP Value supplied by Customer.
	 * @param  string $otp_reference OTP Reference supplied by Stcpay.
	 * @param  string $payment_reference Payment Reference supplied by Stcpay.
	 * @return string
	 */
	public function confirm_payment( $otp_value, $otp_reference, $payment_reference ) {
		return array(
			"MerchantID" => "61240300247",
	        "BranchID" => "1",
	        "TellerID" => "22",
	        "DeviceID" => "500",
	        "RefNum" => "wc_order_bcdahello",
	        "STCPayRefNum" => $payment_reference,
	        "Amount" => 20.800,
	        "PaymentDate" => "2020-07-06T17:34:15.24",
	        "PaymentStatus" => 2,
	        "PaymentStatusDesc" => "Paid"
		);

		$data = array(
			'OtpValue'           => $otp_value,
			'OtpReference'       => $otp_reference,
			'STCPayPmtReference' => $payment_reference,
		);

		return $this->request( 'DirectPaymentConfirm', $data );
	}

	/**
	 * Make request to stcpay
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	private function request( $type, $data ) {
		wc_stcpay()->log( 'Stcpay API Request: ' . $type );
		wc_stcpay()->log( print_r( $data, true ) );

		$url = $this->endpoint . '/' . $type;

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-ClientCode' => $this->merchant_id
			),
			'body' => json_encode( array(
				"{$type}V4RequestMessage" => $data
			) )
		);

		add_action( 'http_api_curl', array( $this, 'curl_ssl_parameters' ) );

		$response = wp_remote_post( $url, $args );

		remove_action( 'http_api_curl', array( $this, 'curl_ssl_parameters' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_api_response', __( 'Empty response received from stcpay.' ) );
		}

		$data = json_decode( $body, true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$errors = array();
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$errors[] = $key . ':' . serialize( $value );
				} else {
					$errors[] = $key . ':' . $value;
				}
			}

			return new WP_Error( 'api_error', sprintf( __( 'Error: %s' ), implode( '. ', $errors ) ) );
		}

		if ( ! isset( $data["{$type}V4ResponseMessage"] ) ) {
			return new WP_Error( 'unknown_api_response', __( 'Stcpay responded with an unknown format.' ) );
		}

		return $data["{$type}V4ResponseMessage"];
	}

	public function curl_ssl_parameters( $handle ) {
		curl_setopt( $handle, CURLOPT_SSLKEY, $this->ssl_key_file );
		curl_setopt( $handle, CURLOPT_SSLCERT, $this->ssl_cert_file );
		curl_setopt( $handle, CURLOPT_SSLCERTPASSWD, $this->ssl_key_password );
	}
}
