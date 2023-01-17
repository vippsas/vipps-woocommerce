<?php
add_action( 'woocommerce_shipping_init', function () {

  //add your shipping method to WooCommers list of Shipping methods
  add_filter( 'woocommerce_shipping_methods',  function ($methods) {
//      $methods['vipps_checkout'] = 'VippsCheckout_Shipping_Method';
      $methods['vipps_checkout_posten'] = 'VippsCheckout_Shipping_Method_Posten';
      return $methods;
  });


/* Abstract class used just to mark the special Vipps Checkout shipping methods. Don't instantiate this */
class VippsCheckout_Shipping_Method extends WC_Shipping_Method {
    public $defaulttitle = "";
    // Basic price, set by options
    public $cost = 0;

    public function preinit () {
    }
    public function init () {
    }

    public function instance_form_fields() {
       return [];
    }

    // Instance setting, common for all submethods
    function init_form_fields() {
        $this->instance_form_fields = array(

                'title' => array(
                    'title' => __( 'Title', 'woo-vipps' ),
                    'type' => 'text',
                    'description' => __( 'Title to be display on site', 'woo-vipps' ),
                    'default' => $this->defaulttitle
                    ),

                'cost' => array(
                    'title' => __( 'Cost ', 'woo-vipps' ),
                    'type' => 'number',
                    'description' => __( 'Cost of shipping', 'woo-vipps' ),
                    'default' => 0
                    ),

                );
        foreach($this->instance_form_fields() as $key=>$setting) {
            $this->instance_form_fields[$key] = $setting;
        }
    }

    public function __construct( $instance_id = 0 ) {
        $this->instance_id 	  = absint( $instance_id );

        //add to shipping zones list
        $this->supports = array(
                'shipping-zones', 
                'instance-settings',
                'instance-settings-modal',
                );

        $this->preinit();
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        $this->title                = $this->get_option( 'title' );
        $this->tax_status           = 'taxable'; //$this->get_option( 'tax_status' );
        $this->cost                 = $this->get_option( 'cost' );

        // see above - fix this somehow
        $this->enabled = "yes";

        $this->init();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }


}

class VippsCheckout_Shipping_Method_Posten extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_posten';

    public function instance_form_fields() {
         $settings = array();
         return $settings;
    }

    public function is_enabled() {
        // Only show when Vipps Checkout is active FIXME (and maybe the settings page)
        return true;
    }

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Posten Norge', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Posten', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Posten Norge', 'woo-vipps' );
    }
    function init() {
    }

    // Add free shipping trigger etc?
    public function calculate_shipping( $package = array()) {
        $instance_settings =  $this->instance_settings;
        // Register the rate
        $this->add_rate( array(
                    'id'      => $this->id,
                    'cost' => 1,
                    'label'   => $instance_settings['title'],
                    'cost'    => $instance_settings['cost'],
                    'package' => $package,
                    'taxes'   => true,
                    )
                );
    }
}

});
