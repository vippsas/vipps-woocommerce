<?php
/**
 * Vipps payment gateway implementation for Gutenberg Blocks
 *
 * @package WooCommerce/Blocks
 * @since 3.0.0
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

final class Vipps extends AbstractPaymentMethodType {
        private $localized=0;
	protected $name = 'vipps';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
	}

        // Register this payment method IOK 2020-08-10
        public static function register() {
            add_action( 'woocommerce_blocks_payment_method_type_registration', 
                        function ($registry) {
                            $registry->register(new static());
            });
        }

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}
	public function get_payment_method_script_handles() {

                $version = filemtime(dirname(__FILE__) . "/js/wc-payment-method-vipps.js");
                $path = plugins_url('js/wc-payment-method-vipps.js', __FILE__);
                $handle = 'wc-payment-method-vipps';
                $dependencies = array('wp-hooks');

                wp_register_script($handle, $path, $dependencies,$version,true);


                // Will not use wp_set_script_translations yet, it seems to be not fully compatible with Loco Translate etc. Instead use
                // old-fashioned localize-script. IOK 2020-08-11
                // wp_set_script_translations( 'wc-payment-method-vipps', 'woo-vipps' );
               
                // This script gets called several times; localize only once.  IOK 2020-08-11
                if (!$this->localized) {
                    $strings = array('Continue with Vipps'=>__('Continue with Vipps', 'woo-vipps'),'Vipps'=> __('Vipps', 'woo-vipps'));
                    wp_localize_script('wc-payment-method-vipps', 'VippsLocale', $strings);
                    $this->localized=1;
                }
		return [ 'wc-payment-method-vipps' ];
	}

        public function get_express_checkout_button () {
            $button = \Vipps::instance()->express_checkout_button_shortcode();
            return  $button;
        }
        public function show_express_checkout_button () {
            $gw = \Vipps::instance()->gateway();
            if (!$gw->cart_supports_express_checkout()) return false;
            return $gw->show_express_checkout();
        }

	public function get_payment_method_data() {
		return [
			'title'                    => $this->get_setting( 'title' ),
			'description'              => $this->get_setting( 'description' ),
                        'iconsrc'                  => apply_filters('woo_vipps_block_logo_url', plugins_url('../../img/vipps_logo_rgb.png', __FILE__)),
                        'show_express_checkout' => $this->show_express_checkout_button(),
                        'expressbutton' => $this->get_express_checkout_button()
		];
	}
}
