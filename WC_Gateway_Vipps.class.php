<?php

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; // IOK FIXME
    //    public $has_fields = false; // IOK FIXME
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";
    public $api = 'https://apitest.vipps.no';

    public function __construct() {
        $this->method_description = __('Offer Vipps as a payment method', 'vipps');
        $this->method_title = __('Vipps','vipps');
        $this->title = __('Vipps','vipps');
        $this->init_form_fields();
        $this->init_settings();
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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

    // IOK 2018-04-20 for this plugin we will simply return true and add the 'Klarna' form to the receipt apage
    public function process_payment ($order_id) {
        global $woocommerce;
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
            $requestid = 1;
            $res =  $this->api_initiate_payment($phone,$order,$requestid);
        } catch (VippException $e) {
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
        // We need to put the order "on-hold" until confirmed, store metadata for interfaceing with Vipps (in case the callback doesn't work)
        // and store the order id in session so we can access it on the 'waiting for confirmation' screen. IOK 2018-04-24
        $content = $res['content'];
        $transactioninfo = @$content['transactionInfo'];
        $transactionid = @$transactioninfo['transactionId'];
        $vippsstatus = @$transactioninfo['status'];
        $message = __(@$transactioninfo['message'],'vipps');
        $vippstamp = strtotime(@$transactioninfo['timeStamp']);

        WC()->session->set('_vipps_pending_order',$order_id); // Send information to the 'please confirm' screen IOK 2018-04-24

        $order = new WC_Order( $order_id );
        $order->update_status('on-hold', __( 'Awaiting vipps payment', 'vipps' ));
        $order->reduce_order_stock();
        $order->set_transaction_id($transactionid);
        $order->update_meta_data('_vipps_transaction',$transactionid);
        $order->update_meta_data('_vipps_confirm_message',$message);
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_status',$vippsstatus); // INITIATE right now
        $order->add_order_note(__('Vipps payment initiated','vipps'));
        $order->save();

        // Then empty the cart; we'll ressurect it if we can and have to - later IOK 2018-04-24
        $woocommerce->cart->empty_cart();

        // Vipps-terminal-page FIXME fetch from settings! IOK 2018-04-23
        $url = '/vipps-betaling/';

        // This will send us to a receipt page where we will do the actual work. IOK 2018-04-20
        return array('result'=>'success','redirect'=>$url);
    }

    public function admin_options() {
        ?>
            <h2><?php _e('Vipps','vipps'); ?></h2>
            <?php $this->display_errors(); ?>
            <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            </table> <?php
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

        // HTTPS is required. IOK 2018-04-24
        $callback = set_url_scheme(home_url(),'https') . '/wc-api/wc_gateway_vipps';
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            $callBack = set_url_scheme(home_url(),'https') . '/?wc-api=wc_gateway_vipps';
        }

        $transaction = array();
        // IOK FIXME use a 'prefix' setting in options IOK 2018-04-24
        $transaction['orderId'] = 'Woo'.($order->get_id());
        // Ignore refOrderId - for child-transactions 
        // IOK FIXME use a currency conversion here IOK 2018-04-24
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

    // IOK experimental FIXME
    public $has_fields = true;
    public function payment_fields() {
        $fields = WC()->checkout->checkout_fields;
        if (isset($fields['billing']['billing_phone']) && $fields['billing']['billing_phone']['required']) {
            // Use Billing Phone if required IOK 2018-04-24
        } else {
            print "<input type=text name='vippsphone' value='' placeholder='ditt telefonnr'>";
        }
    }
    public function validate_fields() {
        return true;
    }




}
