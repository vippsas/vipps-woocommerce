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

  $extended_classes = ['flat_rate', 'free_shipping', 'local_pickup'];
  foreach($extended_classes as $key) {
      add_filter('woocommerce_shipping_instance_form_fields_' . $key, array('VippsCheckout_Shipping_Method', 'add_description_field'));
  }
      



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

    // Add a description field to a standard shipping method
    public static function add_description_field($form_fields) {
        if (! array_key_exists('description', $form_fields)) {
            $description = array('description' =>  array( 
                        'title' => __( 'Description', 'woo-vipps' ),
                        'type' => 'text',
                        'description' => __( 'Short description of shipping method used in Vipps Checkout', 'woo-vipps' ),
                        'default' => "",
                        ));
            $index = array_search('title', array_keys($form_fields));
            if ($index === false) {
                $form_fields['description'] = $description['description'];
            } else {
                $pos = $index+1;
                $form_fields = array_merge(array_slice($form_fields, 0, $pos), $description, array_slice($form_fields, $pos));
            }
        }
          return $form_fields;
    }

    // Instance setting, common for all submethods
    public function init_form_fields() {
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

        $this->instance_form_fields['tax_status'] =  array(
                'title'   => __( 'Tax status', 'woocommerce' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __( 'Taxable', 'woocommerce' ),
                    'none'    => _x( 'None', 'Tax status', 'woocommerce' ),
                    ),
                );


        // From subclasses
        foreach($this->instance_form_fields() as $key=>$setting) {
            $this->instance_form_fields[$key] = $setting;
        }
    }

    // True if the method contains extended options for Vipps Checkout
    public function is_extended() {
       return $this->get_option('type', false);
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
        $this->tax_status           = $this->get_option( 'tax_status' );

        // Never enable parent method
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           $this->enabled = "no";
        }

        $this->init();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }


    public function get_cost($package) {
        return $this->get_option('cost');
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

        $cost = apply_filters('woo_vipps_vipps_checkout_shipping_cost', $this->get_cost($package), $this);
 
        $this->add_rate( array(
                    'id'      => $this->id,
                    'cost' => 1,
                    'label'   => $this->get_option('title'),
                    'cost'    => $cost,
                    'package' => $package,
                    'meta_data' => $meta,
                    'taxes'   => true,
                    )
                );
    }

    public function is_enabled() {
        // Never show the parent method
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           return false;
        }
        $is_vipps_checkout = apply_filters('woo_vipps_is_vipps_checkout', false);
        if ($is_vipps_checkout) return true;
        if (is_cart()) return true;

        // Extended methods must only be shown in Cart and on Vipps Checkout
        if ($this->is_extended()) return false;
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
