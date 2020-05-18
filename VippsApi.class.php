<?php
/*
   This is the VippsApi  class, a delegate in WC_Payment_Gateway that handles the actual communication with Vipps.
   The parameters are fetched from the containing class. IOK 2018-05-11

    This file is part of the WordPress plugin Checkout with Vipps for WooCommerce
    Copyright (C) 2018 WP Hosting AS

    Checkout with Vipps for WooCommerce is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Checkout with Vipps for WooCommerce is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.


 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
require_once(dirname(__FILE__) . "/VippsAPIException.class.php");

class VippsApi {
    public $gateway;

    public function __construct($gateway) {
        $this->gateway = $gateway;
    }

    // These abstraction gets the correct client id and so forth based on whether or not test mode is on
    public function get_merchant_serial() {
        return $this->gateway->get_merchant_serial();
    }
    public function get_clientid() {
        return $this->gateway->get_clientid();
    }
    public function get_secret() {
        return $this->gateway->get_secret();
    }
    public function get_key() {
        $key = $this->gateway->get_key(); 
        return $key;
    }
    public function get_orderprefix() {
        return $this->gateway->get_orderprefix();
    }


    public function get_option($optionname) {
        return $this->gateway->get_option($optionname);
    }
    public function log($what,$type='info') {
        return $this->gateway->log($what,$type);
    }

    // Get an App access token if neccesary. Returns this or throws an error. IOK 2018-04-18
    public function get_access_token($force=0) {
        // First, get a stored token if it exists
        $stored = get_transient('_vipps_app_token');
        if (!$force && $stored && $stored['expires_on'] > time()) {
            return $stored['access_token'];
        }
        // Otherwise, get it from vipps - this might throw errors 
        $fresh = $this->get_access_token_from_vipps();
        if (!$fresh) return null;

        $at = $fresh['access_token'];
        $expire = $fresh['expires_in']/2;
        set_transient('_vipps_app_token',$fresh,$expire);
        return $at;
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function get_access_token_from_vipps() { 
        $clientid=$this->get_clientid();
        $secret=$this->get_secret();
        $at = $this->get_key();
        $command = 'accessToken/get';
        try {
            $result = $this->http_call($command,array(),'POST',array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$at),'url');
            return $result;
        } catch (TemporaryVippsAPIException $e) {
            $this->log(__("Could not get Vipps access token",'woo-vipps') .' '. $e->getMessage(), 'error');
            throw $e;
        } catch (Exception $e) {
            $this->log(__("Could not get Vipps access token",'woo-vipps') .' '. $e->getMessage(). "\n" . $e->getMessage(), 'error');
            throw new VippsAPIConfigurationException($e->getMessage());
        }
    }

    public function initiate_payment($phone,$order,$returnurl,$authtoken,$requestid) {
        $command = 'Ecomm/v2/payments';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        $prefix = $this->get_orderprefix();
        $static_shipping = $order->get_meta('_vipps_static_shipping');

        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        $vippsorderid =  apply_filters('woo_vipps_orderid', $prefix.($order->get_id()), $prefix, $order);
        $order->update_meta_data('_vipps_prefix',$prefix);
        $order->update_meta_data('_vipps_orderid', $vippsorderid);
        $order->set_transaction_id($vippsorderid); // The Vipps order id is probably the clossest we are getting to a transaction ID IOK 2019-03-04
        $order->delete_meta_data('_vipps_static_shipping'); // Don't need this any more
        $order->save();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $callback = $this->gateway->payment_callback_url($authtoken);
        $fallback = $returnurl;

        $transaction = array();
        $transaction['orderId'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round(wc_format_decimal($order->get_total(),'') * 100); 
        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());
        $transactionText =  __('Confirm your order from','woo-vipps') . ' ' . $shop_identification;
        $transaction['transactionText'] = apply_filters('woo_vipps_transaction_text', $transactionText, $order);

        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($transaction['transactionText']);
        if ($length>99) {
          $this->log('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps');
          $transaction['transactionText'] =  __('Confirm your order','woo-vipps');
          $transaction['transactionText'] = substr($transaction['transactionText'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }

        $transaction['timeStamp'] = $date;


        $data = array();
        $data['customerInfo'] = array('mobileNumber' => $phone); 
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch, 'callbackPrefix'=>$callback, 'fallBack'=>$fallback); 

        $express = $this->gateway->express_checkout;
        if ($express) {
            $shippingcallback = $this->gateway->shipping_details_callback_url($authtoken);
            if ($authtoken) {
                $data['merchantInfo']['authToken'] = "Basic " . base64_encode("Vipps" . ":" . $authtoken);
            }
            $data['merchantInfo']["paymentType"] = "eComm Express Payment";
            $data['merchantInfo']["consentRemovalPrefix"] = $this->gateway->consent_removal_callback_url();
            $data['merchantInfo']['shippingDetailsPrefix'] = $shippingcallback;

            if ($static_shipping) {
                $data['merchantInfo']['staticShippingDetails'] = $static_shipping["shippingDetails"];
            }

        }
        $data['transaction'] = $transaction;



        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    public function order_status($order) {
        $merch = $this->get_merchant_serial();
        $vippsorderid = $order->get_meta('_vipps_orderid');
	$requestid = 1;

        $command = 'Ecomm/v2/payments/'.$vippsorderid.'/status';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $data = array();
        $res = $this->http_call($command,$data,'GET',$headers);
        return $res;
    }

    // Capture a payment made. Amount is in cents and required. IOK 2018-05-07
    public function capture_payment($order,$amount,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        $transaction = array();
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($amount);

        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());

        $transaction['transactionText'] = __('Order capture for order','woo-vipps') . ' ' . $orderid . ' ' . $shop_identification;

        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($transaction['transactionText']);
        if ($length>99) {
          $this->log('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps');
          $transaction['transactionText'] = __('Order capture for order','woo-vipps') . ' ' . $orderid;
          $transaction['transactionText'] = substr($transaction['transactionText'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }
        


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Cancel a reserved but not captured payment IOK 2018-05-07
    public function cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/cancel';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $transaction = array();
        $transaction['transactionText'] = __('Order cancel for order','woo-vipps') . ' ' . $orderid . ' ';

        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'PUT',$headers,'json'); 
        return $res;
    }

    // Refund a captured payment.  IOK 2018-05-08
    public function refund_payment($order,$requestid=1,$amount=0,$cents=false) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : wc_format_decimal($order->get_total(),'');

        $command = 'Ecomm/v2/payments/'.$orderid.'/refund';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        // Ignore refOrderId - for child-transactions 
        $transaction = array();
        // If we have passed the value as 'øre' we don't need to calculate any more.
        if ($cents) {
            $transaction['amount'] = $amount;
        } else { 
            $transaction['amount'] = round($amount * 100); 
        }
        $transaction['transactionText'] = __('Refund for order','woo-vipps') . ' ' . $orderid;


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Used to retrieve shipping and user details for express checkout orders where relevant and the callback isn't coming.
    public function payment_details ($order) {
	$requestid=0;
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'Ecomm/v2/payments/'.$orderid.'/details';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        $data = array();

        $res = $this->http_call($command,$data,'GET',$headers,'json'); 
        return $res;
    }


    // Conveniently call Vipps IOK 2018-04-18
    private function http_call($command,$data,$verb='GET',$headers=null,$encoding='url'){
        $server=$this->gateway->apiurl();
        $url = $server . "/" . $command; 

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

        $headers['Connection'] = 'close';
        if ($verb=='POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $headers['Content-length'] = $data_len;
            if ($encoding == 'url') {
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
            } else {
                $headers['Content-type'] = 'application/json';
            }
        }
        $args = array();
        $args['method'] = $verb;
        $args['headers'] = $headers;
        if ($verb == 'POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $args['body'] = $data_encoded;
        }
        if ($verb == 'GET' && $data_encoded) {
            $url .= "?$data_encoded";
        }
        $return = wp_remote_request($url,$args);
        $headers = array();
        $content=NULL;
        $response=0;

        if (is_wp_error($return))  {
            $headers['status'] = "500 " . $return->get_error_message();
            $response = 500;
        } else {
            $response = wp_remote_retrieve_response_code($return);
            $message =  wp_remote_retrieve_response_message($return);
            $headers = wp_remote_retrieve_headers($return);
            $headers['status'] = "$response $message";
            $contenttext = wp_remote_retrieve_body($return);
            if ($contenttext) {
                $content = json_decode($contenttext,true);
            }
        }

        // Parse the result, converting it to exceptions if neccessary. IOK 2018-05-11
        return $this->handle_http_response($response,$headers,$content);
    }

    // Read the response from Vipps - if any - and convert errors (null results, results over 299)
    // to Exceptions IOK 2018-05-11
    private function handle_http_response ($response, $headers, $content) {
        // This would be an error in the URL or something - or a network outage IOK 2018-04-24
        // we will assume it is temporary (ie, no response).
        if (!$response) {
            $msg = __('No response from Vipps', 'woo-vipps');
            throw new TemporaryVippsAPIException($msg);
        }

        // Good result!
        if ($response < 300) {
            return $content; 
        }
        // Now errorhandling. Default to use just the error header IOK 2018-05-11
        $msg = $headers['status'];

        // Sometimes we get one type of error, sometimes another, depending on which layer explodes. IOK 2018-04-24
        if ($content) {
	    // From initiate payment, at least some times. IOK 2018-06-18
	    if (isset($content['message'])) {
		$msg = $content['message'];
	    } elseif (isset($content['error'])) {
                // This seems to be only for the Access Token, which is a separate application IOK 2018-05-11
                $msg = $content['error'];
            } elseif (isset($content['ResponseInfo'])) {
                // This seems to be an error in the API layer. The error is in this elements' ResponseMessage
                $msg = $response  . ' ' .  $content['ResponseInfo']['ResponseMessage'];
            } elseif (isset($content['errorInfo'])) {
                $msg = $response  . ' ' .  $content['errorInfo']['errorMessage'];
            } else {
                // Otherwise, we get a simple array of objects with error messages.  Grab them all.
                $msg = '';
                foreach($content as $entry) {
                    $msg .= $response  . ' ' .   $entry['errorMessage'] . "\n";
                } 
            }
        }

        // 502's are Bad Gateway which means that Vipps is busy. IOK 2018-05-11
        if (intval($response) == 502) {
            $exception = new TemporaryVippsAPIException($msg);
        } else {
            $exception = new VippsApiException($msg);
        }

        $exception->responsecode = intval($response);
        throw $exception;
    }

}
