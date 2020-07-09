jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stcpay admin functions.
	 */
	var wc_stcpay_admin = {
		getEnvironment: function() {
			return $( '#woocommerce_stcpay_environment' ).val();
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_stcpay_environment', function() {
				var environment = $( '#woocommerce_stcpay_environment' ).val(),
					staging_ssl_cert_file = $( '#woocommerce_stcpay_staging_ssl_cert_file' ).parents( 'tr' ).eq( 0 ),
          staging_ssl_key_file = $( '#woocommerce_stcpay_staging_ssl_key_file' ).parents( 'tr' ).eq( 0 ),
          staging_ssl_password = $( '#woocommerce_stcpay_staging_ssl_password' ).parents( 'tr' ).eq( 0 ),
					production_ssl_cert_file = $( '#woocommerce_stcpay_production_ssl_cert_file' ).parents( 'tr' ).eq( 0 ),
					production_ssl_key_file = $( '#woocommerce_stcpay_production_ssl_key_file' ).parents( 'tr' ).eq( 0 ),
					production_ssl_password = $( '#woocommerce_stcpay_production_ssl_password' ).parents( 'tr' ).eq( 0 );

				if ( 'staging' === environment ) {
					staging_ssl_cert_file.show();
          staging_ssl_key_file.show();
          staging_ssl_password.show();

					production_ssl_cert_file.hide();
          production_ssl_key_file.hide();
					production_ssl_password.hide();
				} else {
          production_ssl_cert_file.show();
          production_ssl_key_file.show();
					production_ssl_password.show();

          staging_ssl_cert_file.hide();
          staging_ssl_key_file.hide();
          staging_ssl_password.hide();
				}
			});

			$( '#woocommerce_stcpay_environment' ).change();
		}
	};

	wc_stcpay_admin.init();
});
