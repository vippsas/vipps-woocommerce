<?php

// Remember: In template redirect, do no-cache-headers on the is_vipps_checkout case

    // For Vipps Checkout, poll for the result of the current session
add_action('wp_ajax_vipps_checkout_poll_session', 'vipps_ajax_checkout_poll_session');
add_action('wp_ajax_nopriv_vipps_checkout_poll_session', 'vipps_ajax_checkout_poll_session');
add_action('woocommerce_thankyou_vipps', function () {
    WC()->session->set('current_vipps_session', false);
    WC()->session->set('vipps_checkout_current_pending',false);
});

function vipps_ajax_checkout_poll_session () {
    global $Vipps;
    // Check ajax refererer blablaba! FIXME!

    // Fold this into a function that will create a session if missing. This one however is only to get the order info.
    $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
    $order = $current_pending ? wc_get_order($current_pending) : null;

    if ($order && $order->get_status() != 'pending') {
        // Dispatch on order statuses here
        if ($order->get_status() == 'completed') {
            return wp_send_json_success(array('msg'=>'completed', 'url' => $Vipps->gateway()->get_return_url($order)));;
        }
        return wp_send_json_success(array('msg'=>'not_pending', 'url'=>''));
        exit();
    }

    $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
    if (!$current_vipps_session) {
        WC()->session->set('current_vipps_session', false);
        return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
    }
    $status = get_vipps_checkout_status($current_vipps_session); 
    $failed = ($status == 'EXPIRED') ||  ($status['sessionState'] == 'PaymentFailed');
    $complete = !$failed && ($status['sessionState'] ==  'PaymentSuccessful');
    $ok   = !$failed && ($complete ||  in_array($status['sessionState'],  array('PaymentInitiated', 'SessionStarted')));

    if ($failed) {
        abandonOrder($order);
        WC()->session->set('current_vipps_session', false);
        return wp_send_json_error(array('msg'=>'FAILED', 'url'=>home_url()));
        exit();
    }

    // Errorhandling! FIXME!
    if (!$ok) {
        error_log("status is ". print_r($status, true));
        WC()->session->set('current_vipps_session', false);
        abandonOrder($order);
        return wp_send_json_error(array('msg'=>'EXPIRED', 'url'=>false));
        exit();
    }
    $change = false;
    if ($ok && isset($status['orderContactInformation']))  {
        $contact = $status['orderContactInformation'];
        $order->set_billing_email($contact['email']);
        $order->set_billing_phone($contact['phoneNumber']);
        $order->set_billing_first_name($contact['firstName']);
        $order->set_billing_last_name($contact['lastName']);
        $change = true;
     }
     if ($ok &&  isset($status['orderShippingAddress']))  {
        $contact = $status['orderShippingAddress'];
        $countrycode =  $Vipps->country_to_code($contact['country']);
        $order->set_shipping_first_name($contact['firstName']);
        $order->set_shipping_last_name($contact['lastName']);
        $order->set_shipping_address_1($contact['streetAddress']);
        $order->set_shipping_address_2("");
        $order->set_shipping_city($contact['region']);
        $order->set_shipping_postcode($contact['postalCode']);
        $order->set_shipping_country($countrycode);

        $order->set_billing_address_1($contact['streetAddress']);
        $order->set_billing_address_2("");
        $order->set_billing_city($contact['region']);
        $order->set_billing_postcode($contact['postalCode']);
        $order->set_billing_country($countrycode);

        $change = true;
    }
    if ($change) $order->save();

    if ($complete) {
      // Order is complete! Yay!
        // THIS WILL BE TRICKY TO GET RIGHT! At least wipe this on the thank you page
        $Vipps->gateway()-> update_vipps_payment_details($order);
        $Vipps->gateway()->payment_complete($order);
        wp_send_json_success(array('msg'=>'completed','url' => $Vipps->gateway()->get_return_url($order)));
        exit();
    }

    if ($ok && $change) {
        wp_send_json_success(array('msg'=>'order_change', 'url'=>''));
        exit();
    }
    if ($ok) {
        wp_send_json_success(array('msg'=>'no_change', 'url'=>''));
        exit();
    }

    error_log("Unknown result polling result " . print_r($status, true));
    wp_send_json_success(array('msg'=>'unknown', 'url'=>''));
}

add_shortcode('vipps_checkout', 'vipps_checkout_shortcode');
function vipps_checkout_shortcode ($atts, $content) {
    global $Vipps;

    $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
    $order = $current_pending ? wc_get_order($current_pending) : null;

    // This isn't correct, but anyway
    if (is_a($order, 'WC_Order') && $order->get_status() == 'completed') {
       wp_redirect($Vipps->gateway()->get_return_url($order), 302);
       exit();
    }

    wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
    if ( WC()->cart->is_empty() ) {
        abandonOrder($order);
        wc_get_template( 'cart/cart-empty.php' );
        return;
    }

    # Check to see if we need a new partial order or session. We only need the total to stay the same; so we are not using the cart hash for instance.
    WC()->cart->calculate_fees();
    WC()->cart->calculate_totals();
    $current_total = WC()->cart->get_cart_total();
    $stored = WC()->session->get('current_cart_total');

    $out = "";
    $out .= "Current pending: $current_pending<br>";
    $out .= "Current: $current_total<br>";
    $out .= "Stored: $stored<br>";
    $out .= "Working..<br>";


    $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
    if (!$current_vipps_session) WC()->session->set('current_vipps_session', false);

    $neworder = false;
    $status = null;
    if ($current_vipps_session) {
        $out .= "We got a sesh<br>";
        $status = get_vipps_checkout_status($current_vipps_session);
        if (empty($status) || $status =='EXPIRED' || $status == 'ERROR') {
            $out .= " And it is bogus '$status'<br>";
            $neworder = true;
        }
    }

    if (!is_a($order, 'WC_Order') or $order->get_status() != 'pending')  {
       $neworder = true;
    }
    if ($current_total != $stored) {
        $neworder = true;
    }
    // ALSO IF VIPPS STATUS IS PRESENT AND VALUE IS EXPIRED!


    if ($neworder) {
        if ($current_pending) {
            WC()->session->set('vipps_checkout_current_pending',0);
            $current_pending = 0;
            abandonOrder($order);
        }
    }
    if (!$current_pending) {
        try {
            $current_vipps_session = null;
            WC()->session->set('current_vipps_session', null);
            $current_pending = $Vipps->gateway()->create_partial_order();
            if ($current_pending) {
                // Check for WP_Error too
                WC()->session->set('vipps_checkout_current_pending', $current_pending);
            }
        } catch (Exception $e) {
           wp_die($e->getMessage());
        }
    }
    WC()->session->set('current_cart_total', $current_total);

    $out .= "Current pending: $current_pending<br>";
    $out .= "Current: $current_total<br>";
    $out .= "Stored: $stored<br>";

    if (!$current_vipps_session && $current_pending) {
        $order = wc_get_order($current_pending);
        $phone = "";
        $authtoken = "";
        $requestid = 1;
        $returnurl = $Vipps->payment_return_url();
        $current_vipps_session = $Vipps->gateway()->api->initiate_checkout($phone,$order,$returnurl,$authtoken,$requestid); 
        if ($current_vipps_session) {
            $order = wc_get_order($current_pending);
            $order->update_meta_data('_vipps_init_timestamp',time());
            $order->update_meta_data('_vipps_status','INITIATE'); // INITIATE right now
            $order->add_order_note(__('Vipps Checkout payment initiated','woo-vipps'));
            $order->add_order_note(__('Customer passed to Vipps Checkout','woo-vipps'));
            $order->save();
            WC()->session->set('current_vipps_session', $current_vipps_session);
            $status = get_vipps_checkout_status($current_vipps_session);
        }

    }


    // Check that these exist etc
    $token = $current_vipps_session['token'];
    $src = $current_vipps_session['checkoutFrontendUrl']; 
    $out .= "<iframe style='width:100%;height: 60rem; border=1px solid black;'  src='$src?token=$token'>iframe!</iframe>";
$out .= "<script>
      window.addEventListener(
        'message',
// only frameHeight in pixels are sent.
        function (e) {
            console.log('got message: %j', e);
            pollSessionStatus();
        },
        false
      );

var pollingdone=false;
var polling=false;
function pollSessionStatus () {
   console.log('polling!');
   if (polling) return;
   polling=true;
   jQuery.ajax(". json_encode(admin_url('admin-ajax.php')) . ",
    {cache:false,
       dataType:'json',
       data: { 'action': 'vipps_checkout_poll_session' },
       error: function (xhr, statustext, error) {
         console.log('Error polling status: ' + statustext + ' : ' + error);
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
         if (result['data']['url']) {
            pollingdone = 1;
            window.location.replace(result['data']['url']);
         }
       },
       'timeout': 4000
     });
}

</script>";

    if ($status) {
           $out .= "<pre>" . print_r($current_vipps_session, true) . "</pre>";
           $out .= "<pre>" . print_r($status, true) . "</pre>";
    }

    return $out;
}

function abandonOrder($order) {
    if (is_a($order, 'WC_Order') && $order->get_status() == 'pending') {
        // Also mark for deletion
        $order->set_status('cancelled', __("Abandonded by customer", 'woo-vipps'), false);
        $order->update_meta_data('_vipps_delendum',1);
        $order->save();
    }
}

function get_vipps_checkout_status($session) {
    global $Vipps;
    if ($session && isset($session['token'])) {
        $data = decode_checkout_token($session['token']);
        $status = $Vipps->gateway()->api->poll_checkout($data['sessionId']);
        // Handle stuff here
        return $status;
    }
}

function decode_checkout_token($token) {
    // Actually use JWT verifier here and verify 
    $head = false;
    $body = false;
    if ($token) {
        // First decode the header, body and signature. IOK 2019-10-14
        @list($headb64,$bodyb64,$cryptob64) = explode('.', $token);


        $headjson = base64urldecode($headb64);
        $bodyjson  = base64urldecode($bodyb64);
        $crypto = base64urldecode($cryptob64);

        $head = @json_decode($headjson, true, 512, JSON_BIGINT_AS_STRING);
        $body = @json_decode($bodyjson, true, 512, JSON_BIGINT_AS_STRING);
        return $body;
    }
    return false;
}

function base64urldecode($input) {
            $remainder = strlen($input) % 4;
                    if ($remainder) {
                                    $padlen = 4 - $remainder;
                                                $input .= str_repeat('=', $padlen);
                                            }
                    return base64_decode(strtr($input, '-_', '+/'));
}



           /*
            * EXPIRED or
Array
(
    [sessionId] => cn_zT3Kh4hdystVC8LoI2A
    [transaction] => Array
        (
            [orderId] => woodigitalt1909
            [amount] => 75,75 NOK
            [transactionText] => Bekreft din bestilling fra https://vdev.digitalt.org
            [timeStamp] => 
        )

    [loginURL] => https://api.vipps.no/access-management-1.0/access/oauth2/auth?response_type=code&nonce=O45IaD65E9Zw3FIdRS4uMQ&state=cn_zT3Kh4hdystVC8LoI2A&code_challenge=PtV66FzJJ4ma2lUdptCvxqRjFQIcMF5ZaVD9PPotom8&code_challenge_method=S256&client_id=c19eabc2-fa91-487f-8d39-633f213bdbc3&scope=openid%20address%20email%20name%20phoneNumber%20api_version_2&redirect_uri=https%3A%2F%2Fapi.vipps.no%2Fcheckout%2Flogin%2Fredirect
    [loginPollingURL] => https://api.vipps.no/checkout/login/cn_zT3Kh4hdystVC8LoI2A
    [paymentURL] => https://api.vipps.no/checkout/payment/cn_zT3Kh4hdystVC8LoI2A
    [fallBackURL] => https://vdev.digitalt.org/vipps-betaling/
    [sessionUpdateURL] => https://api.vipps.no/checkout/session/cn_zT3Kh4hdystVC8LoI2A
    [sessionState] => SessionStarted
)
            
               **/


/*
[sessionState] => SessionStarted
    [selectedAddressType] => Custom
    [orderContactInformation] => Array
        (
            [firstName] => iver
            [lastName] => test
            [phoneNumber] => 98818710
            [email] => iverodin@gmail.com
        )

    [orderShippingAddress] => Array
        (
            [firstName] => iver
            [lastName] => test
            [streetAddress] => Observatorie terrasse 4a
            [postalCode] => 0254
            [region] => Oslo
            [country] => Norge
        )

*/
