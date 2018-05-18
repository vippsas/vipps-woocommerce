<?php

// Delegate class for talking to Vipps
require_once(dirname(__FILE__) . "/VippsApi.class.php");

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";
    public $apiurl = 'https://apitest.vipps.no';
    public $api = null;
    public $supports = null;


    public function __construct() {
        $this->method_description = __('Offer Vipps as a payment method', 'vipps');
        $this->method_title = __('Vipps','vipps');
        $this->title = __('Vipps','vipps');
        $this->icon = plugins_url('img/vipps_logo_rgb.png',__FILE__);
        $this->init_form_fields();
        $this->init_settings();
        $this->api = new VippsApi($this);

        $this->supports = array('products','refunds');

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action( 'woocommerce_order_status_on-hold_to_processing', array($this, 'maybe_capture_payment'));
        add_action( 'woocommerce_order_status_on-hold_to_completed', array($this, 'maybe_capture_payment'));
        add_action( 'woocommerce_order_status_processing_to_completed', array($this, 'maybe_capture_payment'));

        add_action( 'woocommerce_order_status_on-hold_to_cancelled', array($this, 'maybe_cancel_payment'));
        add_action( 'woocommerce_order_status_on-hold_to_refunded', array($this, 'maybe_cancel_payment'));

        add_action( 'woocommerce_order_status_processing_to_cancelled', array($this, 'maybe_refund_payment'));
        add_action( 'woocommerce_order_status_completed_to_cancelled', array($this, 'maybe_refund_payment'));
        add_action( 'woocommerce_order_status_processing_to_refunded', array($this, 'maybe_refund_payment'));
        add_action( 'woocommerce_order_status_completed_to_refunded', array($this, 'maybe_refund_payment'));
    }

    // Create callback urls' using WC's callback API in a way that works with Vipps callbacks and both pretty and not so pretty urls.
    private function make_callback_urls($forwhat) {
        // HTTPS required. IOK 2018-05-18
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            return set_url_scheme(home_url(),'https') . "/?wc-api=$forwhat&callback=";
        } else {
            return set_url_scheme(home_url(),'https') . "/wc-api/$forwhat?callback=";
        }
    }
    // The main payment callback
    public function payment_callback_url () {
        return $this->make_callback_urls('wc_gateway_vipps');
    }
    // Callback for the login api
    public function login_callback_url () {
        return $this->make_callback_urls('vipps_login');
    }
    // Callback for the consetn removal callback. Must use template redirect directly, because wc-api doesn't handle DELETE.
    // IOK 2018-05-18
    public function consent_removal_callback_url () {
        if ( !get_option('permalink_structure')) {
            return set_url_scheme(home_url(),'https') . "/?vipps-consent-removal&callback=";
        } else {
            return set_url_scheme(home_url(),'https') . "/vipps-consent-removal/?callback=";
        }
    }


    public function maybe_cancel_payment($orderid) {
        $order = new WC_Order( $orderid );
        $ok = 0;

        // Now first check to see if we have captured anything, and if we have, refund it. IOK 2018-05-07
        $captured = $order->get_meta('_vipps_captured');
        if ($captured) {
            return $this->maybe_refund_payment($orderid);
        }

        try {
            $ok = $this->cancel_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if (!$ok) {
            // It's just a captured payment, so we'll ignore the illegal status change. IOK 2017-05-07
            $msg = __("Could not cancel Vipps payment", 'vipps');
            $this->adminerr($msg);
            $order->save();
            global $Vipps;
            $Vipps->store_admin_notices();
        }
    }

    // Handle the transition from anything to "refund"
    public function maybe_refund_payment($orderid) {
        $order = new WC_Order( $orderid );
        $ok = 0;

        // Now first check to see if we have captured anything, and if we haven't, just cancel order IOK 2018-05-07
        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');
        if (!$captured) {
            return $this->maybe_cancel_payment($orderid);
        }
        if ($to_refund == 0) return true;

        try {
            $ok = $this->refund_payment($order,$to_refund,'exact');
        } catch (TemporaryVippsAPIException $e) {
            $this->adminerr(__('Temporary error when refunding payment through Vipps - ensure order is refunded manually, or reset the order to "Processing" and try again', 'vipps'));
            $this->adminerr($e->getMessage());
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        } catch (Exception $e) {
            $order->add_order_note(__("Error when refunding payment through Vipps:", 'vipps') . ' ' . $e->getMessage());
            $order->save();
            $this->adminerr($e->getMessage());
        }
        if (!$ok) {
            $msg = __('Could not refund payment through Vipps - ensure the refund is handled manually!', 'vipps');
            $this->adminerr($msg);
            $order->add_order_note($msg);
            // Unfortunately, we can't 'undo' the refund when the user manually sets the status to "Refunded" so we must 
            // allow the state change here if that happens.
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        }
    }


    // This is the Woocommerce refund api called by the "Refund" actions. IOK 2018-05-11
    public function process_refund($orderid,$amount=null,$reason='') {
        $order = new WC_Order( $orderid );

        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');
        if (!$captured) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the payment has not been captured yet.", 'vipps'));
        }
        if ($amount*100 > $to_refund) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the refund amount is too large.", 'vipps'));
        }
        $ok = 0;
        try {
            $ok = $this->refund_payment($order,$amount);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not refund Vipps payment', 'vipps') . ' ' .$e->getMessage(),'error');
            return new WP_Error('Vipps',__('Vipps is temporarily unavailable.','vipps') . ' ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = __('Could not refund Vipps payment','vipps') . ' ' . $e->getMessage();
            $order->add_order_note($msg);
            $this->log($msg,'error');
            return new WP_Error('Vipps',$msg);
        }

        if ($ok) {
            $order->add_order_note($amount . ' ' . 'NOK' . ' ' . __(" refunded through Vipps:",'vipps') . ' ' . $reason);
        } 
        return $ok;
    }


    public function init_form_fields() { 
        $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => __( 'Enable Vipps', 'vipps' ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                    ),
                'orderprefix' => array(
                    'title' => __('Order-id Prefix', 'vipps'),
                    'label'       => __( 'Order-id Prefix', 'vipps' ),
                    'type'        => 'string',
                    'description' => __('An alphanumeric textstring to use as a prefix on orders from your shop, to avoid duplicate order-ids','vipps'),
                    'default'     => 'Woo',
                    ),
                'merchantSerialNumber' => array(
                    'title' => __('Merchant Serial Number', 'vipps'),
                    'label'       => __( 'Merchant Serial Number', 'vipps' ),
                    'type'        => 'number',
                    'description' => __('Your merchant serial number from the Developer Portal - Applications tab, Saleunit Serial Number','vipps'),
                    'default'     => '',
                    ),
                'clientId' => array(
                        'title' => __('Client Id', 'vipps'),
                        'label'       => __( 'Client Id', 'vipps' ),
                        'type'        => 'password',
                        'description' => __('Client Id from Developer Portal - Applications tab, "View Secret"','vipps'),
                        'default'     => '',
                        ),
                'secret' => array(
                        'title' => __('Application Secret', 'vipps'),
                        'label'       => __( 'Application Secret', 'vipps' ),
                        'type'        => 'password',
                        'description' => __('Application secret from Developer Portal - Applications tab, "View Secret"','vipps'),
                        'default'     => '',
                        ),
                'Ocp_Apim_Key_AccessToken' => array(
                        'title' => __('Subscription key for Access Token', 'vipps'),
                        'label'       => __( 'Subscription key for Access Token', 'vipps' ),
                        'type'        => 'password',
                        'description' => __('The Primary key for the Access Token subscription from your profile on the developer portal','vipps'),
                        'default'     => '',
                        ),
                'Ocp_Apim_Key_eCommerce' => array(
                        'title' => __('Subscription key for eCommerce', 'vipps'),
                        'label'       => __( 'Subscription key for eCommerce', 'vipps' ),
                        'type'        => 'password',
                        'description' => __('The Primary key for the eCommerce API subscription from your profile on the developer portal','vipps'),
                        'default'     => '',
                        ),

                'title' => array(
                        'title' => __( 'Title', 'woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default' => __('Vipps','vipps')
                        ),
                'description' => array(
                        'title' => __( 'Description', 'woocommerce' ),
                        'type' => 'textarea',
                        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                        'default' => __("Pay with Vipps", 'vipps')
                        )
                    );
    }

    // IOK 2018-04-18 utilities for the 'admin notices' interface.
    private function adminwarn($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-warning is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminerr($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-error is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminnotify($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-info is-dismissible'><p>$what</p></div>";
                });
    }

    public function is_valid_for_use() {
        $currency = get_woocommerce_currency(); 
        if ($currency != 'NOK') {
            return false;
        }
        return true; 
    }

    // IOK 2018-04-20 for this plugin we will simply return true and add the 'Klarna' form to the receipt apage
    public function process_payment ($order_id) {
        global $woocommerce, $Vipps;
        if (!$order_id) return false;

        // Do a quick check for correct setup first - this is the most critical point IOK 2018-05-11 
        try {
            $at = $this->api->get_access_token();
        } catch (Exception $e) {
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
            return false;
        }

        // From the request, get either    [billing_phone] =>  or [vipps phone]
        $phone = '';
        if (isset($_POST['vippsphone'])) {
            $phone = trim($_POST['vippsphone']);
        }
        if (!$phone && isset($_POST['billing_phone'])) {
            $phone = trim($_POST['billing_phone']);
        }
        // No longer the case for V2
        if (false && !$phone) {
            wc_add_notice(__('You need to enter your phone number to pay with Vipps','vipps') ,'error');
            return false;
        }

        $order = new WC_Order($order_id);
        $content = null;

        // Vipps-terminal-page return url to poll/await return
        $returnurl= $Vipps->payment_return_url();

        try {
            // The requestid is actually for replaying the request, but I get 402 if I retry with the same Orderid.
            // Still, if we want to handle transient error conditions, then that needs to be extended here (timeouts, etc)
            $requestid = $order->get_order_key();
            $content =  $this->api->initiate_payment($phone,$order,$returnurl,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not initiate Vipps payment','vipps') . ' ' . $e->getMessage(), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is temporarily unavailable. Please wait or  choose another method.','vipps'),'error');
            return false;
        } catch (Exception $e) {
            $this->log(__('Could not initiate Vipps payment','vipps') . ' ' . $e->getMessage(), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
            return false;
        }

        $url = $content['url'];
        $vippstamp = time();

        // Ensure we only check the status by ajax of our own orders. IOK 2018-05-03
        $sessionorders= WC()->session->get('_vipps_session_orders');
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_session_orders',$sessionorders);
        WC()->session->set('_vipps_pending_order',$order_id); // Send information to the 'please confirm' screen IOK 2018-04-24

        $order = new WC_Order( $order_id );
        $order->set_transaction_id($transactionid);
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_status','INITIATE'); // INITIATE right now
        $order->add_order_note(__('Vipps payment initiated','vipps'));
        $order->add_order_note(__('Awaiting Vipps payment confirmation','vipps'));
        $order->save();

        // Create a signal file that we can check without calling wordpress to see if our result is in IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order);
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }

        // Then empty the cart; we'll ressurect it if we can and have to, so store it in session indexed by order number. IOK 2018-04-24
        // We really don't want any errors here for any reason, if we fail that's ok. IOK 2018-05-07
        try {
            $Vipps->save_cart($order);
        } catch (Exception $e) {
        }
        $woocommerce->cart->empty_cart(true);


        // This will send us to a receipt page where we will do the actual work. IOK 2018-04-20
        return array('result'=>'success','redirect'=>$url);
    }

    // This tries to capture a Vipps payment, and resets the status to 'on-hold' if it fails.  IOK 2018-05-07
    public function maybe_capture_payment($orderid) {
        $order = new WC_Order( $orderid );
        $ok = 0;
        try {
            $ok = $this->capture_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if (!$ok) {
            $msg = __("Could not capture Vipps payment - status set to", 'vipps') . ' ' . __('on-hold','woocommerce');
            $this->adminerr($msg);
            $order->set_status('on-hold',$msg);
            $order->save();
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        }
    }


    // Capture (possibly partially) the order. Only full capture really supported by plugin at this point. IOK 2018-05-07
    public function capture_payment($order,$amount=0) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to capture payment on order not made by Vipps:','vipps'). ' ' . $order->get_id(), 'error');
            $this->adminerr(__('Cannot capture payment on orders not made by Vipps','vipps'));
            return false;
        }

        // If we already have captured everything, then we are ok! IOK 2017-05-07
        $captured = $order->get_meta('_vipps_captured');
        if ($captured) {
            $remaining = $order->get_meta('_vipps_capture_remaining');
            if (!$remaining) {
                $order->add_order_note(__('Payment already captured','vipps'));
                return true;
            }
        }

        // Each time we succeed, we'll increase the 'capture' transaction id so we don't just capture the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it - and also, we don't really support partial capture yet.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_capture_transid'));
        try {
            $requestid = $requestidnr . ":" . $order->get_order_key();
            $content =  $this->api->capture_payment($order,$requestid,$amount);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not capture Vipps payment', 'vipps') . ' ' .$e->getMessage(),'error');
            $this->adminerr(__('Vipps is temporarily unavailable.','vipps') . ' ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $msg = __('Could not capture Vipps payment','vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }
        // Store amount captured, amount refunded etc and increase the capture-key if there is more to capture 
        // status 'captured'
        $transactionInfo = $content['transactionInfo'];
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_capture_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_capture_transid', $requestidnr+1);
        $order->add_order_note(__('Vipps Payment captured:','vipps') . ' ' .  sprintf("%0.2f",$transactionSummary['capturedAmount']/100) . ' ' . 'NOK');
        $order->save();

        return true;
    }

    // Cancel (only completely) a reserved but not yet captured order IOK 2018-05-07
    public function cancel_payment($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to cancel payment on order not made by Vipps:','vipps'). ' ' .$order->get_id(), 'error');
            $this->adminerr(__('Cannot cancel payment on orders not made by Vipps','vipps'));
            return false;
        }
        // If we have captured the order, we can't cancel it. IOK 2018-05-07
        $captured = $order->get_meta('_vipps_captured');
        if ($captured) {
            $msg = __('Cannot cancel a captured Vipps transaction - use refund instead', 'vipps');
            $this->adminerr($msg);
            return false;
        }
        // We'll use the same transaction id for all cancel jobs, as we can only do it completely. IOK 2018-05-07
        try {
            $requestid = "";
            $content =  $this->api->cancel_payment($order,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not cancel Vipps payment', 'vipps') . ' ' .$e->getMessage(),'error');
            $this->adminerr(__('Vipps is temporarily unavailable.','vipps') . ' ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $msg = __('Could not cancel Vipps payment','vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }
        // Store amount captured, amount refunded etc and increase the capture-key if there is more to capture 
        $transactionInfo = $content['transactionInfo'];
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_cancel_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        $order->add_order_note(__('Vipps Payment cancelled:','vipps'));
        $order->save();

        // Update status from Vipps, but ignore errors IO 2018-05-07
        try {
            $this->get_vipps_order_status($order,false);
        } catch (Exception $e)  {
        }
        return true;
    }

    // Refund (possibly partially) the captured order. IOK 2018-05-07
    // The caller must handle the errors.
    public function refund_payment($order,$amount=0,$cents=false) {

        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $msg = __('Trying to refund payment on order not made by Vipps:','vipps') . ' ' . $order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }
        // If we haven't captured anything, we can't refund IOK 2017-05-07
        $captured = $order->get_meta('_vipps_captured');
        if (!$captured) {
            $msg = __('Trying to refund payment on Vipps payment not captured:','vipps'). ' ' .$order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }

        // Each time we succeed, we'll increase the 'refund' transaction id so we don't just refund the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_refund_transid'));
        $requestid = $requestidnr . ":" . $order->get_order_key();
        $content =  $this->api->refund_payment($order,$requestid,$amount,$cents);
        // Store amount captured, amount refunded etc and increase the refund-key if there is more to capture 
        $transactionInfo = $content['transaction']; // NB! Completely different name here as compared to the other calls. IOK 2018-05-11
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_refund_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_refund_transid', $requestidnr+1);
        $order->add_order_note(__('Vipps payment refunded:','vipps') . ' ' .  sprintf("%0.2f",$transactionSummary['refundedAmount']/100) . ' ' . 'NOK');
        $order->save();
        return true;
    }

    // Generate a one-time password for certain callbacks, with some backwards compatibility for PHP 5.6
    private function generate_authtoken($length=32) {
        $token="";
        if (function_exists('random_bytes')) {
            $token = bin2hex(random_bytes($length));
        } elseif  (function_exists('openssl_random_pseudo_bytes')) {
            $token = bin2hex(openssl_random_pseudo_bytes($length));
        } elseif (function_exists('mcrypt_create_iv')) {
            // These aren't "secure" but they are probably ok for this purpose. IOK 2018-05-18
            $token = bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
        } else {
            // Final fallback
            $token = bin2hex(md5(microtime() . ":" . mt_rand()));
        }

        return $token;
    }

    // Send a login-request to Vipps
    public function login_request() {
        global $Vipps;
        // Each time we succeed, we'll increase the 'capture' transaction id so we don't just capture the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it - and also, we don't really support partial capture yet.) IOK 2018-05-07
        $authtoken = $this->generate_authtoken();

        $requestidnr = intval(WC()->session->get('_vipps_login_requestid'));
        $returnurl = $Vipps->login_return_url();
        try {
            $requestid = $requestidnr . ":" . WC()->session->get_customer_id();
            $content =  $this->api->login_request($returnurl,$authtoken,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not login with Vipps', 'vipps') . ' ' .$e->getMessage(),'error');
            throw new TemporaryVippsApiException( __('Vipps is temporarily unavailable.','vipps'));
            return false;
        } catch (Exception $e) {
            $msg = __('Could not login with Vipps:','vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            throw new VippsApiException( __('Vipps is temporarily unavailable.','vipps'));
            return false;
        }

        set_transient('_vipps_loginrequests_' . $content['requestId'], array('authToken'=>$authtoken, 'when'=>time()), 10*60);
        WC()->session->set('_vipps_login_requestid', $requestidnr+1);
        return $content;
    }

    // Check status of order at Vipps, in case the callback has been delayed or failed.   
    // Should only be called if in status 'pending'; it will modify the order when status changes.
    public function callback_check_order_status($order) {
        $oldstatus = $order->get_status();
        $newstatus = $oldstatus;
        $vippsstatus = $this->get_vipps_order_status($order,'iscallback');

        switch ($vippsstatus) { 
            case 'INITIATE':
            case 'REGISTER':
            case 'REGISTERED':
                $newstatus = 'pending';
                break;
            case 'RESERVE':
            case 'RESERVED':
                $newstatus = 'on-hold';
                break;
            case 'SALE':
                $newstatus = 'processing'; 
                break;
            case 'CANCEL':
            case 'VOID':
            case 'AUTOREVERSAL':
            case 'AUTOCANCEL':
            case 'FAILED':
            case 'REJECTED':
                $newstatus = 'cancelled'; 
                break;
        }
        if ($set && $oldstatus != $newstatus) {
            switch ($newstatus) {
                case 'on-hold':
                    wc_reduce_stock_levels($order->get_id());
                    $order->update_status('on-hold', __( 'Payment authorized at Vipps', 'vipps' ));
                    break;
                case 'processing':
                    wc_reduce_stock_levels($order->get_id());
                    $order->update_status('processing', __( 'Payment captured at Vipps', 'vipps' ));
                    break;
                case 'cancelled':
                    $order->update_status('cancelled', __('Order failed or rejected at Vipps', 'vipps'));
                    break;
            }
            $order->save();
        }

        return $newstatus;
    }

    // Get the order status as defined by Vipps. If 'iscallback' is true, set timestamps etc as if this was a Vipps callback. IOK 2018-05-04 
    public function get_vipps_order_status($order, $iscallback=0) {
        $vippsorderid = $order->get_meta('_vipps_orderid');
        if (!$vippsorderid) return null;
        try { 
            $statusdata = $this->api->order_status($order);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not get Vipps order status', 'vipps') . ' ' .$e->getMessage(),'error');
            if (!$iscallback) $this->adminerr(__('Vipps is temporarily unavailable.','vipps') . ' ' . $e->getMessage());
            return null;
        } catch (VippsAPIException $e) {
            $msg = __('Could not get Vipps order status','vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            if (intval($e->responsecode) == 402) {
                $this->log(__('Order does not exist at Vipps - cancelling','vipps'));
                return 'CANCEL'; 
            }
            if (!$iscallback) $this->adminerr($msg);
        } catch (Exception $e) {
            $msg = __('Could not get Vipps order status','vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            if (!$iscallback) $this->adminerr($msg);
            return null;
        }
        if (!$statusdata) return null;

        $transaction = @$statusdata['transactionInfo'];
        if (!$transaction) return null;
        $vippsstatus = $transaction['status'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount= $transaction['amount'];

        if ($iscallback) {
            $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        }
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus); // should be RESERVED or REJECTED mostly, could be FAILED etc. IOK 2018-04-24
        $order->save();

        return $vippsstatus;
    }

    // Handle the callback from Vipps.
    public function handle_callback($result) {
        global $Vipps;


        // These can have a prefix added, which may have changed, so we'll use our own search
        // to retrieve the order IOK 2018-05-03
        $vippsorderid = $result['orderId'];
        $orderid = $Vipps->getOrderIdByVippsOrderId($vippsorderid);

        $order = new WC_Order($orderid);
        if (!$order) {
            $this->log(__("Vipps callback for unknown order",'vipps') . " " .  $orderid);
            return false;
        }

        $merchant= $result['merchantSerialNumber'];
        $me = $this->get_option('merchantSerialNumber');
        if ($me != $merchant) {
            $this->log(__("Vipps callback with wrong merchantSerialNumber - might be forged",'vipps') . " " .  $orderid);
            return false;
        }

        $transaction = @$result['transactionInfo'];
        if (!$transaction) {
            $this->log(__("Anomalous callback from vipps, handle errors and clean up",'vipps'),'error');
            return false;
        }
        $transactionid = $transaction['transactionId'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount = $transaction['amount'];
        $vippsstatus = $transaction['status'];

        // Create a signal file (if possible) so the confirm screen knows to check status IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order,'ok');
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }
        $order->add_order_note(__('Vipps callback received','vipps'));

        $errorInfo = @$result['errorInfo'];
        if ($errorInfo) {
            $this->log(__("Error message in callback from Vipps for order",'vipps') . ' ' . $orderid . ' ' . $errorInfo['errorMessage'],'error');
            $order->add_order_note($errorInfo['errorMessage']);
        }

        $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus); 

        if ($vippsstatus == 'RESERVED' || $vippsstatus == 'RESERVE') { // Apparenlty, the API uses *both* ! IOK 2018-05-03
            wc_reduce_stock_levels($order->get_id());
            $order->update_status('on-hold', __( 'Payment authorized at Vipps', 'vipps' ));
        } else {
            $order->update_status('cancelled', __( 'Payment cancelled at Vipps', 'vipps' ));
        }
        $order->save();
    }


    public function admin_options() {
        ?>
            <h2><?php _e('Vipps','vipps'); ?> <img style="float:right;max-height:40px" alt="<?php _e($this->title,'vipps'); ?>" src="<?php echo $this->icon; ?>"></h2>
            <?php $this->display_errors(); ?>
            <?php if (!$this->is_valid_for_use()): ?>
            <div class="inline error">
            <p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>:
            <?php _e( 'Vipps does not support your currency.', 'vipps' ); ?>
            </p>
            </div>
            <?php endif; ?>
            <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            </table> <?php
    }

    // Validate/mangle input fields 
    function validate_text_field ($key, $value) {
        if ($key != 'orderprefix') return parent::validate_text_field($key,$value);
        $value = preg_replace('![^a-zA-Z0-9]!','',$value);
        return $value;
    }
    function validate_checkbox_field($key,$value) {
        if ($key != 'enabled') return parent::validate_text_field($key,$value);
        if ($this->is_valid_for_use()) return 'yes';
        return 'no';
    }

    function process_admin_options () {
        // Handle options updates
        $saved = parent::process_admin_options();

        $at = $this->get_option('Ocp_Apim_Key_AccessToken');
        $s = $this->get_option('secret');
        $c = $this->get_option('clientId');
        if ($at && $s && $c) {
            try {
                $token = $this->api->get_access_token('force');
                $this->adminnotify(__("Connection to Vipps OK", 'vipps'));
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $this->adminerr(__("Could not connect to Vipps", 'vipps') . ": $msg");
            }
        }

        return $saved;
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source','Vipps Woo Gateway');
        $logger->log($type,$what,$context);
    }

    public function payment_fields() {
        $fields = WC()->checkout->checkout_fields;
        // Use Billing Phone if it is required, otherwise ask for a phone IOK 2018-04-24
        // For v2 of the api, just let Vipps ask for then umber
        return;
        if (isset($fields['billing']['billing_phone']) && $fields['billing']['billing_phone']['required']) {
        } else {
            print "<input type=text name='vippsphone' value='' placeholder='ditt telefonnr'>";
        }
    }
    public function validate_fields() {
        return true;
    }


}
