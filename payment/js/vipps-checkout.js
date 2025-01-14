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



jQuery( document ).ready( function() {
    // This gets loaded conditionally when the Vipps Checkout page is used IOK 2021-08-25
    var pollingdone=false;
    var polling=false;
    var listening=false;
    var initiating=false;

    // Just in case we need to do this by button.
    jQuery('.vipps_checkout_button.button').click(function (e) { initVippsCheckout() });
    if (jQuery('.vipps_checkout_startdiv').length>0) {
       // which we must if we don't have the visibility API
      if (typeof document.hidden == "undefined") {
         jQuery('.vipps_checkout_startdiv').css('visibility', 'visible');
      } else {
         document.addEventListener('visibilitychange', initWhenVisible, false);
         initWhenVisible();
      }
    }


    // Initialize the Vipps Checkout process
    function initVippsCheckout () {
      // Prevent multiple initializations
      if (initiating) return;
      initiating = true;

      // Set visual indicators for processing state
      jQuery("body").css("cursor", "progress");
      jQuery("body").addClass('processing');

      // Disable all Vipps checkout buttons
      jQuery('.vipps_checkout_button.button').each(function () {
           jQuery(this).addClass('disabled');
           jQuery(this).css("cursor", "progress");
      });

      // Check cart total before proceeding with checkout NT-2024-09-07
      // handle any errors in handleCheckoutError IOK 2024-09-09
      return validateCart(proceedWithCheckout, handleCheckoutError);
    }

    // Check if the cart total meets the minimum required amount NT-2024-09-07
    function validateCart(success, failure) {
      jQuery.ajax(VippsConfig['vippsajaxurl'], {
        cache: false,
        dataType: 'json',
        data: { 'action': 'vipps_checkout_validate_cart' },
        method: 'POST',
        success: function(result) {
          // If cart total is valid, proceed
          if (result.success) {
            success();
          } else {
            failure(result.data.message)
          }
        },
        error: function(xhr, statustext, error) {
           // Ignore any validation errors if ajax somehow breaks, but log the thing
          console.log("Error validating cart: " + statustext);
          success();
        }
      });
    }

    function proceedWithCheckout() {
      // Try to start Vipps Checkout with any session provided.
      function doVippsCheckout() {
         if (!VippsSessionState) return false;
         let args = { 
                     checkoutFrontendUrl: VippsSessionState['checkoutFrontendUrl'].replace(/\/$/, ''),
                     token:  VippsSessionState['token'],
                     iFrameContainerId: "vippscheckoutframe",
                     language: VippsConfig['vippslanguage']
             /* IOK 2024-04-10 future improvements of order management coming 
                     ,on: {
                         shipping_option_selected: function (data) { console.log("shipping %j", data); },
                         total_amount_changed: function (data) { console.log("money %j", data); },
                         session_status_changed: function (data) { console.log("session status: %j", data); },
                         shipping_address_changed: function (data) { console.log("shipping address: %j", data); },
                         customer_information_changed: function (data) { console.log("customer info changed: %j", data); }
                     }
                     */
         };
         let vippsCheckout = VippsCheckout(args);
         jQuery("body").css("cursor", "default");
         jQuery('.vipps_checkout_button.button').css("cursor", "default");
         jQuery('.vipps_checkout_startdiv').hide();
         listenToFrame();
         return true;
      }

      if (!doVippsCheckout()) {
          let data  = {};
          let formdata = jQuery("#vippsdata").serializeArray();
          for(i=0;i<formdata.length;i++) {
              let entry = formdata[i];
              data[entry.name] = entry.value;
          }
          if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
              data = wp.hooks.applyFilters('vippsCheckoutInitalizeSessionData', data);
          }
          data['action'] = 'vipps_checkout_start_session';
          data['vipps_checkout_sec'] = jQuery('#vipps_checkout_sec').val();
          data['orderid'] = jQuery('#vippsorderid').val();

          jQuery.ajax(VippsConfig['vippsajaxurl'],
                    {   cache:false,
                        timeout: 0,
                        dataType:'json',

                        data: data,
                        method: 'POST', 
                        error: function (xhr, statustext, error) {
                            jQuery("body").css("cursor", "default");
                            jQuery('.vipps_checkout_button.button').css("cursor", "default");
                            jQuery('.vipps_checkout_startdiv').hide();
                            console.log('Error initiating transaction : ' + statustext + ' : ' + error);
                            pollingdone=true;
                            jQuery("body").removeClass('processing');
                            jQuery('#vippscheckouterror').show();
                            jQuery('#vippscheckoutframe').html('<div style="display:none">Error occured</div>');
                            if (error == 'timeout')  {
                                console.log('Timeout creating Checkout session at vipps');
                            }
                        },
                        'success': function (result,statustext, xhr) {
                            jQuery("body").css("cursor", "default");
                            jQuery('.vipps_checkout_button.button').css("cursor", "default");
                            jQuery('.vipps_checkout_startdiv').hide();

                            // Save order created or fetched
                            if ( result['data']['orderid'] ) {
                               jQuery('#vippsorderid').val(result['data']['orderid']);
                            }
    
                            if (! result['data']['ok']) {
                                console.log("Error starting Vipps MobilePay Checkout %j", result);
                                jQuery('#vippscheckouterror').show();
                                jQuery("body").removeClass('processing');
                                return;
                            }
                            if (result['data']['redirect']) {
                                window.location.replace(result['redirect']);
                                return;
                            }
                            if (result['data']['src']) {
                                VippsSessionState = { token: result['data']['token'], checkoutFrontendUrl: result['data']['src'] }
                                doVippsCheckout();
                            }
                        },
                    });
        }
    }

    // Function to handle errors during the Vipps checkout process NT-2024-09-07
    function handleCheckoutError(errorMessage) {
      console.error(errorMessage);
      jQuery("body").css("cursor", "default");
      jQuery('.vipps_checkout_button.button').css("cursor", "default");
      jQuery('.vipps_checkout_startdiv').hide();
      jQuery("body").removeClass('processing');
      jQuery('#vippscheckouterror').hide();
      jQuery('#vippscheckoutframe').html('<div class="woocommerce-error">' + errorMessage + '</div>');
      initiating = false;
    }

    function listenToFrame() {
        if (listening) return;
        var iframe = jQuery('#vippscheckoutframe iframe');
        if (iframe.length < 1) return;
        var src = iframe.attr('src');
        if (!src) return;
        listening = true;
        var origin = new URL(src).origin;
        window.addEventListener( 'message',
                // Only frameHeight in pixels are sent, but it is sent whenever the frame changes (so, including when address etc is set). 
                // So poll when this happens. IOK 2021-08-25
                function (e) {
                    if (e.origin != origin) return;
                    jQuery("body").removeClass('processing');
                    if (!polling && !pollingdone) pollSessionStatus();
                    },
                    false
                );
    }

    function pollSessionStatus () {
        if (polling) return;
        polling=true;

        if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutPollingStart');
        }

        jQuery.ajax(VippsConfig['vippsajaxurl'],
                {cache:false,
                    timeout: 0,
                    dataType:'json',
                    data: { 'action': 'vipps_checkout_poll_session', 'vipps_checkout_sec' : jQuery('#vipps_checkout_sec').val(), 'orderid' : jQuery('#vippsorderid').val() },
                    error: function (xhr, statustext, error) {
                        // This may happen as a result of a race condition where the user is sent to Vipps
                        //  when the "poll" call still hasn't returned. In this case this error doesn't actually matter, 
                        // It may also be a temporary error, so we do not interrupt polling or notify the user. Just log.
                        // IOK 2022-04-06
                        if (error == 'timeout')  {
                            console.log('Timeout polling session data hos Vipps');
                        } else {
                            console.log('Error polling session data hos Vipps - this may be temporary or because the user has moved on: ' + statustext + " error: " + error);
                        }
                    },
                    'complete': function (xhr, statustext, error)  {
                        polling = false;
                        if (!pollingdone) {
                            // In case of race conditions, poll at least every 5 seconds 
                            setTimeout(pollSessionStatus, 10000);
                        }
                    },
                    method: 'POST', 
                    'success': function (result,statustext, xhr) {
                        console.log('Ok: ' + result['success'] + ' message ' + result['data']['msg'] + ' url ' + result['data']['url']);
                        if (result['data']['msg'] == 'EXPIRED') {
                            jQuery('#vippscheckoutexpired').show();
                            jQuery('#vippscheckoutframe').html('<div style="display:none">Session expired</div>');
                            pollingdone=true;
                            return;
                        }
                        if (result['data']['msg'] == 'ERROR' || result['data']['msg'] == 'FAILED') {
                            jQuery('#vippscheckouterror').show();
                            jQuery('#vippscheckoutframe').html('<div style="display:none">Error occured in backend</div>');
                            pollingdone=true;
                            return;
                        }
                        if (result['data']['url']) {
                            pollingdone = 1;
                            window.location.replace(result['data']['url']);
                        }
                    },
                });
    }



    function initWhenVisible() {
      if (typeof document.visibilityState == 'undefined') return;
      if (initiating) return;
      if (listening) return;
      if (document.visibilityState == 'visible') {
         jQuery("body").addClass('processing');
          // Give other scripts a chance to run first
         setTimeout(initVippsCheckout, 100);
      } else {
         console.log("Not visible - not starting Vipps MobilePay Checkout");
      }
    }

    console.log("Vipps MobilePay Checkout Initialized version 111");
    
    listenToFrame(); // Start now if we have an iframe. This will also start the polling.
    initWhenVisible(); // Or start the session maybe
});
