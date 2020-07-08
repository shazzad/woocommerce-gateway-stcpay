(function($){
	'use strict';

  var formatData = function(data){
    //console.log( JSON.parse('{"' + decodeURI(data).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g,'":"') + '"}') );
    var parts = data.split("&"), pair, out = {};
    for (var i=0; i<parts.length; i++){
      pair = parts[i].split("=");
      out[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
    }
    //console.log(out)
    return out;
  }

  var recentAjax = {url: '', data: {}, response: {}};

  $( document )
    .ajaxSend(function( event, xhr, settings ) {
      recentAjax.url = settings.url;
      recentAjax.xhr = xhr;
      //recentAjax.response = xhr.responseJSON;
      recentAjax.data = formatData(settings.data);
    });

  var $checkout_form = $( 'form.checkout' );

  $checkout_form.on( 'checkout_place_order_success', function(){
    console.log( recentAjax.url );
    if (recentAjax.url === '/?wc-ajax=checkout') {
      recentAjax.xhr.done(function(result){
        if ( result.stcpay_OtpReference ) {
          console.log('Enter your OTP...');
          $('#stcpay_OtpReference').val(result.stcpay_OtpReference);
          $('#stcpay_PmtReference').val(result.stcpay_PmtReference);
        } else if (result && result.otp_confirmed && result.redirect) {
          console.log('redirecting...');
          window.location = result.redirect;
       }
      });
    }
    return false;
  });

})(jQuery);
