(function($){
	'use strict';

  var formatData = function(data){
    var parts = data.split("&"), pair, out = {};
    for (var i=0; i<parts.length; i++){
      pair = parts[i].split("=");
      out[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
    }
    return out;
  }

  var recentAjax = {url: '', data: {}, response: {}};

  $( document )
    .ajaxSend(function( event, xhr, settings ) {
      recentAjax.url = settings.url;
      recentAjax.xhr = xhr;
      recentAjax.data = formatData(settings.data);
    });

  var showHideOtpField = function() {
    var selectedPaymentMethod = $( '.woocommerce-checkout input[name="payment_method"]:checked' ).val();

    if ('stcpay' === selectedPaymentMethod) {
      if ( $('#stcpay_otp_reference').val() || ! $('#stcpay_payment_reference').val() ) {
        $('.field-stcpay-otp-value').hide();
      } else {
        $('.field-stcpay-otp-value').show();
      }
    }
  };

  $( document.body ).on( 'updated_checkout', showHideOtpField );
  $( document.body ).on( 'payment_method_selected', showHideOtpField );

  $( document.body ).on( 'click', '.stcpay-otp-modal .submit-otp', function(e){
    e.preventDefault();
    $(this).attr('disabled', 'disabled');
    $('#place_order').trigger('submit');
  } );

  $( 'form.checkout' ).on( 'checkout_place_order_success', function(){
    var selectedPaymentMethod = $( '.woocommerce-checkout input[name="payment_method"]:checked' ).val();
    // console.log( recentAjax.url );
    if ('stcpay' === selectedPaymentMethod && recentAjax.url === '/?wc-ajax=checkout') {
      recentAjax.xhr.done(function(result){

        if ( 'request' === result.stcpay_otp ) {
          // Stop woocommerce scroll to notices animation.
          $( 'html, body' ).stop();

          $('.stcpay-otp-modal').addClass('show');
          $('#stcpay-mobile-no').attr('readonly', 'readonly');
          $('#stcpay_otp_reference').val(result.stcpay_otp_reference);
          $('#stcpay_payment_reference').val(result.stcpay_payment_reference);

        } else if ( 'success' === result.stcpay_otp  && result.redirect) {
          // Stop woocommerce scroll to notices animation.
          $( 'html, body' ).stop();

          window.location = result.redirect;
       }
      });

      return false;
    } else {
      return true;
    }
  });

})(jQuery);
