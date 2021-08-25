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
    var origin = new URL(jQuery('#vippscheckoutframe iframe').attr('src')).origin;;
    window.addEventListener(
            'message',
            // only frameHeight in pixels are sent, but it is sent whenever the frame changes (so, including when address etc is set). So poll when this happens. IOK 2021-08-25
            function (e) {
                if (e.origin != origin) return;
                console.log('got message %j', e);
                if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutIframeMessage', e);
                }
                if (e.data.hasOwnProperty('frameHeight')) {
                     jQuery('#vippscheckoutframe iframe').attr('x-height', e.data.frameHeight + 'px');
                     jQuery('#vippscheckoutframe iframe').css('height', e.data.frameHeight + 'px');
                }
                if (!polling && !pollingdone) pollSessionStatus();
            },
            false
            );

    function pollSessionStatus () {
        console.log('polling!');
        if (polling) return;
        polling=true;

        if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
                    wp.hooks.doAction('vippsCheckoutPollingStart');
        }

        jQuery.ajax(VippsConfig['vippsajaxurl'],
                {cache:false,
                    dataType:'json',
                    data: { 'action': 'vipps_checkout_poll_session' },
                    error: function (xhr, statustext, error) {
                        console.log('Error polling status: ' + statustext + ' : ' + error);
                        jQuery('#vippscheckoutframe').html('<p>Error occured!</p>');
                        pollingdone=true;
                        if (error == 'timeout')  {
                            console.log('ouch, timeout');
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
                            jQuery('#vippscheckoutframe').html('<p>Expired!</p>');
                            pollingdone=true;
                            return;
                        }
                        if (result['data']['msg'] == 'ERROR') {
                            jQuery('#vippscheckoutframe').html('<p>Error occured!</p>');
                            pollingdone=true;
                            return;
                        }
                        if (result['data']['url']) {
                            pollingdone = 1;
                            window.location.replace(result['data']['url']);
                        }
                    },
                    'timeout': 4000
                });
    }

    console.log("Vipps Checkout Initialized");
});
