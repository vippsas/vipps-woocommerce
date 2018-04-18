<?php

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; // IOK FIXME
    public $has_fields = false; // IOK FIXME
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

    // IOK FIXME  the core of things
    public function process_payment ($order_id) {
        global $woocommerce;
        $order = new WC_Order( $order_id );

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __( 'Vipps payment initiated', 'vipps' ));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
                );
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

    $httpparams = array('method'=>$verb,'header'=>$headerstring);
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
