<?php
/**
 * WooCommerce Stcpay Plugin.
 *
 * @class       WC_Stcpay
 **/


final class WC_Stcpay {

	/**
	 * @var plugin name
	 */
	public $name = 'WooCommerce Gateway Stcpay';


	/**
	 * @var plugin version
	 */
	public $version = '0.0.1';


	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	protected static $_instance = null;


	/**
	 * @var plugin settings
	 */
	protected static $settings = null;


	/**
	 * @var available api environments
	 */
	public static $environments = array(
		'staging'    => array(
			'name'     => 'Staging',
			'endpoint' => 'https://b2btest.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
		'production' => array(
			'name'     => 'Production',
			'endpoint' => 'https://b2b.stcpay.com.sa/B2B.DirectPayment.WebApi/DirectPayment/V4',
		),
	);


	public static $log = false;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}


	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {}


	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {}


	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->initialize();
		$this->register_hooks();
	}

	/**
	 * Define constants
	 */
	private function define_constants() {
		define( 'WC_STCPAY_DIR', plugin_dir_path( WC_STCPAY_PLUGIN_FILE ) );
		define( 'WC_STCPAY_URL', plugin_dir_url( WC_STCPAY_PLUGIN_FILE ) );
		define( 'WC_STCPAY_BASENAME', plugin_basename( WC_STCPAY_PLUGIN_FILE ) );
		define( 'WC_STCPAY_VERSION', $this->version );
		define( 'WC_STCPAY_NAME', $this->name );
	}

	/**
	 * Initialize plugin.
	 */
	private function initialize() {
		if ( is_null( self::$settings ) ) {
			self::$settings = get_option( 'woocommerce_stcpay_settings', array() );
		}
	}

	/**
	 * Include plugin dependency files
	 */
	private function includes() {
		require WC_STCPAY_DIR . '/includes/class-wc-stcpay-utils.php';
		require WC_STCPAY_DIR . '/includes/class-wc-gateway-stcpay.php';
		require WC_STCPAY_DIR . '/includes/class-wc-stcpay-request.php';
		require WC_STCPAY_DIR . '/includes/class-wc-stcpay-client.php';
	}


	/**
	 * Register hooks
	 */
	private function register_hooks() {
		add_action( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ), 0 );
		add_filter( 'plugin_action_links_' . WC_STCPAY_BASENAME, array( $this, 'plugin_action_links' ) );
		# add_action( 'woocommerce_before_settings_checkout', array( $this, 'test_gateway' ) );
	}


	/**
	 * Add the gateways to WooCommerce.
	 */
	public function add_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Stcpay';
		return $methods;
	}


	/**
	 * Adds plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$links['settings'] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stcpay' ) . '">' . __( 'Settings', 'stcpay' ) . '</a>';
		return $links;
	}


	/**
	 * Get option from plugin settings
	 */
	public static function get_option( $name, $default = null ) {
		if ( ! empty( self::$settings ) ) {
			if ( array_key_exists( $name, self::$settings ) ) {
				return self::$settings[ $name ];
			}
		}

		return $default;
	}


	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( 'yes' === self::get_option( 'debug', 'yes' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}

			self::$log->log( $level, $message, array( 'source' => 'stcpay' ) );
		}
	}


	// Debugging
	public function test_gateway() {
		self::log( 'Testing payment 3' );
		return;

		$client = new WC_Stcpay_Client();
		$order = wc_get_order( 11202 );
		$request_payment = $client->request_payment( $order, 966539342897 );
		#echo WC()->countries->countries[ $order->get_billing_country() ];
		WC_Stcpay_Utils::p( $request_payment );
		#WC_Stcpay_Utils::p( $order );
	}
}
