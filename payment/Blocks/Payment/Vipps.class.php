<?php
/**
 * Vipps payment gateway implementation for Gutenberg Blocks
 *
 * @package WooCommerce/Blocks
 * @since 3.0.0
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

final class Vipps extends AbstractPaymentMethodType {
	private $localized = 0;
	private static $store_api_payment_processor = null;
	protected $name = 'vipps';
	protected $payment_method_name = 'Vipps';

	/**
	 * Load gateway settings and register the Store API bridge when Blocks initializes this integration.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
		$this->payment_method_name = \WC_Gateway_Vipps::instance()->get_payment_method_name();
		$this->register_store_api_payment_processor();
	}

	/**
	 * Register once at priority 10. Setting a result status here lets WooCommerce's
	 * legacy fallback at priority 999 skip calling process_payment a second time.
	 */
	protected function register_store_api_payment_processor() {
		if ( self::$store_api_payment_processor ) {
			return;
		}

		self::$store_api_payment_processor = $this;

		add_action(
			'woocommerce_rest_checkout_process_payment_with_context',
			array( $this, 'process_gutenberg_checkout_payment' ),
			10,
			2
		);
	}

	// Register this payment method IOK 2020-08-10
	/**
	 * Register the integration with the Blocks payment-method registry.
	 */
	public static function register() {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new static() );
			}
		);
	}

	/**
	 * Expose the method to Blocks only when it is enabled in the gateway settings.
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Load the checkout component after shared gateway code; filemtime invalidates cached JavaScript.
	 */
	public function get_payment_method_script_handles() {

		$version = filemtime( dirname( __FILE__ ) . '/js/wc-payment-method-vipps.js' );
		$path = plugins_url( 'js/wc-payment-method-vipps.js', __FILE__ );
		$handle = 'wc-payment-method-vipps';
		$dependencies = array( 'wp-hooks', 'wp-element', 'vipps-gw' );

		wp_register_script( $handle, $path, $dependencies, $version, true );

		// Will not use wp_set_script_translations yet, it seems to be not fully compatible with Loco Translate etc. Instead use
		// old-fashioned localize-script IOK 2020-08-11
		// wp_set_script_translations( 'wc-payment-method-vipps', 'woo-vipps' );
		// VippsLocale is localized for vipps-gw instead of this, as it seems it does no longer work to do localize-script at this point.
		// IOK 2022-12-13

		return [ 'wc-payment-method-vipps' ];
	}

	/**
	 * Reuse the gateway shortcode so express checkout retains its existing markup and data attributes.
	 */
        public function get_express_checkout_button () {
            $context = 'checkout_gutenberg';
            if (is_cart()) {
                $context='cart_gutenberg';
            }
            ob_start();
            \Vipps::instance()->cart_express_checkout_button_html($context);
            $button = ob_get_clean();
            return $button;
        }

	/**
	 * Check cart support before exposing the merchant's express-checkout setting.
	 */
	public function show_express_checkout_button() {
		$gw = \Vipps::instance()->gateway();
		if ( ! $gw->cart_supports_express_checkout() ) return false;
		return $gw->show_express_checkout();
	}

	/**
	 * Process an existing Store API order through the gateway and expose its URL as
	 * widget payment details. The pre-process filter may supply a result instead of
	 * calling the gateway; subsequent filters may adapt the result, URL and reference.
	 * Exceptions are surfaced by WooCommerce as checkout errors. A successful result
	 * means payment initiation succeeded, not that payment has been captured.
	 * The browser suppresses the normal redirect only after accepting the widget handoff.
	 */
	public function process_gutenberg_checkout_payment( $context, $result ) {

		if ( empty( $context->payment_method ) || 'vipps' !== $context->payment_method ) {
			return;
		}

		$order = isset( $context->order ) ? $context->order : null;

		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
			throw new \Exception( $this->get_payment_method_error_message( __( '%s could not process the order.', 'woo-vipps' ) ) );
		}

		$gateway = $this->get_gutenberg_checkout_gateway();

		if ( ! $gateway ) {
			throw new \Exception( $this->get_payment_method_error_message( __( '%s is not available.', 'woo-vipps' ) ) );
		}

		$payment_result = apply_filters(
			'woo_vipps_gutenberg_checkout_process_payment_result',
			null,
			$context,
			$result,
			$gateway
		);

		if ( null === $payment_result ) {
			if ( ! is_callable( array( $gateway, 'process_payment' ) ) ) {
				throw new \Exception( $this->get_payment_method_error_message( __( '%s could not start the payment.', 'woo-vipps' ) ) );
			}

			$payment_result = $gateway->process_payment( $order->get_id() );
		}

		$payment_result = apply_filters(
			'woo_vipps_gutenberg_checkout_processed_payment_result',
			$payment_result,
			$context,
			$result,
			$gateway
		);

		if ( ! is_array( $payment_result ) ) {
			throw new \Exception( $this->get_payment_method_error_message( __( '%s returned an invalid payment response.', 'woo-vipps' ) ) );
		}

		if ( isset( $payment_result['result'] ) && 'success' !== $payment_result['result'] ) {
			$message = ! empty( $payment_result['message'] )
				? $payment_result['message']
				: $this->get_payment_method_error_message( __( '%s could not start the payment.', 'woo-vipps' ) );
			throw new \Exception( wp_strip_all_tags( $message ) );
		}

		$payment_url = apply_filters(
			'woo_vipps_gutenberg_checkout_payment_url',
			isset( $payment_result['redirect'] ) ? $payment_result['redirect'] : '',
			$payment_result,
			$context,
			$result,
			$gateway
		);

		if ( ! $payment_url ) {
			throw new \Exception( $this->get_payment_method_error_message( __( '%s did not return a payment URL.', 'woo-vipps' ) ) );
		}

		$payment_reference = apply_filters(
			'woo_vipps_gutenberg_checkout_payment_reference',
			$this->get_gutenberg_checkout_payment_reference( $payment_result, $order ),
			$payment_result,
			$context,
			$result,
			$gateway
		);

		$payment_details = array(
			'vippsPaymentUrl'       => esc_url_raw( $payment_url ),
			'vippsPaymentReference' => $payment_reference ? sanitize_text_field( $payment_reference ) : '',
		);

		if ( isset( $result->payment_details ) && is_array( $result->payment_details ) ) {
			$payment_details = array_merge( $result->payment_details, $payment_details );
		}

		$payment_details = apply_filters(
			'woo_vipps_gutenberg_checkout_payment_details',
			$payment_details,
			$payment_result,
			$context,
			$result,
			$gateway
		);

		$result->set_status( 'success' );
		$result->set_payment_details( $payment_details );
	}

	/**
	 * Resolve the gateway singleton, allowing the main plugin accessor as a compatibility fallback.
	 */
	protected function get_gutenberg_checkout_gateway() {
		if ( class_exists( '\WC_Gateway_Vipps' ) && is_callable( array( '\WC_Gateway_Vipps', 'instance' ) ) ) {
			return \WC_Gateway_Vipps::instance();
		}

		if ( class_exists( '\Vipps' ) && is_callable( array( '\Vipps', 'instance' ) ) ) {
			$vipps = \Vipps::instance();

			if ( is_callable( array( $vipps, 'gateway' ) ) ) {
				return $vipps->gateway();
			}
		}

		return null;
	}

	/**
	 * Prefer the returned payment reference, then known order metadata; a missing reference is optional.
	 */
	protected function get_gutenberg_checkout_payment_reference( $payment_result, $order ) {
		foreach ( array( 'paymentReference', 'reference', 'vippsPaymentReference' ) as $key ) {
			if ( ! empty( $payment_result[ $key ] ) ) {
				return $payment_result[ $key ];
			}
		}

		foreach ( array( '_vipps_payment_reference', '_vipps_reference', 'vipps_payment_reference' ) as $key ) {
			$reference = $order->get_meta( $key, true );

			if ( $reference ) {
				return $reference;
			}
		}

		return '';
	}

	/**
	 * Format customer-facing errors using the configured payment method name.
	 */
	protected function get_payment_method_error_message( $message ) {
		return sprintf( $message, $this->payment_method_name );
	}

	/**
	 * Expose localized labels, branding and express eligibility as vipps_data for the JS component.
	 */
	public function get_payment_method_data() {
		$logo = ( $this->payment_method_name == 'Vipps' ) ? plugins_url( '../../img/vipps-mark.svg', __FILE__ ) : plugins_url( '../../img/mobilepay-mark.png', __FILE__ );
		$brand = ( $this->payment_method_name == 'Vipps' ) ? 'vipps' : 'mobilepay';
		$language = substr( determine_locale(), 0, 2 );
		if ( ! in_array( $language, array( 'no', 'en', 'da', 'fi', 'sv' ), true ) ) {
			$language = 'en';
		}

		$data = [
			'title'                 => $this->payment_method_name,
			'description'           => $this->get_setting( 'description' ),
			'iconsrc'               => apply_filters( 'woo_vipps_block_logo_url', $logo ),
			'brand'                 => apply_filters( 'woo_vipps_block_widget_brand', $brand ),
			'language'              => apply_filters( 'woo_vipps_block_widget_language', $language ),
			'show_express_checkout' => $this->show_express_checkout_button(),
			'expressbutton'         => $this->get_express_checkout_button(),
		];

		return $data;
	}
}
