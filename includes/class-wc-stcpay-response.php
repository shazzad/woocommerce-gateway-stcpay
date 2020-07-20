<?php
/**
 * WooCommerce Stcpay Response.
 *
 * @class       WC_Gateway_Stcpay_Response
**/

class WC_Stcpay_Response {

	protected $handler_name = 'Checkout';

	protected $pending_payment_cron_delays = array(
		1 => 60,
		2 => 300,
		3 => 3600,
		4 => 43200,
		5 => 86400,
	);

	protected $pending_payment_cron_delaysX = array(
		1 => 60,
		2 => 30,
		3 => 36,
		4 => 43,
		5 => 40,
	);

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_completed( $order, $payment ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_stcpay()->log(
				sprintf(
					/* translators: %s: Error message, may contain html */
					__( 'Aborting, Order # %d is already complete.', 'woocommerce-gateway-stcpay' ),
					$order->get_id()
				)
			);
			return true;
		}

		if ( ! $this->validate_amount( $order, $payment['Amount'] ) ) {
			return new WP_Error(
				'stcpay_amount_error',
				__( 'Amount miss-matched', 'woocommerce-gateway-stcpay' )
			);
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: Gateway handler name. */
				__( 'Payment Completed via %s.', 'woocommerce-gateway-stcpay' ),
				$this->handler_name
			)
		);

		$order->payment_complete( $payment['STCPayRefNum'] );

		if ( ! is_admin() && ! wp_doing_cron() ) {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_pending( $order, $payment ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_stcpay()->log( 'Aborting, Order #' . $order->get_id() . ' is already complete.' );
			return;
		}

		$order_id = $order->get_id();

		// Maximum number of pending status reached.
		if ( count( $this->pending_payment_cron_delays ) === (int) get_post_meta( $order_id, 'stcpay_pending_checked', true ) ) {
			$this->stcpay_status_failed( $order, array( 'PaymentStatusDesc' => __( 'Failed (Maximum number of pending payment response received).' ) ) );
			return;
		}

		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: %1$s: Payment status. %2$s: Gateway handler name. */
				__( 'Payment %1$s via %2$s.', 'woocommerce-gateway-stcpay' ),
				$payment['PaymentStatusDesc'],
				$this->handler_name
			)
		);

		if ( ! is_admin() && ! wp_doing_cron() ) {
			WC()->cart->empty_cart();
		}

		$pending_checked = (int) get_post_meta( $order_id, 'stcpay_pending_checked', true );
		if ( ! $pending_checked ) {
			$pending_checked = 1;
		}
		$cron_delay = $this->pending_payment_cron_delays[ $pending_checked ];

		if ( ! wp_next_scheduled( 'stcpay_update_pending_payment_status', array( $order_id ) ) ) {
			wc_stcpay()->log( 'Scheduling cronjob to update stcpay payment status' );
			wp_schedule_single_event( time() + $cron_delay, 'stcpay_update_pending_payment_status', array( $order_id ) );

			update_post_meta( $order_id, 'stcpay_pending_checked', $pending_checked + 1 );
		} else {
			wc_stcpay()->log( 'Cronjob already scheduled to update stcpay payment status' );
		}
	}


	/**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_failed( $order, $payment ) {
		$order->update_status(
			'failed',
			sprintf(
				/* translators: %1$s: Payment status. %2$s: Gateway handler name. */
				__( 'Payment %1$s via %2$s.', 'woocommerce-gateway-stcpay' ),
				$payment['PaymentStatusDesc'],
				$this->handler_name
			)
		);
	}

	/**
	 * Handle a failed payment (User input is not accepted by the underlying PG).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_cancelled( $order, $payment ) {
		$order->update_status(
			'cancelled',
			sprintf(
				/* translators: %1$s: Payment status. %2$s: Gateway handler name. */
				__( 'Payment %1$s via %2$s.', 'woocommerce-gateway-stcpay' ),
				$payment['PaymentStatusDesc'],
				$this->handler_name
			)
		);
	}

	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_paid( $order, $payment ) {
		return $this->stcpay_status_completed( $order, $payment );
	}

	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Stcpay Order object
	 */
	public function stcpay_status_expired( $order, $payment ) {
		return $this->stcpay_status_failed( $order, $payment );
	}

	/**
	 * Check payment amount from IPN matches the order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param int      $amount Amount to validate.
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			wc_stcpay()->log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			/* translators: %s: Amount. */
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Stcpay amounts do not match (amount %s).', 'woocommerce-gateway-stcpay' ), $amount ) );
			return false;
		}

		return true;
	}

	protected function is_order_processing( $order_id ) {
		if ( get_transient( 'stcpay_processing_'. $order_id ) ) {
			return true;
		}

		return false;
	}

	protected function lock_order_process( $order_id ) {
		wc_stcpay()->log( 'Locking order process for ' . $order_id );
		set_transient( 'stcpay_processing_'. $order_id, true );
	}


	protected function unlock_order_process( $order_id ) {
		wc_stcpay()->log( 'Unlocking order process for ' . $order_id );
		delete_transient( 'stcpay_processing_'. $order_id );
	}
}
