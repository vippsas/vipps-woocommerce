<?php
add_action( 'woocommerce_shipping_init', function () {

  //add your shipping method to WooCommers list of Shipping methods
  add_filter( 'woocommerce_shipping_methods',  function ($methods) {
      $methods['vipps_checkout'] = 'VippsCheckout_Shipping_Method';
      return $methods;
  });



class VippsCheckout_Shipping_Method extends WC_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->instance_id 	  = absint( $instance_id );
        $this->id                 = 'vipps_checkout';//this is the id of our shipping method
        $this->method_title       = __( 'Vipps Checkout Shipping', 'woo-vipps' );
        $this->method_description = __( 'Delivery to office of Vipps Checkout Express', 'woo-vipps' );
        //add to shipping zones list
        $this->supports = array(
                'shipping-zones',
                'settings', //use this for separate settings page
                'instance-settings',
                'instance-settings-modal',
                );
        //make it always enabled

        $this->title = __( 'Vipps Checkout Shipping', 'woo-vipps' );
        $this->enabled = 'yes';

        $this->init();

    }

    function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }
    //Fields for the settings page
    function init_form_fields() {

        //fileds for the modal form from the Zones window
        $this->instance_form_fields = array(

                'title' => array(
                    'title' => __( 'Title', 'woo-vipps' ),
                    'type' => 'text',
                    'description' => __( 'Title to be display on site', 'woo-vipps' ),
                    'default' => __( 'Vipps Checkout Shipping', 'woo-vipps' )
                    ),

                'cost' => array(
                    'title' => __( 'Cost ', 'woo-vipps' ),
                    'type' => 'number',
                    'description' => __( 'Cost of shipping', 'woo-vipps' ),
                    'default' => 4
                    ),

                );
        $this->form_fields  = array(
                'config' => array(
                    'title' => __("Configuration thing", 'woo-vipps'),
                    'type' => 'text',
                    'description' => __("Global config thing", 'woo-vipps'),
                    'default' => "foo"
                    ));

    }

    public function calculate_shipping( $package = array()) {
        //as we are using instances for the cost and the title we need to take those values drom the instance_settings
        $intance_settings =  $this->instance_settings;
        // Register the rate
        $this->add_rate( array(
                    'id'      => $this->id,
                    'label'   => $intance_settings['title'],
                    'cost'    => $intance_settings['cost'],
                    'package' => $package,
                    'taxes'   => false,
                    )
                );
    }
}

});
