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

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    public function get_access_token() { 
        $clientid=$this->get_option('clientId');
        $secret=$this->get_option('secret');
        $at = $this->get_option('Ocp_Apim_Key_AccessToken');
        $command = 'accessToken/get';
        return $this->http_call($command,array(),'POST',array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$at),'url');
    }

    public function initiate_payment($phone,$order,$requestid=1) {
        $command = 'Ecomm/v1/payments';
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

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    public function order_status($order) {
        $merch = $this->get_option('merchantSerialNumber');
        $vippsorderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v1/payments/'.$vippsorderid.'/serialNumber/'.$merch.'/status';
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
        $res = $this->http_call($command,$data,'GET',$headers);
        return $res;
    }

    // Capture a payment made. Defaults to full capture only. IOK 2018-05-07
    public function capture_payment($order,$requestid=1,$amount=0) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $command = 'Ecomm/v1/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
        }
        if (!$merch) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
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

        $command = 'Ecomm/v1/payments/'.$orderid.'/cancel';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
        }
        if (!$merch) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
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
    public function refund_payment($order,$requestid=1,$amount=0) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : $order->get_total();

        $command = 'Ecomm/v1/payments/'.$orderid.'/refund';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_option('Ocp_Apim_Key_eCommerce');
        $merch = $this->get_option('merchantSerialNumber');
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
        }
        if (!$merch) {
            throw new VippsAPIException(__('Unfortunately, the Vipps payment method is currently unavailable.','vipps'));
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

}
