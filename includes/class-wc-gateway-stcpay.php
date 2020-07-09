<?php
/**
 * WooCommerce Stcpay Payment Gateway.
 *
 * @class       WC_Gateway_Stcpay
 * @extends     WC_Payment_Gateway
**/
class WC_Gateway_Stcpay extends WC_Payment_Gateway {

	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the form fields
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->environment   = $this->get_option( 'environment' );

		/* display a message if staging environment is used */
		if ( 'staging' == $this->environment ) {
			$this->description .= '<br/><br/><p style="color:red;">' . __( 'TEST MODE. No OTP will be sent through SMS, use any value for OTP.', 'stcpay' ) . '</p>';
			$this->description  = trim( $this->description );
		}

		add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		if ( ! $this->is_available() ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'stcpay';
		$this->has_fields         = true;
		$this->order_button_text  = __( 'Pay with Stcpay', 'stcpay' );
		$this->method_title       = __( 'Stcpay', 'stcpay');
		$this->method_description = __( 'Stcpay payment gateway for WooCommerce.', 'stcpay' );
		$this->supports           = array(
			'products'
		);
		$this->icon               = WC_STCPAY_URL . '/assets/images/stcpay-logo.png';
	}

	/**
	 * Check if the payment gateway is available to be used.
	 *
	 */
	public function is_available() {
		$client = new WC_Stcpay_Client();
		if ( ! $client->is_available() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Return whether or not this gateway still requires setup to function.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		$client = new WC_Stcpay_Client();
		return ! $client->is_available();
	}

	/**
	 * Process payment
	 *
	 * @param  $order_id Order id.
	 * @return bool|array
	 */
	public function process_payment( $order_id ) {
		if ( WC()->checkout->get_value( 'stcpay_otp_value' ) ) {
			return $this->confirm_payment( $order_id );
		} else {
			return $this->request_payment( $order_id );
		}
	}

	public function confirm_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = new WC_Stcpay_Client();

		wc_stcpay()->log( 'Stcpay confirming payment with OTP - '. WC()->checkout->get_value( 'stcpay_otp_value' ) );

		if ( ! WC()->checkout->get_value( 'stcpay_otp_reference' ) ) {
			wc_add_notice( __( 'Internal Error: Missing otp reference.' ), 'error' );
			return false;
		} elseif ( ! WC()->checkout->get_value( 'stcpay_payment_reference' ) ) {
			wc_add_notice( __( 'Internal Error: Missing payment reference.' ), 'error' );
			return false;
		}

		$confirm_payment = $client->confirm_payment(
			WC()->checkout->get_value( 'stcpay_otp_value' ),
			WC()->checkout->get_value( 'stcpay_otp_reference' ),
			WC()->checkout->get_value( 'stcpay_payment_reference' )
		);

		if ( is_wp_error( $confirm_payment ) ) {
			wc_stcpay()->log( sprintf( __( 'Stcpay API Error: %s' ), $confirm_payment->get_error_message() ) );

			wc_add_notice( $confirm_payment->get_error_message(), 'error' );
			return false;
		}

		// Remove cart.
		WC()->cart->empty_cart();

		$order->add_order_note( sprintf( __( 'Stcpay Payment Status: %s' ), wc_clean( $confirm_payment['PaymentStatusDesc'] ) ) );

		$order->add_meta_data( 'Stcpay PaymentDate', wc_clean( $confirm_payment['PaymentDate'] ) );
		$order->add_meta_data( 'Stcpay PaymentStatus', wc_clean( $confirm_payment['PaymentStatus'] ) );
		$order->add_meta_data( 'Stcpay PaymentStatusDesc', wc_clean( $confirm_payment['PaymentStatusDesc'] ) );

		$order->payment_complete( $confirm_payment['STCPayRefNum'] );

		#update_post_meta( $order->get_id(), 'STCPay RefNum', $confirm_payment['STCPayRefNum'] );
		#update_post_meta( $order->get_id(), 'STCPay PaymentDate', $confirm_payment['PaymentDate'] );
		#update_post_meta( $order->get_id(), 'STCPay PaymentStatus', $confirm_payment['PaymentStatus'] );
		#update_post_meta( $order->get_id(), 'STCPay PaymentStatusDesc', $confirm_payment['PaymentStatusDesc'] );

		return array(
			'result'        => 'success',
			'messages'      => __( 'OTP Confirmed.' ),
			'stcpay_otp' 	=> 'success',
			'confirm'       => $confirm_payment,
			'redirect'      => $this->get_return_url( $order ),
		);
	}

	public function request_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = new WC_Stcpay_Client();

		wc_stcpay()->log( sprintf( __( 'Stcpay requesting payment for order # %d' ), $order_id  ) );

		$request_payment = $client->request_payment( $order, WC()->checkout->get_value( 'stcpay_mobile_no' ) );

		if ( is_wp_error( $request_payment ) ) {
			wc_stcpay()->log( sprintf( __( 'Stcpay API Error: %s' ), $request_payment->get_error_message() ) );

			if ( in_array( $request_payment->get_error_code(), array( 'empty_api_response' ) ) ) {
				wc_add_notice( __( 'Internal Error: Please try later, or use other payment gateway.' ), 'error' );
			} else if ( in_array( $request_payment->get_error_code(), array( 'api_error' ) ) ) {
				wc_add_notice( $request_payment->get_error_message(), 'error' );
			} else if ( in_array( $request_payment->get_error_code(), array( 'unknown_api_response' ) ) ) {
				wc_add_notice( __( 'Stcpay Error: Unknown Response.', 'stcpay' ), 'error' );
			} else {
				wc_add_notice( __( 'Could not process your request. Please try later, or use other payment gateway.' ), 'error' );
			}

			return false;
		}

		$order->add_order_note(
			sprintf(
				__( 'Stcpay OtpReference: %s, PaymentReference: %s' ),
				wc_clean( $request_payment['OtpReference'] ),
				wc_clean( $request_payment['STCPayPmtReference'] )
			)
		);
		$order->add_meta_data( 'Stcpay OtpReference', wc_clean( $request_payment['OtpReference'] ) );
		$order->add_meta_data( 'Stcpay PaymentReference', wc_clean( $request_payment['STCPayPmtReference'] ) );
		$order->save();

		return array(
			'result'                   => 'success',
			'messages'                 => '<div class="woocommerce-info">' . __( 'Enter OTP to confirm order' ) . '</div>',
			'stcpay_otp' 			   => 'request',
			'stcpay_otp_reference'     => $request_payment['OtpReference'],
			'stcpay_payment_reference' => $request_payment['STCPayPmtReference'],
			'redirect'                 => $this->get_return_url( $order ),
		);
	}

	public function validate_fields() {
		if ( ! WC()->checkout->get_value( 'stcpay_mobile_no' ) ) {
			wc_add_notice( __( 'Please enter your stcpay wallet mobile number' ), 'error' );
		}
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}

		?>
		<fieldset id="WC()-stcpay-form" class="WC()-payment-form">
			<p class="form-row field-stcpay-mobile-no">
				<label for="stcpay-mobile-no"><?php _e( 'Stcpay Wallet Mobile Number', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input id="stcpay-mobile-no" class="input-text" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="stcpay_mobile_no" required />
			</p>
			<input type="hidden" id="stcpay_otp_reference" name="stcpay_otp_reference" />
			<input type="hidden" id="stcpay_payment_reference" name="stcpay_payment_reference" />
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Intialize form fields
	 *
	 */
	public function init_form_fields() {
		$this->form_fields = include WC_STCPAY_DIR . 'includes/settings-stcpay.php';
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs styles/scripts used for stcpay payment
	 *
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// If Stripe is not available, bail.
		if ( ! $this->is_available() ) {
			return;
		}

		wp_register_script(
			'stcpay-checkout',
			WC_STCPAY_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			WC_STCPAY_VERSION,
			true
		);
		wp_register_style(
			'stcpay-frontend',
			WC_STCPAY_URL . '/assets/css/frontend.css',
			array(),
			WC_STCPAY_VERSION
		);

		wp_enqueue_script( 'stcpay-checkout' );
		wp_enqueue_style( 'stcpay-frontend' );
	}

	/**
	 * Load admin scripts.
	 *
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( 'woocommerce_page_WC()-settings' !== $screen_id ) {
			return;
		}

		wp_enqueue_script(
			'woocommerce_stcpay_admin',
			WC_STCPAY_URL . '/assets/js/settings.js',
			array( 'jquery' ),
			WC_STCPAY_VERSION,
			true
		);
	}
}
