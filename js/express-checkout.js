/*

This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

jQuery(document).ready(function () {
 var validating = 0;

 function validateExpressCheckout () {
    var termscheck = validateTermsAndConditions();
    var validation = termscheck;
    if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         validation = wp.hooks.applyFilters('vippsValidateExpressCheckoutForm', validation);
    }
    return validation;
 }

 function validateTermsAndConditions () {
   var termsbox = jQuery('.input-checkbox[name="terms"]');
   if (termsbox.length == 0) return true;
   if (termsbox.is(':checked')) return true;
   termsbox.closest('.validate-required').addClass('woocommerce-invalid');
   termsbox.closest('.validate-required').addClass('woocommerce-invalid-required-field ');
   var message= VippsCheckoutMessages['termsAndConditionsError'];
   addErrorMessage(message, jQuery('#vippsdata'));
   return false;
 }

  // Remove old error messages
 function removeErrorMessages () {
   jQuery('.woocommerce-error.vipps-error').fadeOut(300, function () {  jQuery(this).remove(); });
   jQuery(document).trigger('woo-vipps-remove-errors');
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsRemoveErrorMessages');
   }
   validating=0;
   jQuery('.woocommerce-invalid woocommerce-invalid-required-field').removeClass('woocommerce-invalid woocommerce-invalid-required-field');
   jQuery('.woocommerce-invalid').removeClass('woocommerce-invalid');
   jQuery('.woocommerce-invalid-required-field').removeClass('woocommerce-invalid-required-field');
 }

 // And add new ones
 function addErrorMessage(msg,element) {
   // Allow developers to customize error message by hiding vipps-default-error-message and hooking woo-vipps-error-message <messsage>,<element>
   var msg = "<p><ul class='woocommerce-error vipps-error vipps-default-error-message vipps-buy-now-error'><li>"+msg+"!</li></ul></p>";
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      msg = wp.hooks.applyFilters('vippsErrorMessage',msg, element);
   }
   jQuery(document).trigger('woo-vipps-error-message',[msg, element]);
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsAddErrorMessage', msg, element);
   }

   jQuery(msg).hide().insertAfter(element).fadeIn(300);
   jQuery('.woocommerce-error.vipps-error').click(removeErrorMessages);
 }

 // Show a success message, allow overriding using hooks
 function vippsSuccess() {
    var msgcontent = VippsCheckoutMessages['successMessage'];
    jQuery('.vipps-express-checkout').blur();
    jQuery('#do-express-checkout').hide();

    if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined' && wp.hooks.hasAction('vippsSuccessMessage')) {
      wp.hooks.doAction('vippsSuccessMessage', msgcontent);
      return;
    }
     jQuery('#vipps-status-message').empty();
     var msg = jQuery('<div id=\'success\' class="woocommerce-info">'+ msgcontent +'</div>');
     jQuery('#vipps-status-message').append(msg);
     jQuery('.vipps-express-checkout').blur();
 } 
 // The default no-info "temporarily unavailable" message
 function vippsError() {
     jQuery('#do-express-checkout').hide();
     jQuery('.vipps-express-checkout').blur();
     var msgcontent = VippsCheckoutMessages['temporaryError'];

     if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined' && wp.hooks.hasAction('vippsSuccessMessage')) {
       wp.hooks.doAction('vippsErrorMessage', msgcontent);
       return;
     }

     jQuery('#vipps-status-message').empty();
     var msg = jQuery('<div id=\'error\' class="woocommerce-message woocommerce-error">'+ msgcontent +'</div>');
     jQuery('#vipps-status-message').append(msg);
 } 
 // An actual failure message from the backend
 function vippsFailure(msgcontent) {
     jQuery('#do-express-checkout').hide();
     jQuery('.vipps-express-checkout').blur();

     if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined' && wp.hooks.hasAction('vippsFailureMessage')) {
       wp.hooks.doAction('vippsFailureMessage', msgcontent);
       return;
     }

     jQuery('#vipps-status-message').empty();
     var msg = jQuery('<div id=\'failure\' class="woocommerce-message woocommerce-error">'+ msgcontent +'</div>');
     jQuery('#vipps-status-message').append(msg);
 } 

 function doExpressCheckout (e) {
   if (e) e.preventDefault(); 
   if (validating) return false;
   validating=true;
   removeErrorMessages();
   var ok = validateExpressCheckout();
   if (!ok) {
    jQuery('.vipps-express-checkout').blur();
    validating=false;
    return false;
   }

   // Required for the order attribution thing IOK 2024-01-16
   if (jQuery('#vippsorderattribution').val() == 1) {
       try {
           console.log("Triggering init_checkout");
           jQuery(document.body).trigger( 'init_checkout' );
       } catch (error) {
           console.log("Could not trigger init-checkout: %j", error);
       }
   }

   jQuery('#do-express-checkout').prop('disabled',true);
   jQuery('#do-express-checkout').prop('inactive',true);
   jQuery('body').addClass('processing');

   var data  = jQuery("#vippsdata").serialize();
   jQuery.ajax(VippsConfig['vippsajaxurl'], {
    "method": "POST",
    "data":data,
    "cache":false,
    "dataType": "json",
    "error": function (xhr, statustext, error) {
      console.log("Error creating express checkout:" + statustext + " " + error);
      vippsError();
      jQuery('body').removeClass('processing');
      validating=0;
    },
    "success": function (result, statustext, xhr) {
     if (result["ok"]) {
       vippsSuccess();
       validating=0;
       window.location.replace(result["url"]);
     } else {
       console.log("Failure!");
       jQuery('#do-express-checkout').hide();
       vippsFailure(result['msg']);
       jQuery('body').removeClass('processing');
       jQuery('.vipps-express-checkout').blur();
       validating=0;
     }
    },
    "timeout": 0
   });
 }

 // No interaction neccessary => just buy stuff
 var buttons = jQuery('#do-express-checkout');
 if (buttons.length == 0) {
   // Give other scripts a chance to run
   setTimeout(doExpressCheckout, 100);
//   doExpressCheckout();
 } else {
   buttons.click(doExpressCheckout);   
   jQuery('input').change(removeErrorMessages);
 }
 


});
