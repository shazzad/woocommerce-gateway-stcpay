(function($){
	'use strict';

  var stcpay_checkout = {
    $modal: $('#stcpay-otp-modal'),
    $confirmationForm: $('.otp-confirmation-form'),
    $confirmBtn: $('.otp-confirmation-form .confirm-otp-btn'),
    $requestForm: $('.otp-request-form'),
    $requestBtn: $('.otp-request-form .request-otp-btn'),

    formatData: function(data){
      var parts = data.split("&"), pair, out = {};
      for (var i=0; i<parts.length; i++){
        pair = parts[i].split("=");
        out[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
      }
      return out;
    },
    recentAjax: {
      url: '',
      data: {},
      response: {}
    },
    captureAjaxData: function( event, xhr, settings ) {
      stcpay_checkout.recentAjax.url = settings.url;
      stcpay_checkout.recentAjax.xhr = xhr;
      stcpay_checkout.recentAjax.data = stcpay_checkout.formatData(settings.data);
    },
    getSelectedPaymentMethod: function(){
      return $( '.woocommerce input[name="payment_method"]:checked' ).val();
    },

    otpExpireTimer: null,
    otpExpires: 0,
    displayOtpExpirationCountdown: function(time) {
      this.otpExpireTimer = null;
      this.otpExpires = time;
      this.updateExpirationMessage();
    },
    updateExpirationMessage: function() {
      if ( ! stcpay_checkout.otpExpires ) {
        clearTimeout( stcpay_checkout.otpExpireTimer );
      } else {
        var ts = Math.round((new Date()).getTime() / 1000);
        var expires = stcpay_checkout.otpExpires - ts;

        // console.log( otpExpires - ts );
        if ( expires > 0 ) {
          stcpay_checkout.$modal
            .find('.modal-notice')
              .html('<div class="countdown">' + stcpay.textOtpExpiresIn.replace('{expires}', expires) + '</div>')
                .show();
          stcpay_checkout.otpExpireTimer = setTimeout(stcpay_checkout.updateExpirationMessage, 1000);
        } else {
          clearTimeout( stcpay_checkout.otpExpireTimer );

          stcpay_checkout.$modal
            .find('.modal-notice')
              .empty()
                .hide();

          stcpay_checkout.$confirmationForm
            .hide()
              .find('.form-notice')
                .empty();

          stcpay_checkout.$requestBtn
            .removeAttr('disabled')
              .html(stcpay_checkout.$requestBtn.data('text'));

          stcpay_checkout.$requestForm
            .show()
              .find('.form-notice')
                .html('<div class="woocommerce-error">' + stcpay.textOtpExpired + '</div>')
                  .show();
        }
      }
    },

    displayOtpModal: function() {
      if ( ! stcpay_checkout.$modal.hasClass('show') ) {
        stcpay_checkout.$modal.addClass('show');
        $('#stcpay-mobile-no').attr('readonly', 'readonly');
      }
    },
    hideOtpModal: function() {
      stcpay_checkout.$modal.removeClass('show');
      $('#stcpay-mobile-no').removeAttr('readonly').focus();
    },
    onCheckoutPlaceOrderSuccess: function(e){
      if ('stcpay' !== stcpay_checkout.getSelectedPaymentMethod() || ! stcpay_checkout.recentAjax.url || stcpay_checkout.recentAjax.url !== '/?wc-ajax=checkout') {
        return true;
      }

      e.preventDefault();

      stcpay_checkout.recentAjax.xhr
      .done(function(result){
        $('#stcpay-action').val(result.stcpay_action);

        if ( 'request_payment' === result.stcpay_action ) {
          stcpay_checkout.hideOtpModal();

        } else if ( 'confirm_payment' === result.stcpay_action ) {
          // Stop woocommerce scroll to notices animation.
          $('html, body').stop();

          stcpay_checkout.$confirmationForm.show();
          stcpay_checkout.$requestForm.hide();

          stcpay_checkout.displayOtpModal();
          if (result.otpExpires) {
            stcpay_checkout.displayOtpExpirationCountdown(result.otpExpires);
          }
          // console.log('confirm');

          stcpay_checkout.$confirmationForm
            .find('.form-notice')
              .html(result.messages)
                .show();

          stcpay_checkout.$confirmBtn
            .removeAttr('disabled')
              .html( stcpay_checkout.$confirmBtn.data('text') )

        } else if ( 'payment_confirmed' === result.stcpay_action  && result.redirect) {
          // Stop woocommerce scroll to notices animation.
          $('html, body').stop();

          window.location = result.redirect;
       }
      });

      return false;
    },
    onClickModalCloseButton: function(e){
      e.preventDefault();
      clearTimeout( stcpay_checkout.otpExpireTimer );

      stcpay_checkout.$modal.removeClass('show');
      $('#stcpay-mobile-no').removeAttr('readonly').focus();
      $('#order_review').unblock();

      return false;
    },

    isOrderPayPage: function(){
      return $( document.body ).hasClass( 'woocommerce-order-pay' );
    },

    onClickRequestOtpButton: function(e){
      e.preventDefault();

      if (stcpay_checkout.isOrderPayPage()) {
        stcpay_checkout.orderPayRequestOtp();
      } else {
        stcpay_checkout.checkoutRequestOtp();
      }

      return false;
    },
    checkoutRequestOtp: function(){
      stcpay_checkout.$requestBtn
        .attr('disabled', 'disabled')
          .text( stcpay_checkout.$requestBtn.data('loading') );

      stcpay_checkout.$requestForm
        .find('.form-notice')
          .empty()
            .hide();

      $('#stcpay-otp-value').val('');
      $('#stcpay-action').val('request_otp');
      $('#place_order').trigger('submit');
    },
    orderPayRequestOtp: function(){
      stcpay_checkout.$requestBtn
        .attr('disabled', 'disabled')
          .text( stcpay_checkout.$requestBtn.data('loading') );

      stcpay_checkout.$requestForm
        .find('.form-notice')
          .empty()
            .hide();

      var data = {
        action: 'stcpay_request_otp',
        order_key: stcpay.orderKey,
        mobile_no: $('#stcpay-mobile-no').val()
      };

      $.post(stcpay.ajaxUrl, data)
      .done(function(r){
        if (r.success) {
          stcpay_checkout.$requestForm
            .hide();

          stcpay_checkout.$confirmationForm
            .show()
              .find('.form-notice')
                .html(r.data.message)
                  .show();

          stcpay_checkout.displayOtpExpirationCountdown(r.data.otpExpires);

        } else {
          stcpay_checkout.otpExpires = 0;
          stcpay_checkout.hideOtpModal();
          $('#order_review').unblock();
        }
      })
      .complete(function(){
        stcpay_checkout.$requestBtn
          .removeAttr('disabled')
          .text( stcpay_checkout.$requestBtn.data('text') );
      });
    },

    onClickConfirmOtpButton: function(e){
      e.preventDefault();

      if ( ! $('#stcpay-modal-otp-value').val() ) {
        stcpay_checkout.$confirmationForm.find('.form-notice').html('<div class="woocommerce-error">Enter OTP</div>').show();
        $('#stcpay-modal-otp-value').focus();
      } else {
        if (stcpay_checkout.isOrderPayPage()) {
          stcpay_checkout.orderPayConfirmOtp();
        } else {
          stcpay_checkout.checkoutConfirmOtp();
        }
      }
    },
    checkoutConfirmOtp: function(){
      stcpay_checkout.$confirmBtn
        .attr('disabled', 'disabled')
          .text( stcpay_checkout.$confirmBtn.data('loading') );

      $('#stcpay-otp-value').val($('#stcpay-modal-otp-value').val());
      $('#stcpay-action').val('confirm_otp');
      $('#place_order').trigger('submit');
    },
    orderPayConfirmOtp: function(){
      stcpay_checkout.$confirmBtn
        .attr('disabled', 'disabled')
        .text( stcpay_checkout.$confirmBtn.data('loading') );

      var data = {
        action: 'stcpay_confirm_otp',
        order_key: stcpay.orderKey,
        otp_value: $('#stcpay-modal-otp-value').val()
      };

      $.post(stcpay.ajaxUrl, data)
      .done(function(r){
        if (r.success) {
          stcpay_checkout.$confirmationForm
            .find('.form-actions, #stcpay-modal-otp-value')
              .hide();

          stcpay_checkout.$confirmationForm
            .find('.form-notice')
              .html('<div class="woocommerce-message">'+ r.data.message + '</div>')
              .show();

          $('#order_review')
            .addClass('confirmed')
              .submit();
        } else {
          stcpay_checkout.$confirmationForm
            .find('.form-notice')
              .html('<div class="woocommerce-error">'+ r.data.message + '</div>')
                .show();
        }
      })
      .complete(function(){
        stcpay_checkout.$confirmBtn
          .removeAttr('disabled')
            .text( stcpay_checkout.$confirmBtn.data('text') );
      });
    },

    onSubmitOrderPayForm: function(e){
      if ('stcpay' === stcpay_checkout.getSelectedPaymentMethod() && ! $( '#order_review' ).hasClass('confirmed') ) {
        e.preventDefault();

        if (! $('#stcpay-mobile-no').val()) {

          setTimeout(function(){
            $('#order_review').unblock();
          }, 1);

          $('.field-stcpay-mobile-no .field-notice')
            .html('<div class="woocommerce-error">' + stcpay.textEnterWalletNumber + '</div>');
        } else {
          var data = {
            action: 'stcpay_request_otp',
            order_key: stcpay.orderKey,
            mobile_no: $('#stcpay-mobile-no').val()
          };

          $.post(stcpay.ajaxUrl, data)
          .done(function(r){
            if (r.success) {
              $('.field-stcpay-mobile-no .field-notice')
                .hide()
                  .empty();

              stcpay_checkout.$confirmationForm
                .show()
                  .find('.form-notice')
                    .empty()
                      .hide();

              stcpay_checkout.$requestForm
                .hide()
                  .find('.form-notice')
                    .empty()
                      .hide();

              stcpay_checkout.displayOtpModal();
              stcpay_checkout.displayOtpExpirationCountdown(r.data.otpExpires);
            } else {
              stcpay_checkout.otpExpires = 0;
              stcpay_checkout.hideOtpModal();
              $('#order_review').unblock();

              $('.field-stcpay-mobile-no .field-notice')
                .html('<div class="woocommerce-error">' + r.data.message + '</div>')
                  .show();
            }
          });
        }

        return false;
      }
    },

    init: function(){
      // Close Modal
      $(document.body).on('click', '#stcpay-otp-modal .modal-close-btn', this.onClickModalCloseButton);

      /* OTP Confirmation */
      $(document.body).on('click', '#stcpay-otp-modal .confirm-otp-btn', this.onClickConfirmOtpButton);

      /* OTP Request */
      $(document.body).on('click', '#stcpay-otp-modal .request-otp-btn', this.onClickRequestOtpButton);

      /* Order Pay */
      if ( this.isOrderPayPage() ) {
        $('#order_review').on( 'submit', stcpay_checkout.onSubmitOrderPayForm);
      }

      // Checkout ====
      // Capture last ajax request.
      $(document).ajaxSend(stcpay_checkout.captureAjaxData);
      // Checkout page success takeover.
      $( 'form.checkout' ).on( 'checkout_place_order_success', stcpay_checkout.onCheckoutPlaceOrderSuccess);
    },
  };

  stcpay_checkout.init();

})(jQuery);
