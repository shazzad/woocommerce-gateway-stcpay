<?php
/**
 * WooCommerce Stcpay Payment Gateway.
 *
 * @class       WC_Gateway_Stcpay
 * @extends     WC_Payment_Gateway
**/


class WC_Gateway_Stcpay extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'stcpay';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Proceed to Stcpay', 'stcpay' );
		$this->method_title       = __( 'Stcpay', 'stcpay');
		$this->method_description = __( 'Stcpay payment gateway for WooCommerce.', 'stcpay' );
		$this->supports           = array(
			'products'
		);
		$this->icon               = WC_STCPAY_URL . '/assets/images/stcpay-logo.png';

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->environment   = $this->get_option( 'environment' );

		/* display a message if staging environment is used */
		if ( 'staging' == $this->environment ) {
			/* translators: %s: Link to Stcpay documentation page */
			$this->description .= ' ' . sprintf( __( 'SANDBOX ENABLED. Visit <a href="%s">Stcpay</a> for more details.', 'stcpay' ), 'https://stcpay.com.sa/en' );
			$this->description  = trim( $this->description );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array( $this, 'process_admin_options' ) );

		if ( ! $this->is_available() ) {
			$this->enabled = 'no';
		}
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
		$order  = wc_get_order( $order_id );
		$client = new WC_Stcpay_Client();


		if ( WC()->checkout->get_value( 'stcpay_otp' ) ) {
			wc_stcpay()->log( 'Stcpay confirming payment (OTP) for order # '. $order_id );

			if ( ! WC()->checkout->get_value( 'stcpay_OtpReference' ) ) {
				wc_add_notice( __( 'Internal Error: Missing otp reference.' ), 'error' );
				return false;
			} elseif ( ! WC()->checkout->get_value( 'stcpay_PmtReference' ) ) {
				wc_add_notice( __( 'Internal Error: Missing payment reference.' ), 'error' );
				return false;
			}

			$confirm_payment = $client->confirm_payment(
				WC()->checkout->get_value( 'stcpay_otp' ),
				WC()->checkout->get_value( 'stcpay_OtpReference' ),
				WC()->checkout->get_value( 'stcpay_PmtReference' )
			);

			if ( is_wp_error( $confirm_payment ) ) {
				wc_stcpay()->log( 'Stcpay Api Error: '. $confirm_payment->get_error_message() );

				wc_add_notice( $confirm_payment->get_error_message(), 'error' );
				return false;
			}

			// Remove cart.
			WC()->cart->empty_cart();


			$order->add_order_note( sprintf( __( 'Stcpay Payment Status: %s', wc_clean( $confirm_payment['PaymentStatusDesc'] ) ) ) );

			$order->add_meta_data( 'STCPay RefNum', wc_clean( $confirm_payment['STCPayRefNum'] ) );
			$order->add_meta_data( 'STCPay PaymentDate', wc_clean( $confirm_payment['PaymentDate'] ) );
			$order->add_meta_data( 'STCPay PaymentStatus', wc_clean( $confirm_payment['PaymentStatus'] ) );
			$order->add_meta_data( 'STCPay PaymentStatusDesc', wc_clean( $confirm_payment['PaymentStatusDesc'] ) );

			$order->payment_complete( $confirm_payment['STCPayRefNum'] );

			#update_post_meta( $order->get_id(), 'STCPay RefNum', $confirm_payment['STCPayRefNum'] );
			#update_post_meta( $order->get_id(), 'STCPay PaymentDate', $confirm_payment['PaymentDate'] );
			#update_post_meta( $order->get_id(), 'STCPay PaymentStatus', $confirm_payment['PaymentStatus'] );
			#update_post_meta( $order->get_id(), 'STCPay PaymentStatusDesc', $confirm_payment['PaymentStatusDesc'] );

			return array(
				'result'        => 'success',
				'messages'      => 'OTP Confirmed.',
				'otp_confirmed' => true,
				'confirm'       => $confirm_payment,
				'redirect'      => $this->get_return_url( $order ),
			);
		}

		wc_stcpay()->log( 'Stcpay requesting payment for order # '. $order_id );

		$request_payment = $client->request_payment( $order, $order->get_billing_phone() );
		if ( is_wp_error( $request_payment ) ) {
			wc_stcpay()->log( 'Stcpay Api Error: '. $request_payment->get_error_message() );

			if ( in_array( $request_payment->get_error_code(), array( 'empty_api_response' ) ) ) {
				wc_add_notice( __( 'Internal Error: Please try later, or use other payment gateway.' ), 'error' );
			} else if ( in_array( $request_payment->get_error_code(), array( 'api_error' ) ) ) {
				wc_add_notice( $request_payment->get_error_message(), 'error' );
			} else if ( in_array( $request_payment->get_error_code(), array( 'unknown_api_response' ) ) ) {
				wc_add_notice( __( 'Stcpay Error: Unknown Response.', 'stcpay' ), 'error' );
			} else {
				wc_add_notice( __('Could not process your request. Please try later, or use other payment gateway.'), 'error' );
			}

			return false;
		}

		return array(
			'result'              => 'success',
			'messages'            => '<div class="woocommerce-info">Enter otp to confirm order</div>',
			'stcpay_OtpReference' => $request_payment['OtpReference'],
			'stcpay_PmtReference' => $request_payment['STCPayPmtReference'],
			'redirect'            => $this->get_return_url( $order ),
		);
	}

	public function validate_fields() {
		if ( ! WC()->checkout->get_value( 'stcpay_mobile' ) ) {
			wc_add_notice( __( 'Please enter your stcpay wallet mobile number' ), 'error' );
		}
	}

	public function payment_fields() {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class='wc-payment-form'>
			<?php
			echo '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-mobile">' . esc_html__( 'Stcpay Wallet Mobile Number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-mobile" class="input-text" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="' . $this->id . '_mobile" required />
			</p>';

			echo '<p class="form-row form-row-last">
				<label for="' . esc_attr( $this->id ) . '-otp">' . esc_html__( 'OTP', 'woocommerce' ) . '</label>
				<input id="' . esc_attr( $this->id ) . '-otp" class="input-text" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="' . $this->id . '_otp" />
			</p>';
			?>
			<input type="hidden" id="stcpay_OtpReference" name="stcpay_OtpReference" />
			<input type="hidden" id="stcpay_PmtReference" name="stcpay_PmtReference" />
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

		// If Stripe is not enabled bail.
		if ( ! $this->is_available() ) {
			return;
		}

		wp_register_script( 'stcpay-checkout', WC_STCPAY_URL . 'assets/js/checkout.js', array( 'jquery' ), WC_STCPAY_VERSION, true );
		wp_enqueue_script( 'stcpay-checkout' );

		#wp_register_style( 'stcpay_styles', WC_STCPAY_URL . '/assets/css/stcpay-styles.css', array(), WC_STCPAY_VERSION );
		#wp_enqueue_style( 'stcpay_styles' );
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

		wp_enqueue_script( 'woocommerce_stcpay_admin', WC_STCPAY_URL . '/assets/js/stcpay-admin-scripts.js', array(), WC_STCPAY_VERSION, true );
	}
}
