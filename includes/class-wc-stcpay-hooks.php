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
		?>
		<div class="stcpay-otp-modal">
			<div class="modal-wrap">
				<div class="modal-inner">
					<p class="logo-wrap"><img src="<?php echo  WC_STCPAY_URL . '/assets/images/stcpay-logo.png'; ?>" /></p>
					<label for="stcpay-otp-value">Please enter the OTP you have received from STC Pay</label>
					<input id="stcpay-otp-value" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" name="stcpay_otp_value" />
					<button class="submit-otp" type="button">Verify OTP</button>
				</div>
			</div>
		</div>
		<?php
	}
}
