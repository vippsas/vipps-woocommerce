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

}
