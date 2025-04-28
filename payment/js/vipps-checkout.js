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
    var sessionStarted = false;
    var listening=false;
    var initiating=false;

    // This will hold the Vipps Checkout object
    let VCO = null;

    // Which need to be locked/unlocked before we can modify the session. And the session
    // can only be locked from a screen like this. IOK 2025-04-24
    function unlockSession() {
       if (VCO) {
         try {
          VCO.unlock();
         } catch (error) {
         }
       }
    }
    function lockSession(timeout=0) {
       if (VCO) {
         try {
          VCO.lock();
          if (timeout > 0) {
              setTimeout(unlockSession, timeout);
          }
         } catch (error) {
         }
       }
    }

    // Provide a way for thirdparties to lock the session without exposing internal functions.
    // Using WPs hooks interface here for historical reasons. IOK 2025-04-25
    // Ensure you have enqueued wp-hooks and do (for instance) 
    // wp.hooks.doAction('vippsCheckoutLockSession', 5000);
    if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.addAction('vippsCheckoutLockSession', 'woo-vipps', lockSession);
                    wp.hooks.addAction('vippsCheckoutUnlockSession', 'woo-vipps', unlockSession);
    }

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

    function iframeLoaded() {
        // Safeguard. This would actually mostly affect *second tabs* opened, so we may not want to call it at all. Insted,
        // we may want to see here if the session status has changed, and if not, close the page. IOK 2025-04-16
        // Unfortunately, the session status change thing doesn't happen immediately, so this would need to be with a timeout.
        jQuery("body").removeClass('processing');
    }

    function proceedWithCheckout() {
      // Try to start Vipps Checkout with any session provided.
      function doVippsCheckout() {
         if (!VippsSessionState) return false;
         let args = { 
                     checkoutFrontendUrl: VippsSessionState['checkoutFrontendUrl'].replace(/\/$/, ''),
                     token:  VippsSessionState['token'],
                     iFrameContainerId: "vippscheckoutframe",
                     language: VippsConfig['vippslanguage'],
                     on: {
                         shipping_option_selected: function (data) { pollSessionStatus('shipping_selected', data); },
                         total_amount_changed: function (data) { pollSessionStatus('total_changed', data); },
                         session_status_changed: function (data) {
                             sessionStarted = true; jQuery("body").removeClass('processing');
                             pollSessionStatus('status_changed', data);
                         },
                         shipping_address_changed: function (data) { pollSessionStatus('address_changed', data); } ,
                         customer_information_changed: function (data) { pollSessionStatus('customer_info_changed', data); }
                     }
         };
         let vippsCheckout = VippsCheckout(args);
         VCO = vippsCheckout;

         // When just loaded, with a slight delay ensure the session is unlocked, just in case it was locked in a different tab which
         // was then closed. IOK 2025-04-24
         setTimeout(unlockSession, 5000);

         jQuery("body").css("cursor", "default");
         jQuery('.vipps_checkout_button.button').css("cursor", "default");
         jQuery('.vipps_checkout_startdiv').hide();

         // The iframe should be *present* now, but not loaded, so we get to add an onLoad element. IOK 2025-04-16
         jQuery('#vippscheckoutframe iframe').on("load", iframeLoaded);

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

    function pollSessionStatus (type, pollData) {
        if (polling) return;
        polling=true;
        locking = 0;
        if (!type) type="none";
        if (!pollData) pollData={};

        // For these two, we need to lock the session because VAT calculations can change IOK 2025-04-24        
        if (type =="address_changed" || type=="customer_info_changed") {
          locking=1;
        }

        if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutPollingStart', type, pollData, VCO);
        }

        if (locking) {
           lockSession();
        }

        jQuery.ajax(VippsConfig['vippsajaxurl'],
                {cache:false,
                    timeout: 0,
                    dataType:'json',
                    data: { 'action': 'vipps_checkout_poll_session', 'lock_held' : locking, 'type': type, 'pollData': pollData, 'vipps_checkout_sec' : jQuery('#vipps_checkout_sec').val(), 'orderid' : jQuery('#vippsorderid').val() },
                    error: function (xhr, statustext, error) {
                        setTimeout(unlockSession, 5000); // Allow backend some time to complete actions that would require the lock to be held. IOK 2025-04-24

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
                    },
                    method: 'POST', 
                    'success': function (result,statustext, xhr) {
                        unlockSession();
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

    console.log("Vipps MobilePay Checkout Initialized version 115");
    initWhenVisible(); 
});
