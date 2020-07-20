<?php
/**
 * WooCommerce Stcpay Cron Handler.
 *
 * @class       WC_Gateway_Stcpay_Cron_Handler
 * @extends     WC_Gateway_Stcpay_Response
**/


class WC_Stcpay_Cron_Handler extends WC_Stcpay_Response {

	protected $handler_name = 'Cron';

	public function __construct() {
		add_action( 'stcpay_update_pending_payment_status', array( $this, 'update_pending_payment_status' ) );
	}

	public function update_pending_payment_status( $order_id ) {
		wc_stcpay()->log( 'Updating payment status' );

		$order  = wc_get_order( $order_id );
		if ( ! $order->get_id() || 'stcpay' !== $order->get_payment_method() ) {
			return false;
		}

		// if already processing through other call.
		if ( $this->is_order_processing( $order_id ) ) {
			return false;
		}

		// put a lock for one minute.
		$this->lock_order_process( $order_id );

		$client = new WC_Stcpay_Client();
		$payment = $client->get_payment( $order->get_order_key() );
		wc_stcpay()->log( print_r( $payment, true ) );

		if ( is_wp_error( $payment ) ) {
			wc_stcpay()->log(
				sprintf(
					/* translators: %s: Error message. */
					__( 'Stcpay API Error: %s', 'woocommerce-gateway-stcpay' ),
					$payment->get_error_message()
				)
			);

			$this->unlock_order_process( $order_id );

			return;
		}

		if ( 'Pending' === $payment['PaymentStatusDesc'] ) {
			$this->stcpay_status_pending( $order, $payment );
		} elseif ( 'Paid' === $payment['PaymentStatusDesc'] ) {
			$this->stcpay_status_paid( $order, $payment );
		} elseif ( 'Cancelled' === $payment['PaymentStatusDesc'] ) {
			$this->stcpay_status_cancelled( $order, $payment );
		} elseif ( 'Expired' === $payment['PaymentStatusDesc'] ) {
			$this->stcpay_status_expired( $order, $payment );
		}

		$this->unlock_order_process( $order_id );
	}
}
