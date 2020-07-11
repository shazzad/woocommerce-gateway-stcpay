<?php
/**
 * Stcpay hooks
 *
 * Handle processes that happens outside of payment gateway.
 *
 * @class       WC_Stcpay_Hooks
 */
class WC_Stcpay_Hooks {

	public static function init() {
		add_action( 'wp_ajax_stcpay_request_otp', array( __CLASS__, 'request_otp_ajax' ) );
		add_action( 'wp_ajax_stcpay_confirm_otp', array( __CLASS__, 'confirm_otp_ajax' ) );

		#add_action( 'woocommerce_checkout_after_order_review', array( __CLASS__, 'render_otp_form' ) );
		#add_action( 'woocommerce_pay_order_after_submit', array( __CLASS__, 'render_otp_form' ) );
		#add_action( 'woocommerce_before_settings_checkout', array( __CLASS__, 'admin_chekout_page' ) );
	}

	public static function admin_chekout_page() {
		$client = new WC_Stcpay_Client();
		$payment = $client->get_payment( 'wc_order_lOB5Qt7rGMgOQ' );
		WC_Stcpay_Utils::p($payment);
	}

	private function clear_stcpay_otp_session() {
		WC()->session->__unset( 'stcpay_otp_expires' );
		WC()->session->__unset( 'stcpay_otp_reference' );
		WC()->session->__unset( 'stcpay_payment_reference' );
	}

	public static function confirm_otp_ajax() {
		$data = $_POST;
		if ( empty( $_POST['order_key'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid order key' )
			) );
		} elseif ( empty( $_POST['otp_value'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid OTP' )
			) );
		}

		$order_id = wc_get_order_id_by_order_key( wp_unslash( $_POST['order_key'] ) );
		if ( ! $order_id ) {
			wp_send_json_error( array(
				'message' => __( 'No order found with given key.' )
			) );
		}

		$order = wc_get_order( $order_id );
		$otp_value = wp_unslash( $_POST['otp_value'] );


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

			wp_send_json_error( array(
				'message' => $error
			) );
		}

		wc_stcpay()->log(
			sprintf(
				/* translators: %d: Order id, numeric. */
				__( 'Stcpay confirming payment for order: %d, using OTP %s', 'woocommerce-gateway-stcpay' ),
				$order->get_id(),
				$otp_value
			)
		);

		$client = new WC_Stcpay_Client();

		$confirm_payment = $client->confirm_payment(
			$otp_value,
			WC()->session->get( 'stcpay_otp_reference' ),
			WC()->session->get( 'stcpay_payment_reference' )
		);

		if ( is_wp_error( $confirm_payment ) ) {
			wc_stcpay()->log( sprintf( __( 'Stcpay API Error: %s' ), $confirm_payment->get_error_message() ) );

			if ( $confirm_payment->get_error_data() ) {
				wc_stcpay()->log( print_r( $confirm_payment->get_error_data(), true ) );
			}

			if ( ! in_array( $confirm_payment->get_error_code(), array( 'api_error', 'customer_error', 'internal_error', 'stcpay_error' ) ) ) {
				wp_send_json_error( array(
					'message' => __( 'Could not process your request. Please try later, or use other payment gateway.', 'woocommerce-gateway-stcpay' )
				) );
			} else {
				wp_send_json_error( array(
					'code' => $confirm_payment->get_error_code(),
					'message' => $confirm_payment->get_error_message()
				) );
			}
		}

		// Remove cart items if contain.
		WC()->cart->empty_cart();

		$order->add_order_note( __( 'Stcpay OTP Verified', 'woocommerce-gateway-stcpay' ) );

		update_post_meta( $order->get_id(), 'stcpay_otp_verified', time() );

		wp_send_json_success( array(
			'message' => 'OTP Confirmed'
		));
	}

	public static function request_otp_ajax() {
		$data = $_POST;
		if ( empty( $_POST['order_key'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid order key', 'woocommerce-gateway-stcpay' )
			) );
		} elseif ( empty( $_POST['mobile_no'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid mobile number', 'woocommerce-gateway-stcpay' )
			) );
		}

		$order_id = wc_get_order_id_by_order_key( wp_unslash( $_POST['order_key'] ) );
		if ( ! $order_id ) {
			wp_send_json_error( array(
				'message' => __( 'No order found with given key.', 'woocommerce-gateway-stcpay' )
			) );
		}

		$order = wc_get_order( $order_id );
		$mobile_no = wp_unslash( $_POST['mobile_no'] );

		wc_stcpay()->log(
			sprintf(
				/* translators: %d: Order id, numeric. */
				__( 'Stcpay requesting payment for order # %d', 'woocommerce-gateway-stcpay' ),
				$order->get_id()
			)
		);

		// store mobile number in user meta & session.
		WC()->session->set( 'stcpay_mobile_no', $mobile_no );
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), 'stcpay_mobile_no', $mobile_no );
		}

		// Clear previous verification meta.
		delete_post_meta( $order->get_id(), 'stcpay_otp_verified' );

		$client = new WC_Stcpay_Client();
		$request_payment = $client->request_payment( $order, $mobile_no );

		if ( is_wp_error( $request_payment ) ) {
			wc_stcpay()->log( sprintf( __( 'Stcpay API Error: %s' ), $request_payment->get_error_message() ) );

			if ( $request_payment->get_error_data() ) {
				wc_stcpay()->log( print_r( $request_payment->get_error_data(), true ) );
			}

			if ( ! in_array( $request_payment->get_error_code(), array( 'api_error', 'customer_error', 'internal_error', 'stcpay_error' ) ) ) {
				wp_send_json_error( array(
					'message' => __( 'Could not process your request. Please try later, or use other payment gateway.', 'woocommerce-gateway-stcpay' )
				) );
			} else {
				wp_send_json_error( array(
					'code' => $request_payment->get_error_code(),
					'message' => $request_payment->get_error_message()
				) );
			}
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: Payment reference, string. */
				__( 'Stcpay OTP Requested <br/>Payment Reference: %s <br/>OTP Reference: %s', 'woocommerce-gateway-stcpay' ),
				wc_clean( $request_payment['STCPayPmtReference'] ),
				wc_clean( $request_payment['OtpReference'] )
			)
		);

		WC()->session->set( 'stcpay_otp_expires', time() + $request_payment['ExpiryDuration'] );
		WC()->session->set( 'stcpay_otp_reference', $request_payment['OtpReference'] );
		WC()->session->set( 'stcpay_payment_reference', $request_payment['STCPayPmtReference'] );

		wp_send_json_success( array(
			'message'    => '<div class ="woocommerce-info">' . __( 'Enter OTP to confirm order', 'woocommerce-gateway-stcpay' ) . '</div>',
			'otpExpires' => time() + $request_payment['ExpiryDuration']
		));
	}

}
