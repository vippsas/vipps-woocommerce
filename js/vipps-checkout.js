/*

This file is part of the plugin Checkout with Vipps for WooCommerce
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

    jQuery('.vipps_checkout_button.button').click (function (e) {
      if (initiating) return;
      clearValidations();
      var ok = validateTerms();
      if (ok) {
        initiating=true;
        jQuery("body").css("cursor", "progress");
        jQuery('.vipps_checkout_button.button').each(function () {
           jQuery(this).addClass('disabled');
           jQuery(this).css("cursor", "progress");
        });
        jQuery.ajax(VippsConfig['vippsajaxurl'],
                {   cache:false,
                    timeout: 0,
                    dataType:'json',
                    data: { 'action': 'vipps_checkout_start_session', 'vipps_checkout_sec' : jQuery('#vipps_checkout_sec').val() },
                    method: 'POST', 
                    error: function (xhr, statustext, error) {
                        jQuery("body").css("cursor", "default");
                        jQuery('.vipps_checkout_button.button').css("cursor", "default");
                        jQuery('.vipps_checkout_startdiv').hide();
                        console.log('Error initiating transaction : ' + statustext + ' : ' + error);
                        pollingdone=true;
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
                        if (! result['data']['ok']) {
                            console.log("Error starting Vipps Checkout %j", result);
                            jQuery('#vippscheckouterror').show();
                            return;
                        }
                        if (result['data']['redirect']) {
                            window.location.replace(result['redirect']);
                            return;
                        }
                        if (result['data']['src']) {
                            var iframe = jQuery('<iframe src="' + result['data']['src'] + '" frameBorder=0 style="width:100%;height: 60rem;"></iframe>'); 
                            jQuery('#vippscheckoutframe').append(iframe);
                            listenToFrame();
                            return;
                        }
                    },
                });


      }
    });

    function validateTerms () { 
       var termsbox = jQuery('.input-checkbox[name="terms"]');
       if (termsbox.length == 0) return true;
       if (termsbox.is(':checked')) return true;
       termsbox.closest('.validate-required').addClass('woocommerce-invalid');
       termsbox.closest('.validate-required').addClass('woocommerce-invalid-required-field');
       return false;
    }
    function clearValidations() {
       var termsbox = jQuery('.input-checkbox[name="terms"]');
       termsbox.closest('.validate-required').removeClass('woocommerce-invalid');
       termsbox.closest('.validate-required').removeClass('woocommerce-invalid-required-field');
    }

    jQuery('.input-checkbox[name="terms"]').change(function () {
       clearValidations();
    });

    function listenToFrame() {
        if (listening) return;
        var iframe = jQuery('#vippscheckoutframe iframe');
        if (iframe.length < 1) return;
        var src = iframe.attr('src');
        if (!src) return;
        listening = true;
        var origin = new URL(src).origin;;
        window.addEventListener(
                'message',
                // Only frameHeight in pixels are sent, but it is sent whenever the frame changes (so, including when address etc is set). 
                // So poll when this happens. IOK 2021-08-25
                function (e) {
                if (e.origin != origin) return;
                console.log('got message %j', e); // FIXME debuggin
                if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutIframeMessage', e);
                }
                if (e.data.hasOwnProperty('frameHeight')) {
                    jQuery('#vippscheckoutframe iframe').css('height', e.data.frameHeight + 'px');
                }
                if (!polling && !pollingdone) pollSessionStatus();
                },
                false
                );
    }

    function pollSessionStatus () {
        console.log('polling!');
        if (polling) return;
        polling=true;

        if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutPollingStart');
        }

        jQuery.ajax(VippsConfig['vippsajaxurl'],
                {cache:false,
                    timeout: 0,
                    dataType:'json',
                    data: { 'action': 'vipps_checkout_poll_session', 'vipps_checkout_sec' : jQuery('#vipps_checkout_sec').val() },
                    error: function (xhr, statustext, error) {
                        console.log('Error polling status: ' + statustext + ' : ' + error);
                        pollingdone=true;
                        jQuery('#vippscheckouterror').show();
                        jQuery('#vippscheckoutframe').html('<div style="display:none">Error occured</div>');
                        if (error == 'timeout')  {
                            console.log('Timeout polling session data hos Vipps');
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

    listenToFrame(); // Start now if we have an iframe. This will also start the polling.
    console.log("Vipps Checkout Initialized version 101");
});
