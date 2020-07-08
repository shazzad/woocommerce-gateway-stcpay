<?php
/**
 * WooCommerce Stcpay Payment Gateway Request.
 *
 * Handle outbound requests to Stcpay API
 *
 * @class       WC_Gateway_Stcpay_Request
 */

class WC_Gateway_Stcpay_Request {

	/**
	 * Get the Stcpay request URL for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function get_payment_url( $order ) {
		try {
			$params = $this->create_order_params( $order );

			/* send customer id if required */
			if ( 'yes' === wc_stcpay()->get_option( 'send_customer_id' ) ) {
				if ( $order->get_customer_id() > 0 ) {
					$params ['customer_id'] = $order->get_customer_id();
				}
			}

			$stcpay_order = Stcpay\Model\Order::create( $params );

			if ( wp_is_mobile() ) {
				$payment_link = $stcpay_order->paymentLinks->mobile;
			} else {
				$payment_link = $stcpay_order->paymentLinks->web;
			}

			// TEST
			// $payment_link = 'null/merchant/pay/ord_6e547fe7622c45cdbf62f7a8e123a768';

			if ( ! wp_http_validate_url( $payment_link ) ) {
				return new WP_Error( 'stcpayInvalidPaymentLink', sprintf( __( 'Invalid payment link - %s', 'stcpay' ), $payment_link ) );
			}

			return $payment_link;

		} catch( Stcpay\Exception\AuthenticationException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid api key', 'stcpay') );
		} catch( Stcpay\Exception\InvalidRequestException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid request', 'stcpay') );
		} catch( Stcpay\Exception\APIConnectionException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Could not connect to api server', 'stcpay') );
		}
	}


	/**
	 * Refund for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function create_refund( $order_id, $amount, $refund_id = 0 ) {
		wc_stcpay()->log( 'Refund requested for order # ' . $order_id . ', amount : '. $amount );
		try {
			$wc_order = wc_get_order( $order_id );
			$params = array( 'order_id' => $wc_order->get_order_key(), 'amount' => $amount );
			if ( ! empty( $refund_id ) ) {
				$params['unique_request_id'] = $refund_id;
			}
			$result = Stcpay\Model\Order::refund( $params );
			wc_stcpay()->log( 'Order Refunds: ' . wc_print_r( $result->refunds, true ) );

			return true;
		} catch( Stcpay\Exception\AuthenticationException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid api key', 'stcpay') );
		} catch( Stcpay\Exception\InvalidRequestException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid request', 'stcpay') );
		} catch( Stcpay\Exception\APIConnectionException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Could not connect to api server', 'stcpay') );
		}
	}


	/**
	 * Get order from stcpay.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function get_order( $order_id ) {
		try {
			$wc_order = wc_get_order( $order_id );
			$stcpay_order = Stcpay\Model\Order::Status( array( 'order_id' => $wc_order->get_order_key() ) );
			return $stcpay_order;
		} catch( Stcpay\Exception\AuthenticationException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid api key', 'stcpay') );
		} catch( Stcpay\Exception\InvalidRequestException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Invalid request', 'stcpay') );
		} catch( Stcpay\Exception\APIConnectionException $e) {
			return new WP_Error( 'stcpayAuthenticationException', $e->getErrorMessage() ? $e->getErrorMessage() : __('Could not connect to api server', 'stcpay') );
		}
	}


	/**
	 * Build parameters using wc order to create an order on Stcpay
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public function create_order_params( $order ) {
		if ( 'yes' === wc_stcpay()->get_option('return_handler_enabled') ) {
			$return_url = WC()->api_request_url('wc_gateway_stcpay_return');
		} else {
			$return_url = $order->get_checkout_order_received_url();
		}

		$params = array(
			'order_id' => $order->get_order_key(),
			'amount' => $order->get_total(),
			'currency' => $order->get_currency(),
			'customer_email' => $order->get_billing_email(),
			'customer_phone' => $order->get_billing_phone(),
			//'description' => '',
			'return_url' => $return_url,
			'billing_address_first_name' => $order->get_billing_first_name(),
			'billing_address_last_name' => $order->get_billing_last_name(),
			'billing_address_line1' => $order->get_billing_address_1(),
			'billing_address_line2' => $order->get_billing_address_2(),
			'billing_address_city' => $order->get_billing_city(),
			'billing_address_state' => $order->get_billing_state(),
			'billing_address_postal_code' => $order->get_billing_postcode(),
			'billing_address_phone' => $order->get_billing_phone(),
			'billing_address_country_code_iso' => $order->get_billing_country(),
			'billing_address_country' => $order->get_billing_country() ? WC()->countries->countries[ $order->get_billing_country() ] : '',

			'shipping_address_first_name' => $order->get_shipping_first_name(),
			'shipping_address_last_name' => $order->get_shipping_last_name(),
			'shipping_address_line1' => $order->get_shipping_address_1(),
			'shipping_address_line2' => $order->get_shipping_address_2(),
			'shipping_address_city' => $order->get_shipping_city(),
			'shipping_address_state' => $order->get_shipping_state(),
			'shipping_address_postal_code' => $order->get_shipping_postcode(),
			'shipping_address_phone' => $order->get_billing_phone(),
			'shipping_address_country_code_iso' => $order->get_shipping_country(),
			'shipping_address_country' => $order->get_shipping_country() ? WC()->countries->countries[ $order->get_shipping_country() ] : '',
		);

		return $params;
	}
}
