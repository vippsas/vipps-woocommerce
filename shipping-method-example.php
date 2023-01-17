<?php
add_action( 'woocommerce_shipping_init', function () {

  //add your shipping method to WooCommers list of Shipping methods
  add_filter( 'woocommerce_shipping_methods',  function ($methods) {
//      $methods['vipps_checkout'] = 'VippsCheckout_Shipping_Method';
      $methods['vipps_checkout_posten'] = 'VippsCheckout_Shipping_Method_Posten';
      return $methods;
  });


/* Abstract method used just to mark the special Vipps Checkout shipping methods */
class VippsCheckout_Shipping_Method extends WC_Shipping_Method {

}

class VippsCheckout_Shipping_Method_Posten extends VippsCheckout_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->instance_id 	  = absint( $instance_id );
        $this->id                 = 'vipps_checkout_posten';

        // in process_admin_options, endre denne til korrekt tittel med prefiks "Vipps Checkout"
        
        $this->method_title       = __( 'Vipps Checkout: Posten', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Posten Norge', 'woo-vipps' );
        //add to shipping zones list
        $this->supports = array(
                'shipping-zones', 
                'instance-settings',
                'instance-settings-modal',
                );
        $this->title = __( 'Vipps Checkout: Posten', 'woo-vipps' );
        $this->init();
    }

    public function is_enabled() {
        // Only show when Vipps Checkout is active FIXME (and maybe the settings page)
        return true;
    }

    function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    // Fields for the settings of the instance
    function init_form_fields() {

        //fileds for the modal form from the Zones window
        $this->instance_form_fields = array(

                'title' => array(
                    'title' => __( 'Title', 'woo-vipps' ),
                    'type' => 'text',
                    'description' => __( 'Title to be display on site', 'woo-vipps' ),
                    'default' => __( 'Posten Norge', 'woo-vipps' )
                    ),

                'cost' => array(
                    'title' => __( 'Cost ', 'woo-vipps' ),
                    'type' => 'number',
                    'description' => __( 'Cost of shipping', 'woo-vipps' ),
                    'default' => 0
                    ),

                );
        // No global settings for this method.
    }

    // Add free shipping trigger etc?
    public function calculate_shipping( $package = array()) {
        //as we are using instances for the cost and the title we need to take those values drom the instance_settings
        $intance_settings =  $this->instance_settings;
        // Register the rate
        $this->add_rate( array(
                    'id'      => $this->id,
                    'label' => __("Vipps Checkout: Posten", 'woo-vipps'), // FIXME
                    'cost' => 1,
                    'label'   => $intance_settings['title'],
                    'cost'    => $intance_settings['cost'],
                    'package' => $package,
                    'taxes'   => true,
                    )
                );
    }
}

});
