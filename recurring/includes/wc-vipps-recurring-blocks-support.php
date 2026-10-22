<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Vipps Recurring payment method integration for Gutenberg Blocks
 *
 * @since 2.6.0
 */
final class WC_Vipps_Recurring_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'vipps_recurring';

	private ?WC_Gateway_Vipps_Recurring $gateway = null;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = \WC_Vipps_Recurring_Helper::get_settings();
	}

	private function gateway(): ?WC_Gateway_Vipps_Recurring {
		if ( $this->gateway ) {
			return $this->gateway;
		}

		$this->gateway = WC_Vipps_Recurring::get_instance()->gateway();

		return $this->gateway;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		return $this->gateway()->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$version      = filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . "/assets/js/vipps-recurring-payment-method-block.js" );
		$path         = WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/js/vipps-recurring-payment-method-block.js';
		$handle       = 'wc-payment-method-vipps_recurring';

		// The payment integration owns the reusable SDK controller, even when its gateway is disabled.
		wp_register_script( 'wc-payment-method-vipps', plugins_url( 'Blocks/Payment/js/wc-payment-method-vipps.js', WC_VIPPS_PAYMENT_MAIN_FILE ),
			[ 'wp-hooks', 'wp-element', 'vipps-gw' ], filemtime( dirname( WC_VIPPS_PAYMENT_MAIN_FILE ) . '/Blocks/Payment/js/wc-payment-method-vipps.js' ), true );
		$dependencies = [ 'wp-hooks', 'wp-i18n', 'wc-payment-method-vipps', 'vipps-recurring-express' ];

		wp_register_script( $handle, $path, $dependencies, $version, true );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'woo-vipps', dirname(WC_VIPPS_MAIN_FILE) . '/languages/' );
		}

		return [ $handle ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return [
			'title'       => $this->gateway()->title,
			'description' => $this->gateway()->description,
			'logo'        => apply_filters(
				'wc_vipps_recurring_checkout_logo_url',
				WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/' . $this->gateway()->brand . '-mark.svg',
				$this->gateway()->brand
			),
			'brand'       => $this->gateway()->brand,
			'language'    => Vipps::instance()->resolve_html_button_language( Vipps::instance()->get_html_button_attrs_for_context( 'checkout_gutenberg' )['language'] ?? 'store' ),
			'expressbutton' => WC_Vipps_Recurring_Express_Checkout::get_instance()->button_html( is_cart() ? 'cart_gutenberg' : 'checkout_gutenberg' ),
			'show_express_checkout' => WC_Vipps_Recurring_Express_Checkout::get_instance()->show_cart_button( is_cart() ? 'cart' : 'checkout' ),
			'supports'    => $this->get_supported_features(),
		];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways[ $this->name ] ) ) {
			$gateway = $gateways[ $this->name ];

			return array_filter( $gateway->supports, [ $gateway, 'supports' ] );
		}

		return [];
	}
}
