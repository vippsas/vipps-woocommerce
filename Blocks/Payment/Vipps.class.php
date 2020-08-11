<?php
/**
 * Vipps payment gateway implementation for Gutenberg Blocks
 *
 * @package WooCommerce/Blocks
 * @since 3.0.0
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Assets\Api as AssetApi;
use Automattic\WooCommerce\Blocks\Package;


final class Vipps extends AbstractPaymentMethodType {
        private $localized=0;
	protected $name = 'vipps';
	private $asset_api;
	public function __construct( AssetApi $asset_api ) {
		$this->asset_api = $asset_api;
	}
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
	}

        // Register this payment method IOK 2020-08-10
        public static function register() {
            $container = Package::container();
            $container->register(Vipps::class,
                        function( $container ) {
                                $asset_api = $container->get(AssetApi::class); 
                                return new Vipps($asset_api);
                        });
            add_action( 'woocommerce_blocks_payment_method_type_registration', 
                        function ($registry) {
                            $container = Package::container();
                            $registry->register($container->get(Vipps::class ));
            });
        }

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}
	public function get_payment_method_script_handles() {

                // Cannot call the asset_api's register_script method because it takes a relative url, so we need to replicate
                // its functionality, in particular the dependencies. IOK 2020-08-10
                $dependencies = array('wc-vendors', 'wc-blocks' );
                $handle = 'wc-payment-method-vipps';
                wp_register_script($handle, plugins_url('js/wc-payment-method-vipps.js', __FILE__), 
                                    apply_filters( 'woocommerce_blocks_register_script_dependencies', $dependencies, $handle ),
                                    filemtime(dirname(__FILE__) . "/js/wc-payment-method-vipps.js"),true);


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

	public function get_payment_method_data() {
		return [
			'title'                    => $this->get_setting( 'title' ),
			'description'              => $this->get_setting( 'description' ),
                        'iconsrc'                  => apply_filters('woo_vipps_block_logo_url', plugins_url('../../img/vipps_logo_rgb.png', __FILE__))
		];
	}
}
