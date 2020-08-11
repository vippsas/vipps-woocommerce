<?php
/**
 * Vipps payment gateway implementation for Gutenberg Blocks
 *
 * @package WooCommerce/Blocks
 * @since 3.0.0
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Assets\Api;


final class Vipps extends AbstractPaymentMethodType {
        private $localized=0;
	protected $name = 'vipps';
	private $asset_api;
	public function __construct( Api $asset_api ) {
		$this->asset_api = $asset_api;
	}
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
	}
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}
	public function get_payment_method_script_handles() {
                wp_register_script('wc-payment-method-vipps', plugins_url('js/wc-payment-method-vipps.js', __FILE__), 
                                    array('wc-blocks-registry', 'wc-settings', 'wp-html-entities', 'wp-i18n', 'wp-polyfill'), 
                                    filemtime(dirname(__FILE__) . "/js/wc-payment-method-vipps.js"));
                // Will not use wp_set_script_translations yet, it seems to be not fully compatible with Loco Translate etc IOK 2020-08-11
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
			'description'              => $this->get_setting( 'description' )
		];
	}
}
