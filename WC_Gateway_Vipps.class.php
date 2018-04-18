<?php

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; // IOK FIXME
    public $has_fields = false; // IOK FIXME
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";

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
                    'type'        => 'text',
                    'description' => __('Client Id from Developer Portal - Applications tab, "View Secret"','vipps'),
                    'default'     => '',
                    ),
                 'secret' => array(
                    'title' => __('Application Secret', 'vipps'),
                    'label'       => __( 'Application Secret', 'vipps' ),
                    'type'        => 'text',
                    'description' => __('Application secret from Developer Portal - Applications tab, "View Secret"','vipps'),
                    'default'     => '',
                    ),
                 'Ocp_Apim_Key_AccessToken' => array(
                    'title' => __('Subscription key for Access Token', 'vipps'),
                    'label'       => __( 'Subscription key for Access Token', 'vipps' ),
                    'type'        => 'text',
                    'description' => __('The Primary key for the Access Token subscription from your profile on the developer portal','vipps'),
                    'default'     => '',
                    ),
                 'Ocp_Apim_Key_eCommerce' => array(
                    'title' => __('Subscription key for eCommerce', 'vipps'),
                    'label'       => __( 'Subscription key for eCommerce', 'vipps' ),
                    'type'        => 'text',
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
            <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            </table> <?php
    }

    function process_admin_options () {
        // Handle options updates
        $saved = parent::process_admin_options();
        return $saved;
    }

private function http_call($url,$data,$verb='GET',$headers=[],$encoding='url'){
    $date = gmdate('c');
    $data_encoded = '';
    if ($encoding == 'url' || $verb == 'GET') {
     $data_encoded = http_build_query($data);
    } else {
     $data_encoded = json_encode($data);
    }
    $data_len = strlen ($data_encoded);
    $http_response_header = null;
    $sslparams = [];

    if (preg_match("!\.dev\.!",$url)) {
    //  IOK 2017-11-09 Peer certificate CN=`internal.beat.no' did not match expected CN=`api.dev.beat.no'
     $sslparams['verify_peer'] = false;
     $sslparams['verify_peer_name'] = false;
    }

    $headers['Connection'] = 'close';
    if ($verb=='POST' || $verb == 'PATCH' || $verb == 'PUT') {
     if ($encoding == 'url') {
      $headers['Content-type'] = 'application/x-www-form-urlencoded';
     } else {
      $headers['Content-type'] = 'application/json';
     }
    }
    $headerstring = '';
    $hh = [];
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
    $params = ['http'=>$httpparams,'ssl'=>$sslparams];

    $context = stream_context_create($params);
    $content = null;

    $contenttext = @file_get_contents($url,false,$context);
    if ($contenttext) {
     $content = json_decode($contenttext,true);
    }
    $response = 0;
    if ($http_response_header && isset($http_response_header[0])) {
     $match = [];
     $ok = preg_match('!^HTTP/... (...) !i',$http_response_header[0],$match);
     if ($ok) {
      $response = 1 * $match[1];
     }
    }
    return array('response'=>$response,'headers'=>$http_response_header,'content'=>$content);
  }



}
