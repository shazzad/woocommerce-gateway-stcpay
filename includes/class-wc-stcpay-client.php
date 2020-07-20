<?php
/**
 * WooCommerce Stcpay Api Client.
 *
 * Handle outbound requests to Stcpay API
 *
 * @class       WC_Stcpay_Client
 */
class WC_Stcpay_Client {

	/**
	 * Mock data, ignore sending request to stcpay.
	 * @var bool
	 */
	const MOCK_DATA = false;
	const MOCK_PENDING = false;
	const MOCK_CANCELLED = false;

	/**
	 * Available api environments
	 * @var [type]
	 */
	public static $environments = array(
		'staging'    => array(
			'name'     => 'Staging',
			'endpoint' => 'https://b2btest.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
		'production' => array(
			'name'     => 'Production',
			'endpoint' => 'https://b2b.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
	);

	/**
	 * Api errors
	 * @var array
	 */
	protected $errors;

	/**
	 * Current api environment in used.
	 * @var string
	 */
	protected $environment;

	/**
	 * Current api endpoint.
	 * @var string
	 */
	protected $endpoint;

	/**
	 * SSL certificate file used for api request.
	 * @var string
	 */
	protected $ssl_cert_file;

	/**
	 * SSL certificate key file used for api request.
	 * @var string
	 */
	protected $ssl_key_file;

	/**
	 * SSL certificate key password, if any.
	 * @var string
	 */
	protected $ssl_key_password = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->environment      = $this->get_option( 'environment', 'staging' );
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->ssl_cert_file    = $this->get_option( $this->environment . '_ssl_cert_file' );
		$this->ssl_key_file     = $this->get_option( $this->environment . '_ssl_key_file' );
		$this->ssl_key_password = $this->get_option( $this->environment . '_ssl_key_password' );
		$this->endpoint         = self::$environments[ $this->environment ]['endpoint'];

		$this->init_error_codes();
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
	 * Load error code in class variable.
	 */
	private function init_error_codes() {
		// stcpay = 2000,
		// internal = 2001, 2003, 2004, 2006, 2011, 2012, 2012, 2013, 2014, 2015, 2016, 2019, 2021, 2026, 2033, 2034, 2035, 2036
		// customer = 2008, 2017, 2018, 2020, 2023, 2024, 2025, 2028, 2029, 2030, 2031, 2037
		$this->errors = array(
			2000 => __( 'An error occurred.', 'woocommerce-gateway-stcpay' ),
			2001 => __( 'Invalid Request Format.', 'woocommerce-gateway-stcpay' ),
			2002 => __( 'At least one of the fields must be provided', 'woocommerce-gateway-stcpay' ),
			2003 => __( 'Generic Business Validation.', 'woocommerce-gateway-stcpay' ),
			2004 => __( 'Merchant Not Found.', 'woocommerce-gateway-stcpay' ),
			2006 => __( 'No Such Payment.', 'woocommerce-gateway-stcpay' ),
			2008 => __( 'No Account Found With This Mobile No.', 'woocommerce-gateway-stcpay' ),
			2009 => __( 'Already Paid.', 'woocommerce-gateway-stcpay' ),
			2010 => __( 'RefNum Or Payment Date Required', 'woocommerce-gateway-stcpay' ),
			2011 => __( 'Required BranchID.', 'woocommerce-gateway-stcpay' ),
			2012 => __( 'Required TellerID.', 'woocommerce-gateway-stcpay' ),
			2013 => __( 'Required DeviceID.', 'woocommerce-gateway-stcpay' ),
			2014 => __( 'Required RefNum.', 'woocommerce-gateway-stcpay' ),
			2015 => __( 'Required BillNumber.', 'woocommerce-gateway-stcpay' ),
			2016 => __( 'Required Amount.', 'woocommerce-gateway-stcpay' ),
			2017 => __( 'Required Mobile No.', 'woocommerce-gateway-stcpay' ),
			2018 => __( 'Invalid Mobile No.', 'woocommerce-gateway-stcpay' ),
			2019 => __( 'Required OtpReference.', 'woocommerce-gateway-stcpay' ),
			2020 => __( 'Required OtpValue.', 'woocommerce-gateway-stcpay' ),
			2021 => __( 'Required STCPayPmtReference.', 'woocommerce-gateway-stcpay' ),
			2022 => __( 'Invalid Token', 'woocommerce-gateway-stcpay' ),
			2023 => __( 'Customer doesn’t have Sufficient Fund.', 'woocommerce-gateway-stcpay' ),
			2024 => __( 'Shared Account Limit Exceeded.', 'woocommerce-gateway-stcpay' ),
			2025 => __( 'Shared Account Transaction not allowed.', 'woocommerce-gateway-stcpay' ),
			2026 => __( 'Payment Expired.', 'woocommerce-gateway-stcpay' ),
			2027 => __( 'Payment Cancelled.', 'woocommerce-gateway-stcpay' ),
			2028 => __( 'Please complete your Customer Information.', 'woocommerce-gateway-stcpay' ),
			2029 => __( 'Your account status is not valid to perform this transaction.', 'woocommerce-gateway-stcpay' ),
			2030 => __( 'Account is not allowed for transfers.', 'woocommerce-gateway-stcpay' ),
			2031 => __( 'Invalid OTP either expired or not found, please request a new OTP.', 'woocommerce-gateway-stcpay' ),
			2033 => __( 'MerchantId Required.', 'woocommerce-gateway-stcpay' ),
			2034 => __( 'Merchant is not active.', 'woocommerce-gateway-stcpay' ),
			2035 => __( 'Invalid Merchant ID.', 'woocommerce-gateway-stcpay' ),
			2036 => __( 'Invalid Amount.', 'woocommerce-gateway-stcpay' ),
			2037 => __( 'Payment information mismatch with the OtpReference.', 'woocommerce-gateway-stcpay' )
		);
	}

	private function get_stcpay_error_codes() {
		return array( 2000 );
	}

	private function get_customer_error_codes() {
		return array( 2008, 2017, 2018, 2020, 2023, 2024, 2025, 2028, 2029, 2030, 2031, 2037 );
	}

	private function get_internal_error_codes() {
		return array( 2001, 2003, 2004, 2011, 2012, 2012, 2013, 2014, 2015, 2016, 2019, 2021, 2026, 2033, 2034, 2035, 2036 );
	}

	/**
	 * Get the Stcpay request URL for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string $mobile_no Customer stcpay wallet mobile number.
	 * @return string
	 */
	public function request_payment( $order, $mobile_no ) {
		if ( self::MOCK_DATA ) {
			return array(
				'OtpReference'       => 'MruEmuIQSDYDuFMEeWNN',
				'STCPayPmtReference' => '3495370164',
				'ExpiryDuration'     => 5,
			);
		}

		$merchant_note = $this->get_option( 'merchant_note' );

		$data = array(
			'BranchID'     => $this->get_option( 'branch_id' ),
			'TellerID'     => $this->get_option( 'teller_id' ),
			'DeviceID'     => $this->get_option( 'device_id' ),
			'MobileNo'     => $mobile_no,
			'RefNum'       => $order->get_order_key(),
			'BillNumber'   => $order->get_id(),
			'Amount'       => $order->get_total(),
			'MerchantNote' => $merchant_note,
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
		if ( self::MOCK_DATA && '4444' === $otp_value ) {
			return new WP_Error( 'customer_error', __( 'Invalid OTP either expired or not found, please request a new OTP.', 'woocommerce-gateway-stcpay' ) );
		}

		if ( self::MOCK_DATA ) {
			return array(
				"MerchantID"        => "61240300247",
		        "BranchID"          => "1",
		        "TellerID"          => "22",
		        "DeviceID"          => "500",
		        "RefNum"            => "wc_order_bcdahello",
		        "STCPayRefNum"      => $payment_reference,
		        "Amount"            => 20.800,
		        "PaymentDate"       => "2020-07-06T17:34:15.24",
		        "PaymentStatus"     => 2,
		        "PaymentStatusDesc" => "Paid"
			);
		}

		$data = array(
			'OtpValue'           => $otp_value,
			'OtpReference'       => $otp_reference,
			'STCPayPmtReference' => $payment_reference,
		);

		// Simulate pending transaction.
		if ( self::MOCK_PENDING ) {
			$response = $this->request( 'DirectPaymentConfirm', $data );
			if ( isset( $response['PaymentStatus'] ) ) {
				$response['PaymentStatus'] = 1;
				$response['PaymentStatusDesc'] = 'Pending';
			}

			return $response;
		}

		return $this->request( 'DirectPaymentConfirm', $data );
	}

	/**
	 * Get the Stcpay request URL for an order.
	 *
	 * @param  string $payment_reference Payment Reference, wc order key.
	 * @return string
	 */
	public function get_payment( $payment_reference ) {
		if ( self::MOCK_DATA ) {
			return array(
				"MerchantID"        => "61240300247",
		        "BranchID"          => "1",
		        "TellerID"          => "22",
		        "DeviceID"          => "500",
		        "RefNum"            => "wc_order_bcdahello",
		        "STCPayRefNum"      => $payment_reference,
		        "Amount"            => 20.800,
		        "PaymentDate"       => "2020-07-06T17:34:15.24",
		        "PaymentStatus"     => 2,
		        "PaymentStatusDesc" => "Paid"
			);
		}

		$data = array(
			'RefNum' => $payment_reference,
		);

		$response = $this->request( 'PaymentInquiry', $data );
		if ( ! is_wp_error( $response ) && isset( $response['TransactionList'] ) ) {
			$response = $response['TransactionList'][0];
		}

		// Simulate pending transaction.
		/*
		if ( self::MOCK_PENDING ) {
			if ( isset( $response['PaymentStatus'] ) ) {
				$response['PaymentStatus'] = 1;
				$response['PaymentStatusDesc'] = 'Pending';
			}

			return $response;
		}
		*/

		return $response;
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

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Body comes as empty when merchant id or ssl certificate mismatch.
		if ( empty( $body ) ) {
			return new WP_Error( 'config_error', __( 'Invalid merchant id or ssl certificates.', 'woocommerce-gateway-stcpay' ) );
		}

		// Decode json data.
		$data = json_decode( $body, true );

		// Code other than 200 is unacceptable.
		if ( 200 !== $code ) {
			/*
			if ( isset( $data['Code'] ) && in_array( $data['Code'], $this->get_stcpay_error_codes() ) ) {
				return new WP_Error( 'stcpay_error', __( 'Stcpay Error. Please try later or Use other payment method' ) );
			} else*/
			if ( isset( $data['Code'] ) && in_array( $data['Code'], $this->get_internal_error_codes() ) ) {
				return new WP_Error( 'internal_error', __( 'Internal Error. Please try later or Use other payment method', 'woocommerce-gateway-stcpay' ), $data );
			} elseif ( isset( $data['Code'] ) && in_array( $data['Code'], $this->get_customer_error_codes() ) ) {
				return new WP_Error( 'customer_error', $this->errors[ $data['Code'] ], $data );
			} elseif ( isset( $data['Code'] ) && isset( $data['Text'] ) ) {
				return new WP_Error( 'api_error', $data['Text'] . ' - ' . $data['Code'], $data );
			} else {
				return new WP_Error( 'api_error_unknown', __( 'API Error', 'woocommerce-gateway-stcpay' ) );
			}
		}

		if ( ! isset( $data["{$type}V4ResponseMessage"] ) ) {
			return new WP_Error( 'unknown_api_response', __( 'Unknown Response.', 'woocommerce-gateway-stcpay' ) );
		}

		return $data["{$type}V4ResponseMessage"];
	}

	public function curl_ssl_parameters( $handle ) {
		curl_setopt( $handle, CURLOPT_SSLKEY, $this->ssl_key_file );
		curl_setopt( $handle, CURLOPT_SSLCERT, $this->ssl_cert_file );
		curl_setopt( $handle, CURLOPT_SSLCERTPASSWD, $this->ssl_key_password );
	}
}
