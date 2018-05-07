<?php

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";
    public $api = 'https://apitest.vipps.no';

    public function __construct() {
        $this->method_description = __('Offer Vipps as a payment method', 'vipps');
        $this->method_title = __('Vipps','vipps');
        $this->title = __('Vipps','vipps');
        $this->icon = plugins_url('img/vipps_logo_rgb.png',__FILE__);
        $this->init_form_fields();
        $this->init_settings();
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'maybe_capture_payment' ) );
        add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'maybe_capture_payment' ) );
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
        // From the request, get either    [billing_phone] =>  or [vipps phone]

        $at = $this->get_access_token();
        if (!$at) {
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
            return false;
        }
        $phone = '';
        if (isset($_POST['vippsphone'])) {
            $phone = trim($_POST['vippsphone']);
        }
        if (!$phone && isset($_POST['billing_phone'])) {
            $phone = trim($_POST['billing_phone']);
        }
        if (!$phone) {
            wc_add_notice(__('You need to enter your phone number to pay with Vipps','vipps') . print_r($_POST,true),'error');
            return false;
        }

        $order = new WC_Order($order_id);
        $res = null;
        try {
            // The requestid is actually for replaying the request, but I get 402 if I retry with the same Orderid.
            // Still, if we want to handle transient error conditions, then that needs to be extended here (timeouts, etc)
            $requestid = $order->get_order_key();
            $res =  $this->api_initiate_payment($phone,$order,$requestid);
        } catch (VippsApiException $e) {
            wc_add_notice($e->getMessage(), 'error');
            return false;
        }

        // This would be an error in the URL or something - or a network outage IOK 2018-04-24
        if (!$res || !$res['response']) {
            $this->log(__('Could not initiate Vipps payment','vipps') . ' ' . __('No response from Vipps', 'vipps'), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
            return false;
        } 

        // Errors. We can't do much recovery, but we can log, which we will do . IOK 2018-04-24
        if ($res['response']>399) {
            if (isset($res['content'])) {
                $content = $res['content'];
                // Sometimes we get one type of error, sometimes another, depending on which layer explodes. IOK 2018-04-24 
                if (isset($content['ResponseInfo'])) {
                    // This seems to be an error in the API layer. The error is in this elements' ResponseMessage
                    $this->log(__('Could not initiate Vipps payment','vipps') . ' ' . $res['response'] . ' ' .  $content['ResponseInfo']['ResponseMessage'], 'error');
                    wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
                    return false;
                } else {
                    // Otherwise, we get a simple array of objects with error messages.  Log them all.
                    $notvippscustomer = 0;
                    foreach($content as $entry) {
                        if (preg_match('!User is not registered with VIPPS!i',$entry['errorMessage'])) {
                            $notvippscustomer = 1;
                        }
                        $this->log(__('Could not initiate Vipps payment','vipps') . ' ' .$res['response'] . ' ' .   $entry['errorMessage'], 'error');
                    }
                    if ($notvippscustomer) {
                        wc_add_notice(__('Your phone number doesn\'t have Vipps! Download the app and register, choose another payment method.','vipps'),'error');
                    } else { 
                        wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
                    }
                    return false;
                }
            } else {
                // No response content at all, so just log the response header
                $this->log(__('Could not initiate Vipps payment','vipps') . ' ' .  $res['headers'][0], 'error');
                wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
                return false;
            }
        }
        // This should not ever happen, so log it and fail
        if (intval($res['response']) != 202) {
            $this->log(__('Unexpected response from Vipps','vipps') . ' ' .  print_r($res,true), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'),'error');
            return false;
        }

        // So here we have a correct response 202 Accepted and so on and so forth! IOK 2018-04-24
        // We need to clean out the cart, store metadata for interfaceing with Vipps (in case the callback doesn't work)
        // and store the order id in session so we can access it on the 'waiting for confirmation' screen. IOK 2018-04-24
        $content = $res['content'];
        $transactioninfo = @$content['transactionInfo'];
        $transactionid = @$transactioninfo['transactionId'];
        $vippsstatus = @$transactioninfo['status'];
        $message = __(@$transactioninfo['message'],'vipps');
        $vippstamp = strtotime(@$transactioninfo['timeStamp']);

        // Ensure we only check the status by ajax of our own orders. IOK 2018-05-03
        $sessionorders= WC()->session->get('_vipps_session_orders');
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_session_orders',$sessionorders);
        WC()->session->set('_vipps_pending_order',$order_id); // Send information to the 'please confirm' screen IOK 2018-04-24

        $order = new WC_Order( $order_id );
        $order->set_transaction_id($transactionid);
        $order->update_meta_data('_vipps_transaction',$transactionid);
        $order->update_meta_data('_vipps_confirm_message',$message);
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_status',$vippsstatus); // INITIATE right now
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

        // Vipps-terminal-page FIXME fetch from settings! IOK 2018-04-23
        $url = '/vipps-betaling/';

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
        }
    }


    // Capture (possibly partially) the order. Only full capture really supported by plugin at this point. IOK 2018-05-07
    public function capture_payment($order,$amount=0) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to capture payment on order not made by Vipps:','vipps'),$order->get_id());
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
            $requestid = $requestidnr . ":" . $order->get_order-key();
            $res =  $this->api_capture_payment($order,$requestid,$amount);
        } catch (VippsApiException $e) {
            $this->adminerr($e->getMessage());
            return false;
        }
        // This would be an error in the URL or something - or a network outage IOK 2018-04-24
        if (!$res || !$res['response']) {
            $msg = __('Could not capture Vipps payment','vipps') . ' ' . __('No response from Vipps', 'vipps');
            $this->adminerr($msg);
            $this->log($msg, 'error');
            $order->add_order_note($msg);
            return false;
        } 
        // Error-handling. This needs more work after testing. IOK 2018-05-07
        if ($res['response']>399) {
            if (isset($res['content'])) {
                $content = $res['content'];
                // Sometimes we get one type of error, sometimes another, depending on which layer explodes. IOK 2018-04-24 
                if (isset($content['ResponseInfo'])) {
                    // This seems to be an error in the API layer. The error is in this elements' ResponseMessage
                    $msg = __('Could not capture Vipps payment','vipps') . ' ' . $res['response'] . ' ' .  $content['ResponseInfo']['ResponseMessage'];
                    $this->log($msg,'error');
                    $this->adminerr($msg);
                    $order->add_order_note($msg);
                    return false;
                } else {
                    // Otherwise, we get a simple array of objects with error messages.  Log them all.
                    $allmsg = '';
                    foreach($content as $entry) {
                        $msg = __('Could not capture Vipps payment','vipps') . ' ' .$res['response'] . ' ' .   $entry['errorMessage'];
                        $allmsg .= $msg . "\n";
                        $this->log($msg, 'error');
                        $this->adminerr($msg);
                    }
                    $order->add_order_note($allmsg);
                    return false;
                }
            } else {
                // No response content at all, so just log the response header
                $msg = __('Could not initiate Vipps payment','vipps') . ' ' .  $res['headers'][0];
                $this->log($msg,'error');
                $this->adminerr($msg);
                return false;
            }
        }
        // Store amount captured, amount refunded etc and increase the capture-key if there is more to capture 
        // status 'captured'
        $content = $res['content'];
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
        }



        return $newstatus;
    }

    // Get the order status as defined by Vipps. If 'iscallback' is true, set timestamps etc as if this was a Vipps callback. IOK 2018-05-04 
    public function get_vipps_order_status($order, $iscallback=0) {
        $vippsorderid = $order->get_meta('_vipps_orderid');
        if (!$vippsorderid) return null;
        $statusdata = $this->api_order_status($order);
        if (!$statusdata) return null;
        // Errors. The response of an 500 is quite different than for a 40x. I0K 2018-05-04
        if ($statusdata['response']>399) {
            $content = $statusdata['content'];
            if (isset($content['message'])) {
                throw new VippsAPIException(__("Error getting order status: ",'vipps') . $statusdata['response'] . " " . $content['message']);
            } else {
                $msg = __("Error getting order status: ",'vipps') . $statusdata['response'];
                foreach($content as $entry) {
                    if (isset($entry['errorMessage'])) {
                        $msg .= ' ' . $entry['errorMessage']; 
                    }
                }
                throw new VippsAPIException($msg);
            }
        }


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

        $this->log("We are in the callback" . print_r($result,true), 'debug');

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

        $ordertransid = $order->get_meta('_vipps_transaction');
        if ($ordertransid != $transactionid) {
            $this->log(__("Vipps callback with wrong transaction id for order",'vipps'). " " . $orderid . ": " . $transactionid . ': ' . $ordertransid ,'error');
            return false;
        }
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
                $token = $this->get_access_token('force');
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

    // Get an App access token if neccesary. Returns this or throws an error. IOK 2018-04-18
    private function get_access_token($force=0) {
        // First, get a stored 
        $stored = get_transient('_vipps_app_token');
        if (!$force && $stored && $stored['expires_on'] > time()) {
            return $stored['access_token'];
        }
        $fresh = $this->api_get_access_token();

        // Nothing at all? Throw an error IOK 2018-04-18
        if (!$fresh || !isset($fresh['response'])) {
            throw new VippsAPIException(__("Could not connect to Vipps API",'vipps')); 
        }

        // Else if we get a response at all, it will have the access token, so store it and return IOK 2018-04-18
        if ($fresh['response'] == 200) {
            $resp = $fresh['content'];
            $at = $resp['access_token'];
            $expire = $resp['expires_in']/2;
            set_transient('_vipps_app_token',$resp,$expire);
            return $at;
        }
        // If we got an error message, throw that IOK 2018-04-18
        if ($fresh['content'] && isset($fresh['content']['error'])) {
            throw new VippsAPIException(__("Could not get access token from Vipps API",'vipps') . ": " . __($fresh['content']['error'],'vipps')); 
            error_log("Vipps: " . $fresh['content']['error'] . " " . $fresh['content']['error_description']);
        } 

        // No message, so return the first header (500, 411 etc) IOK 2018-04-18
        throw new VippsAPIException(__("Could not get access token from Vipps API",'vipps') . ": " . __($fresh['headers'][0],'vipps')); 
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function api_get_access_token() { 
        $clientid=$this->get_option('clientId');
        $secret=$this->get_option('secret');
        $at = $this->get_option('Ocp_Apim_Key_AccessToken');
        $server=$this->api;

        $url = $server . '/accessToken/get';
        return $this->http_call($url,array(),'POST',array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$at),'url');
    }

    private function api_initiate_payment($phone,$order,$requestid=1) {
        $server = $this->api;
        $url = $server . '/Ecomm/v1/payments';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        $prefix = $this->get_option('orderprefix');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'));
        }
        if (!$merch) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'));
        }

        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        $vippsorderid =  $prefix.($order->get_id());
        $order->update_meta_data('_vipps_prefix',$prefix);
        $order->update_meta_data('_vipps_orderid', $vippsorderid);
        $order->save();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        // HTTPS is required. IOK 2018-04-24
        $callback = set_url_scheme(home_url(),'https') . '/wc-api/wc_gateway_vipps';
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            $callBack = set_url_scheme(home_url(),'https') . '/?wc-api=wc_gateway_vipps';
        }

        $transaction = array();
        $transaction['orderId'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($order->get_total() * 100); 
        $transaction['transactionText'] = __('Confirm your order from','vipps') . ' ' . home_url(); 
        $transaction['timeStamp'] = $date;


        $data = array();
        $data['customerInfo'] = array('mobileNumber' => $phone);
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch, 'callBack'=>$callback); 
        $data['transaction'] = $transaction;

        $res = $this->http_call($url,$data,'POST',$headers,'json'); 
        return $res;
    }

    private function api_order_status($order) {
        $server = $this->api;

        $merch = $this->get_option('merchantSerialNumber');
        $vippsorderid = $order->get_meta('_vipps_orderid');

        $url = $server . '/Ecomm/v1/payments/'.$vippsorderid.'/serialNumber/'.$merch.'/status';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $prefix = $this->get_option('orderprefix');
        if (!$subkey) {
            $this->log(__('Could not get order details from Vipps - no subscription key','vipps'));
            return null;
        }
        if (!$merch) {
            $this->log(__('Could not get order details from Vipps - no merchant serial number','vipps'));
            return null;
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $data = array();
        $res = $this->http_call($url,$data,'GET',$headers);
        return $res;
    }

    // Capture a payment made. Defaults to full capture only. IOK 2018-05-07
    private function api_capture_payment($order,$requestid=1,$amount=0) {
        $server = $this->api;
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $url = $server . '/Ecomm/v1/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'));
        }
        if (!$merch) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','vipps'));
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        $transaction = array();
        $transaction['orderId'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($amount * 100); 
        $transaction['transactionText'] = __('Order capture for order','vipps') . ' ' . $orderid . ' ' . home_url(); 


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($url,$data,'POST',$headers,'json'); 
        $this->log("Capture log " . print_r($res,true));
        return $res;
    }



    // Conventently call Vipps IOK 2018-04-18
    private function http_call($url,$data,$verb='GET',$headers=null,$encoding='url'){
        if (!$headers) $headers=array();
        $date = gmdate('c');
        $data_encoded = '';
        if ($encoding == 'url' || $verb == 'GET') {
            $data_encoded = http_build_query($data);
        } else {
            $data_encoded = json_encode($data);
        }
        $data_len = strlen ($data_encoded);
        $http_response_header = null;
        $sslparams = array();

        // Always verify peer etc IOK 2018-04-18
        if (true) {
            $sslparams['verify_peer'] = false;
            $sslparams['verify_peer_name'] = false;
        }

        $headers['Connection'] = 'close';
        if ($verb=='POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $headers['Content-length'] = $data_len;
            if ($encoding == 'url') {
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
            } else {
                $headers['Content-type'] = 'application/json';
            }
        }
        $headerstring = '';
        $hh = array();
        foreach($headers as $key=>$value) {
            array_push($hh,"$key: $value");
        }
        $headerstring = join("\r\n",$hh);
        $headerstring .= "\r\n";

        $httpparams = array('method'=>$verb,'header'=>$headerstring,'ignore_errors'=>true);
        if ($verb == 'POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $httpparams['content'] = $data_encoded;
        }
        if ($verb == 'GET' && $data_encoded) {
            $url .= "?$data_encoded";
        }
        $params = array('http'=>$httpparams,'ssl'=>$sslparams);

        $context = stream_context_create($params);
        $content = null;


        $contenttext = @file_get_contents($url,false,$context);
        if ($contenttext) {
            $content = json_decode($contenttext,true);
        }
        $response = 0;
        if ($http_response_header && isset($http_response_header[0])) {
            $match = array();
            $ok = preg_match('!^HTTP/... (...) !i',$http_response_header[0],$match);
            if ($ok) {
                $response = 1 * $match[1];
            }
        }
        return array('response'=>$response,'headers'=>$http_response_header,'content'=>$content);
    }

    public function payment_fields() {
        $fields = WC()->checkout->checkout_fields;
        if (isset($fields['billing']['billing_phone']) && $fields['billing']['billing_phone']['required']) {
            // Use Billing Phone if it is required, otherwise ask for a phone IOK 2018-04-24
        } else {
            print "<input type=text name='vippsphone' value='' placeholder='ditt telefonnr'>";
        }
    }
    public function validate_fields() {
        return true;
    }




}
