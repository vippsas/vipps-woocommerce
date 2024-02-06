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
        protected $payment_method_name = "Vipps";

	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
                $this->payment_method_name =  \WC_Gateway_Vipps::instance()->get_payment_method_name();
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
                $dependencies = array('wp-hooks', 'vipps-gw');

                wp_register_script($handle, $path, $dependencies,$version,true);


                // Will not use wp_set_script_translations yet, it seems to be not fully compatible with Loco Translate etc. Instead use
                // old-fashioned localize-script IOK 2020-08-11
                // wp_set_script_translations( 'wc-payment-method-vipps', 'woo-vipps' );
                // VippsLocale is localized for vipps-gw instead of this, as it seems it does no longer work to do localize-script at this point.
                // IOK 2022-12-13

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

                $logo = ($this->payment_method_name == "Vipps") ? plugins_url('../../img/vipps-mark.svg', __FILE__) : plugins_url('../../img/mobilepay-mark.png', __FILE__);

		return [
			'title'                    => $this->payment_method_name,
			'description'              => $this->get_setting( 'description' ),
                        'iconsrc'                  => apply_filters('woo_vipps_block_logo_url', $logo),
                        'show_express_checkout' => $this->show_express_checkout_button(),
                        'expressbutton' => $this->get_express_checkout_button()
		];
	}
}
