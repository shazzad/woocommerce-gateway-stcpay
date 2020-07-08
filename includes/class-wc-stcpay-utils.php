<?php
/**
 * WooCommerce Stcpay Utility Class.
 *
 * Provides static methods as helpers.
 *
 * @class       WC_Stcpay_Utils
**/


class WC_Stcpay_Utils {

	public static function p( $a ) {
		echo '<pre>';
		print_r($a);
		echo '</pre>';
	}

	public static function d( $a ) {
		self::p( $a );
		exit;
	}
}
