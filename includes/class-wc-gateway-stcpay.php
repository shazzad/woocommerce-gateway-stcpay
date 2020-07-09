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
			$this->description .= '<p style="color:red; font-size: 18px; margin: 20px 0 10px;">';
			$this->description .= __( 'TEST MODE', 'woocommerce-gateway-stcpay' );
			$this->description .= '</p>';
			$this->description .= '<p style="padding: 10px; border: 1px solid red; font-size: 12px; line-height: 18px;">';
			$this->description .= __( 'Use 966539342897 as mobile number.', 'woocommerce-gateway-stcpay' );
			$this->description .= '<br/>' . __( 'No OTP will be sent through SMS, use any value for OTP.', 'woocommerce-gateway-stcpay' );
			$this->description .= '<br/>' . __( 'Use 4444 as OTP to test wrong otp output.', 'woocommerce-gateway-stcpay' );
			$this->description .= '</p>';
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
		$this->order_button_text  = __( 'Pay with Stcpay', 'woocommerce-gateway-stcpay' );
		$this->method_title       = __( 'Stcpay', 'woocommerce-gateway-stcpay');
		$this->method_description = __( 'Stcpay payment gateway for WooCommerce.', 'woocommerce-gateway-stcpay' );
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
		if ( 'confirm_payment' === WC()->checkout->get_value( 'stcpay_action' ) ) {
			return $this->confirm_payment( $order_id );
		} elseif ( 'request_payment' === WC()->checkout->get_value( 'stcpay_action' ) ) {
			return $this->request_payment( $order_id );
		}
	}

	private function clear_stcpay_otp_session() {
		WC()->session->__unset( 'stcpay_otp_expires' );
		WC()->session->__unset( 'stcpay_otp_reference' );
		WC()->session->__unset( 'stcpay_payment_reference' );
	}

	public function confirm_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = new WC_Stcpay_Client();

		wc_stcpay()->log( 'Stcpay confirming payment with OTP - '. WC()->checkout->get_value( 'stcpay_otp_value' ) );

		$error = '';
		if ( ! WC()->session->get( 'stcpay_otp_expires' ) || WC()->session->get( 'stcpay_otp_expires' ) < time() ) {
			$error = __( 'Internal Error: OTP expired.' );
		} elseif ( ! WC()->session->get( 'stcpay_otp_reference' ) ) {
			$error = __( 'Internal Error: Missing otp reference.' );
		} elseif ( ! WC()->session->get( 'stcpay_payment_reference' ) ) {
			$error = __( 'Internal Error: Missing payment reference.' );
		}

		if ( ! empty( $error ) ) {
			$this->clear_stcpay_otp_session();

			return array(
				'result'        => 'success',
				'messages'      => '<div class="woocommerce-error">' . __( 'OTP expired, Request again.', 'woocommerce-gateway-stcpay' ) . '</div>',
				'stcpay_action' => 'request_payment',
				'redirect'      => $this->get_return_url( $order ),
			);
		}

		$confirm_payment = $client->confirm_payment(
			WC()->checkout->get_value( 'stcpay_otp_value' ),
			WC()->session->get( 'stcpay_otp_reference' ),
			WC()->session->get( 'stcpay_payment_reference' )
		);

		if ( is_wp_error( $confirm_payment ) ) {
			wc_stcpay()->log(
				sprintf(
					/* translators: %s: Error message, may contain html */
					__( 'Stcpay API Error: %s', 'woocommerce-gateway-stcpay' ),
					$confirm_payment->get_error_message()
				)
			);

			$error = $confirm_payment->get_error_message();

			if ( ! in_array( $confirm_payment->get_error_code(), array( 'api_error', 'customer_error', 'internal_error', 'stcpay_error' ) ) ) {
				$error = __( 'Could not process your request. Please try later, or use other payment gateway.', 'woocommerce-gateway-stcpay' );
			}

			return array(
				'result'        => 'success',
				'messages'      => '<div class="woocommerce-error">' . $error . '</div>',
				'stcpay_action' => 'confirm_payment',
				'redirect'      => $this->get_return_url( $order ),
			);
		}

		// Remove cart.
		WC()->cart->empty_cart();

		$order->add_order_note(
			sprintf(
				/* translators: %1$s: Payment status - PAID, PENDING, CANCELLED, EXPIRED. %2$s: Amount */
				__( 'Stcpay Payment Status: %1$s. Amount Collected: %2$s', 'woocommerce-gateway-stcpay' ),
				wc_clean( $confirm_payment['PaymentStatusDesc'] ),
				wc_clean( $confirm_payment['Amount'] )
			)
		);

		$order->add_meta_data( 'Stcpay PaymentDate', wc_clean( $confirm_payment['PaymentDate'] ) );
		$order->add_meta_data( 'Stcpay PaymentStatus', wc_clean( $confirm_payment['PaymentStatus'] ) );
		$order->add_meta_data( 'Stcpay PaymentStatusDesc', wc_clean( $confirm_payment['PaymentStatusDesc'] ) );

		$order->payment_complete( $confirm_payment['STCPayRefNum'] );

		return array(
			'result'        => 'success',
			'messages'      => __( 'OTP Confirmed.', 'woocommerce-gateway-stcpay' ),
			'stcpay_action' => 'payment_confirmed',
			'confirm'       => $confirm_payment,
			'redirect'      => $this->get_return_url( $order ),
		);
	}

	public function request_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = new WC_Stcpay_Client();

		wc_stcpay()->log(
			sprintf(
				/* translators: %d: Order id, numeric. */
				__( 'Stcpay requesting payment for order # %d', 'woocommerce-gateway-stcpay' ),
				$order_id
			)
		);

		$mobile_no = WC()->checkout->get_value( 'stcpay_mobile_no' );

		// store mobile number in user meta & session.
		WC()->session->set( 'stcpay_mobile_no', $mobile_no );
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), 'stcpay_mobile_no', $mobile_no );
		}

		$request_payment = $client->request_payment( $order, WC()->checkout->get_value( 'stcpay_mobile_no' ) );

		if ( is_wp_error( $request_payment ) ) {
			wc_stcpay()->log( sprintf( __( 'Stcpay API Error: %s' ), $request_payment->get_error_message() ) );

			if ( $request_payment->get_error_data() ) {
				wc_stcpay()->log( print_r( $request_payment->get_error_data(), true ) );
			}

			if ( ! in_array( $request_payment->get_error_code(), array( 'api_error', 'customer_error', 'internal_error', 'stcpay_error' ) ) ) {
				wc_add_notice( __( 'Could not process your request. Please try later, or use other payment gateway.', 'woocommerce-gateway-stcpay' ), 'error' );
			} else {
				wc_add_notice( $request_payment->get_error_message(), 'error' );
			}

			return false;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: Payment reference, string. */
				__( 'Stcpay OTP Requested, Payment Reference: %s', 'woocommerce-gateway-stcpay' ),
				wc_clean( $request_payment['STCPayPmtReference'] )
			)
		);

		WC()->session->set( 'stcpay_otp_expires', time() + $request_payment['ExpiryDuration'] );
		WC()->session->set( 'stcpay_otp_reference', $request_payment['OtpReference'] );
		WC()->session->set( 'stcpay_payment_reference', $request_payment['STCPayPmtReference'] );

		return array(
			'result'                   => 'success',
			'messages'                 => '<div class="woocommerce-info">' . __( 'Enter OTP to confirm order', 'woocommerce-gateway-stcpay' ) . '</div>',
			'stcpay_action' 		   => 'confirm_payment',
			'redirect'                 => $this->get_return_url( $order ),
		);
	}

	public function validate_fields() {
		if ( ! WC()->checkout->get_value( 'stcpay_mobile_no' ) ) {
			wc_add_notice( __( 'Please enter your stcpay wallet mobile number', 'woocommerce-gateway-stcpay' ), 'error' );
		}
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}

		$mobile_no = '';
		if ( is_user_logged_in() ) {
			$mobile_no = get_user_meta( get_current_user_id(), 'stcpay_mobile_no', true );
		}
		if ( ! $mobile_no ) {
			$mobile_no = WC()->session->get( 'stcpay_mobile_no' );
		}

		?>
		<fieldset id="wc-stcpay-form" class="wc-payment-form">
			<p class="form-row field-stcpay-mobile-no">
				<label for="stcpay-mobile-no"><?php _e( 'Stcpay Wallet Mobile Number', 'woocommerce-gateway-stcpay' ); ?>&nbsp;<span class="required">*</span></label>
				<input id="stcpay-mobile-no" value="<?php echo esc_attr( $mobile_no ); ?>" class="input-text" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="stcpay_mobile_no" required />
			</p>
			<input type="hidden" id="stcpay-action" name="stcpay_action" value="request_payment" />
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

		if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
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
