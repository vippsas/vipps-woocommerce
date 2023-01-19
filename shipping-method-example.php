<?php
add_action( 'woocommerce_shipping_init', function () {

  //add your shipping method to WooCommers list of Shipping methods
  add_filter( 'woocommerce_shipping_methods',  function ($methods) {
//      $methods['vipps_checkout'] = 'VippsCheckout_Shipping_Method';
      $methods['vipps_checkout_posten'] = 'VippsCheckout_Shipping_Method_Posten';
      $methods['vipps_checkout_postnord'] = 'VippsCheckout_Shipping_Method_Postnord';
      $methods['vipps_checkout_helthjem'] = 'VippsCheckout_Shipping_Method_Helthjem';
      $methods['vipps_checkout_porterbuddy'] = 'VippsCheckout_Shipping_Method_Porterbuddy';
      $methods['vipps_checkout_instabox'] = 'VippsCheckout_Shipping_Method_Instabox';
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

                'description' => array(
                    'title' => __( 'Description', 'woo-vipps' ),
                    'type' => 'text',
                    'description' => __( 'Short description of shipping method', 'woo-vipps' ),
                    'default' => "",
                    ),

                'cost' => array(
                    'title' => __( 'Cost ', 'woo-vipps' ),
                    'type' => 'number',
                    'description' => __( 'Cost of shipping', 'woo-vipps' ),
                    'default' => 0
                    ),

                );
        // Support pickup point/home delivery where appropriate
        if (!empty($this->delivery_types)) {
            $options = array('' => __('No delivery method specified', 'woo-vipps'));
            foreach($this->delivery_types as $type) {
                switch ($type) {
                    case 'PICKUP_POINT':
                        $options[$type] = __('Allow user to choose pickup point', 'woo-vipps');
                        break;
                    case 'HOME_DELIVERY':
                        $options[$type] = __('Allow user to select home delivery', 'woo-vipps');
                        break;
                    default:
                            // inconceivable!
                }
            }
            $this->instance_form_fields['type'] = array(
                    'title'       => __( 'Delivery method', 'woocommerce' ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'If you are using Vipps Checkout, you can select extended delivery method options here. If you do, these methods will not appear in Express Checkout or the standard WooCommerce checkout page.', 'woo-vipps' ),
                    'default'     => '',
                    'options'     => $options,
                    'desc_tip'    => false,
                    );
        }
        // From subclasses
        foreach($this->instance_form_fields() as $key=>$setting) {
            $this->instance_form_fields[$key] = $setting;
        }
    }
    public function __construct( $instance_id = 0 ) {
        $this->instance_id 	  = absint( $instance_id );

        //add to shipping zones list
        $this->supports = array( 'instance-settings', 'instance-settings-modal');

        // Subclasses support shipping zones, parent does not. With this we could add a global 
        // settings page using the never-available parent method.
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
            // nop
        } else {
            $this->supports[] = 'shipping-zones';
        }


        $this->preinit();
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        $this->title                = $this->get_option( 'title' );
        $this->tax_status           = 'taxable'; //$this->get_option( 'tax_status' );
        $this->cost                 = $this->get_option( 'cost' );

        // see above - fix this somehow
        $this->enabled = "yes";
        // Never enable parent method
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           $this->enabled = "no";
        }

        $this->init();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    // Add free shipping trigger etc?
    public function calculate_shipping( $package = array()) {
        $instance_settings =  $this->instance_settings;
        $meta = array();
        $meta['brand'] = $this->brand;
        $option = $this->get_option('type', false);
        $description = $this->get_option('description', '');

        if ($option) {
           $meta['type'] = $option;
        }
 
        $this->add_rate( array(
                    'id'      => $this->id,
                    'cost' => 1,
                    'label'   => $this->get_option('title'),
                    'cost'    => $this->get_option('cost'),
                    'package' => $package,
                    'meta_data' => $meta,
                    'taxes'   => true,
                    )
                );
    }

    public function is_enabled() {
        // Only show when Vipps Checkout is active, if the settings are on etc. Also, never the parent method.
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           return false;
        }

        error_log("This is method ". get_class($this) . " wondering if it is enabled. Cart: " . is_cart() . " checkout " . is_checkout());


        return true;
    }

}

class VippsCheckout_Shipping_Method_Posten extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_posten';
    public $delivery_types = ['PICKUP_POINT'];
    public $brand = "POSTEN";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Posten Norge', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Posten', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Posten Norge', 'woo-vipps' );
    }
}

class VippsCheckout_Shipping_Method_Helthjem extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_helthjem';
    public $delivery_types = ['PICKUP_POINT', 'HOME_DELIVERY'];
    public $brand = "HELTHJEM";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Helthjem', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Helthjem', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Helthjem', 'woo-vipps' );
    }
}


class VippsCheckout_Shipping_Method_Postnord extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_postnord';
    public $delivery_types = ['PICKUP_POINT'];
    public $brand = "POSTNORD";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Postnord', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Postnord', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Postnord', 'woo-vipps' );
    }
}

class VippsCheckout_Shipping_Method_Porterbuddy extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_porterbuddy';
    public $delivery_types = ['HOME_DELIVERY'];
    public $brand = "PORTERBUDDY";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Porterbuddy', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Porterbuddy', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Porterbuddy', 'woo-vipps' );
    }
}

class VippsCheckout_Shipping_Method_Instabox extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_instabox';
    public $delivery_types = ['PICKUP_POINT'];
    public $brand = "INSTABOX";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Instabox', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Instabox', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Instabox', 'woo-vipps' );
    }
}



});
