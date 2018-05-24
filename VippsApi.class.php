<?php
/*
   This is the VippsApi  class, a delegate in WC_Payment_Gateway that handles the actual communication with Vipps.
   The parameters are fetched from the containing class. IOK 2018-05-11

 */

require_once(dirname(__FILE__) . "/exceptions.php");

class VippsApi {
    public $gateway;

    public function __construct($gateway) {
        $this->gateway = $gateway;
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
        set_transient('_vipps_app_token',$resp,$expire);
        return $at;
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function get_access_token_from_vipps() { 
        $clientid=$this->get_option('clientId');
        $secret=$this->get_option('secret');
        $at = $this->get_option('Ocp_Apim_Key_AccessToken');
        $command = 'accessToken/get';
        try {
            $result = $this->http_call($command,array(),'POST',array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$at),'url');
            return $result;
        } catch (TemporaryVippsAPIException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->log(__("Could not get Vipps access token",'vipps') .' '. $e->getMessage());
            throw new VippsAPIConfigurationException($e->getMessage());
        }
    }

    // This is for 'login with vipps', not express checkout - but both have the same parameters more or less. IOK 2018-05-18
    public function login_request($returnurl,$authtoken,$requestid) {
        $command = 'signup/v1/loginRequests';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $callback = $this->gateway->login_callback_url();
        $consentremoval = $this->gateway->consent_removal_callback_url();

        $merchantInfo = array();
        $merchantInfo['merchantSerialNumber'] = $merch;
        $merchantInfo['callbackPrefix'] = $callback;
        $merchantInfo['consentRemovalPrefix'] = $consentremoval;
        $merchantInfo['fallBack'] = $returnurl;
        $merchantInfo['isApp'] = false;
        $merchantInfo['autoLoginToken'] = "";

        /// *WHY*? Well, PHP will parse an Authorization header, and will absolutely not understand anything else than Basic and Digest. The header itself is stripped.
        /// So we must make Vipps actually send a Basic login.
        $merchantInfo['authToken'] = "Basic " . base64_encode("Vipps" . ":" . $authtoken);

        $data = array('merchantInfo'=>$merchantInfo);
        $res = $this->http_call($command,$data,'POST',$headers,'json');
        return $res;
    }

    // Call Vipps to get the result of the login request passed. Should be called just once. IOK 2018-05-22 
    public function login_request_status($requestid) {
        $command = 'signup/v1/loginRequests/' . $requestid;
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $res = $this->http_call($command,array(),'GET',$headers);
        return $res;
    }

    public function initiate_payment($phone,$order,$returnurl,$authtoken,$requestid) {
        $command = 'Ecomm/v2/payments';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        $prefix = $this->get_option('orderprefix');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
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

        $callback = $this->gateway->payment_callback_url();
        $fallback = $returnurl;

        $transaction = array();
        $transaction['orderId'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($order->get_total() * 100); 
        $transaction['transactionText'] = __('Confirm your order from','vipps') . ' ' . home_url(); 
        $transaction['timeStamp'] = $date;


        $data = array();
        $data['customerInfo'] = array('mobileNumber' => $phone); // IOK FIXME not required in 2.0
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch, 'callbackPrefix'=>$callback, 'fallBack'=>$fallback); 

        $express = true;
        // Exptress only if shipping and address missign
        if ($express) {
          $data['merchantInfo']['shippingDetailsPrefix'] = $this->gateway->shipping_details_callback_url();
          if ($authtoken) {
            $data['merchantInfo']['authToken'] = "Basic " . base64_encode("Vipps" . ":" . $authtoken);
          }
          $data['merchantInfo']["paymentType"] = "eComm Express Payment";
          $data['merchantInfo']["consentRemovalPrefix"] = $this->gateway->consent_removal_callback_url();
        }

        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    public function order_status($order) {
        $merch = $this->get_option('merchantSerialNumber');
        $vippsorderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$vippsorderid.'/status';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $prefix = $this->get_option('orderprefix');
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
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

    // Capture a payment made. Defaults to full capture only. IOK 2018-05-07
    public function capture_payment($order,$requestid=1,$amount=0) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $command = 'Ecomm/v2/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        $transaction = array();
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($amount * 100); 
        $transaction['transactionText'] = __('Order capture for order','vipps') . ' ' . $orderid . ' ' . home_url(); 


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Cancel a reserved but not captured payment IOK 2018-05-07
    public function cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $command = 'Ecomm/v2/payments/'.$orderid.'/cancel';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $transaction = array();
        $transaction['transactionText'] = __('Order cancel for order','vipps') . ' ' . $orderid . ' ';

        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'PUT',$headers,'json'); 
        return $res;
    }

    // Refund a captured payment.  IOK 2018-05-08
    public function refund_payment($order,$requestid=1,$amount=0,$cents=false) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $command = 'Ecomm/v2/payments/'.$orderid.'/refund';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','vipps'),'error');
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
        $transaction['transactionText'] = __('Refund for order','vipps') . ' ' . $orderid;


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }


    // Conventently call Vipps IOK 2018-04-18
    private function http_call($command,$data,$verb='GET',$headers=null,$encoding='url'){
        $server=$this->gateway->apiurl;
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

$this->log($url);
$this->log(print_r($data_encoded,true));
$this->log($contenttext);
$this->log(print_r($http_response_headers,true));

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
        // Parse the result, converting it to exceptions if neccessary. IOK 2018-05-11
        return $this->handle_http_response($response,$http_response_header,$content);
    }

    // Read the response from Vipps - if any - and convert errors (null results, results over 299)
    // to Exceptions IOK 2018-05-11
    private function handle_http_response ($response, $headers, $content) {
        // This would be an error in the URL or something - or a network outage IOK 2018-04-24
        // we will assume it is temporary (ie, no response).
        if (!$response) {
            $msg = __('No response from Vipps', 'vipps');
            throw new TemporaryVippsAPIException($msg);
        }

        // Good result!
        if ($response < 300) {
            return $content; 
        }
        // Now errorhandling. Default to use just the error header IOK 2018-05-11
        $msg = $headers[0];

        // Sometimes we get one type of error, sometimes another, depending on which layer explodes. IOK 2018-04-24
        if ($content) {
            if (isset($content['error'])) {
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
