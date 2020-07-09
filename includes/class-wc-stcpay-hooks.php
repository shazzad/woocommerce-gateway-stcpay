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
		add_action( 'woocommerce_checkout_after_order_review', array( __CLASS__, 'render_otp_form' ) );
	}

	public static function render_otp_form( $checkout ) {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! isset( $available_gateways['stcpay'] ) ) {
			return;
		}

		# WC_Stcpay_Utils::p( WC()->checkout );

		?>
		<div class="stcpay-otp-modal">
			<div class="modal-wrap">
				<div class="modal-inner">
					<p class="logo-wrap"><img src="<?php echo  WC_STCPAY_URL . '/assets/images/stcpay-logo.png'; ?>" /></p>
					<label for="stcpay-otp-value"><?php esc_html_e( 'Please enter the OTP you have received from STC Pay', 'woocommerce-gateway-stcpay' ); ?></label>
					<input id="stcpay-otp-value" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="stcpay_otp_value" />
					<div class="modal-message"></div>
					<div class="modal-actions">
						<button class="button verify-otp" type="button" data-text="<?php esc_attr_e( 'Verify OTP', 'woocommerce-gateway-stcpay' ); ?>" data-loading="<?php esc_attr_e( 'Verifying', 'woocommerce-gateway-stcpay' ); ?>"><?php esc_html_e( 'Verify OTP', 'woocommerce-gateway-stcpay' ); ?></button>
						<button class="button modal-close" type="button"><?php esc_html_e( 'Cancel', 'woocommerce-gateway-stcpay' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
