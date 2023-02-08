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
    // Some methods may support free shipping
    public $supports_free_shipping = true;
    // Porterbuddy (only) supports having the cost calculated in Vipps Checkout itself.
    public $supports_dynamic_cost = false;

    // True for the instances where this has been set to true
    public $dynamic_cost = false;

    public function preinit () {
    }
    public function init () {
    }
    public function postinit () {
    }

    public function instance_form_fields() {
       return [];
    }

    public function free_shipping_form_fields() {
        return array(
                'title'            => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => $this->method_title,
                    'desc_tip'    => true,
                    ),
                'requires'         => array(
                    'title'   => __( 'Free shipping requires...', 'woocommerce' ),
                    'type'    => 'select',
                    'class'   => 'wc-enhanced-select',
                    'default' => '',
                    'options' => array(
                        ''           => __( 'N/A', 'woocommerce' ),
                        'coupon'     => __( 'A valid free shipping coupon', 'woocommerce' ),
                        'min_amount' => __( 'A minimum order amount', 'woocommerce' ),
                        'either'     => __( 'A minimum order amount OR a coupon', 'woocommerce' ),
                        'both'       => __( 'A minimum order amount AND a coupon', 'woocommerce' ),
                        ),
                    ),
                'min_amount'       => array(
                        'title'       => __( 'Minimum order amount', 'woocommerce' ),
                        'type'        => 'price',
                        'placeholder' => wc_format_localized_price( 0 ),
                        'description' => __( 'Users will need to spend this amount to get free shipping (if enabled above).', 'woocommerce' ),
                        'default'     => '0',
                        'desc_tip'    => true,
                        ),
                'ignore_discounts' => array(
                        'title'       => __( 'Coupons discounts', 'woocommerce' ),
                        'label'       => __( 'Apply minimum order rule before coupon discount', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => __( 'If checked, free shipping would be available based on pre-discount order amount.', 'woocommerce' ),
                        'default'     => 'no',
                        'desc_tip'    => true,
                        ),
                );



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
                    'desc_tip'=>true
                    ));

        if ($this->supports_dynamic_cost) {
            $this->instance_form_fields['dynamic_cost'] = array(
                    'title'       => __( 'Calculate cost in Checkout', 'woo-vipps' ),
                    'label'       => __( 'Calculate costs in the Checkout window', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'class'       => 'vipps_checkout_dynamic_cost_field',
                    'description' => __( 'If checked, cost of shipping will be calculated dynamically in the Vipps Checkout window', 'woo-vipps' ),
                    'default'     => 'no',
                    'desc_tip'    => true,
                    );

        }
       $this->instance_form_fields['cost'] = array(
                    'title' => __( 'Cost ', 'woo-vipps' ),
                    'type' => 'number',
                    'class' => 'vipps_checkout_cost_field',
                    'description' => __( 'Cost of shipping', 'woo-vipps' ),
                    'default' => 0,
                    'desc_tip'=>true
                    );
        // Support pickup point/home delivery where appropriate
        if (!empty($this->delivery_types)) {
            $options = array('' => __('No delivery method specified', 'woo-vipps'));
            foreach($this->delivery_types as $type) {
                switch ($type) {
                    case 'MAILBOX':
                        $options[$type] = __('Deliver to customers\' mailbox', 'woo-vipps');
                        break;
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
                    'desc_tip'    => true,
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

        if ($this->supports_free_shipping) {
 add_action( 'admin_footer', array( 'WC_Shipping_Free_Shipping', 'enqueue_admin_js' ), 10 ); // Priority needs to be higher than wc_print_js (25).
            $this->instance_form_fields = array_merge($this->instance_form_fields, $this->free_shipping_form_fields());
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
        $this->dynamic_cost = $this->supports_dynamic_cost && ('yes' == $this->get_option('dynamic_cost'));

        // Never enable parent method
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           $this->enabled = "no";
        }

        $this->init();
        $this->postinit();

        add_action( 'admin_footer', array( 'VippsCheckout_Shipping_Method', 'enqueue_admin_js' ), 10 ); // Priority needs to be higher than wc_print_js (25).

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }


    public function get_cost($package) {
        $cost = $this->get_option('cost');
        if ($this->supports_dynamic_cost && $this->dynamic_cost ) {
              $cost = 0;
        }

        $cost = apply_filters('woo_vipps_vipps_checkout_shipping_cost', $cost, $this);
        return $cost;
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

        $cost = $this->get_cost($package);

        $ratedata = array(
                    'id'      => $this->get_rate_id(),
                    'label'   => $this->get_option('title'),
                    'cost'    => $cost,
                    'package' => $package,
                    'meta_data' => $meta,
                    );

        error_log("Rate: " . print_r($ratedata, true));

        $ratedata = apply_filters('woo_vipps_vipps_checkout_shipping_rate', $ratedata, $this);

        $this->add_rate($ratedata);
    }

    public function is_enabled() {
        return true; // FIXME
    }

    public function is_available($package) {

error_log("In is_available for " . get_class($this) . " instance id " . $this->instance_id);


        // Never show the parent method
        if (get_class($this) == "VippsCheckout_Shipping_Method") {
           return false;
        }
        $is_vipps_checkout = apply_filters('woo_vipps_is_vipps_checkout', false);

error_log("Is vipps checkout: $is_vipps_checkout");

        if ($is_vipps_checkout) return true;

error_log("Is cart: " . is_cart());

        if (is_cart()) return true;

error_log("Is extended: " . $this->is_extended());

        // Extended methods must only be shown in Cart and on Vipps Checkout
        if ($this->is_extended()) return false;
        return true;         
    }


    // Static so it gets loaded just once; must be loaded before 25. (See Free Shipping).
    public static function enqueue_admin_js() {
        wc_enqueue_js( "jQuery(document).ready(function () {

          function vipps_checkout_hide_cost_for_dynamic_pricing (el) {
              let form = jQuery( el ).closest( 'form' );
              let costfield = jQuery( '.vipps_checkout_cost_field', form ).closest( 'tr' );
              if (jQuery(el).is(':checked')) {
                  costfield.hide();
              } else {
                  costfield.show();
              }
          }

          jQuery( document.body ).on( 'change', '.vipps_checkout_dynamic_cost_field', function() {
             vipps_checkout_hide_cost_for_dynamic_pricing( this );
          });
          jQuery( '.vipps_checkout_dynamic_cost_field' ).trigger( 'change' );
          jQuery(document.body).on( 'wc_backbone_modal_loaded', function( evt, target ) {
            if ( 'wc-modal-shipping-method-settings' === target ) {
                vipps_checkout_hide_cost_for_dynamic_pricing(jQuery('#wc-backbone-modal-dialog .vipps_checkout_dynamic_cost_field', evt.currentTarget));
            }
          });

         console.log('vipps checkouuuuut')
         });");


    }

}

class VippsCheckout_Shipping_Method_Posten extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_posten';
    public $delivery_types = ['MAILBOX','PICKUP_POINT','HOME_DELIVERY'];
    public $brand = "POSTEN";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Posten Norge', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Posten', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Posten Norge', 'woo-vipps' );
    }
    // After all settings loaded
    public function postinit () {
        $this->dynamic_cost= true; // NO! just for testing FIXME
    }

}

class VippsCheckout_Shipping_Method_Helthjem extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_helthjem';
    public $delivery_types = ['HOME_DELIVERY', 'PICKUP_POINT'];
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
    public $delivery_types = ['PICKUP_POINT','HOME_DELIVERY'];
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
    // Cannot support free shipping, since cost == 0 means dynamic pricing
    public $supports_free_shipping = false;
    public $supports_dynamic_cost = true;

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Porterbuddy', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Porterbuddy', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Porterbuddy', 'woo-vipps' );
    }





}

class VippsCheckout_Shipping_Method_Instabox extends VippsCheckout_Shipping_Method {
    public $id = 'vipps_checkout_instabox';
    public $delivery_types = ['HOME_DELIVERY','PICKUP_POINT'];
    public $brand = "INSTABOX";

    // Called by the parent constructor before loading settings
    function preinit() {
        $this->defaulttitle = __( 'Instabox', 'woo-vipps' );
        $this->method_title = __( 'Vipps Checkout: Instabox', 'woo-vipps' );
        $this->method_description = __( 'Fraktmetode spesielt for Vipps Checkout: Instabox', 'woo-vipps' );
    }


}



});
