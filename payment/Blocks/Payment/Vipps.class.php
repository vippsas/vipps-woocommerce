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

	public function initialize() {
		$this->settings = get_option( 'woocommerce_vipps_settings', [] );
		$this->payment_method_name = \WC_Gateway_Vipps::instance()->get_payment_method_name();
		$this->log_gutenberg_checkout( 'Initialized Vipps Blocks payment method.', array(
			'payment_method_name' => $this->payment_method_name,
			'name'                => $this->name,
		) );
		$this->register_store_api_payment_processor();
	}

	protected function register_store_api_payment_processor() {
		if ( self::$store_api_payment_processor ) {
			return;
		}

		self::$store_api_payment_processor = $this;

		$this->log_gutenberg_checkout( 'Registering Store API payment processor.' );

		add_action(
			'woocommerce_rest_checkout_process_payment_with_context',
			array( $this, 'process_gutenberg_checkout_payment' ),
			10,
			2
		);
	}

	// Register this payment method IOK 2020-08-10
	public static function register() {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new static() );
			}
		);
	}

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

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

	public function get_express_checkout_button() {
		$button = \Vipps::instance()->express_checkout_button_shortcode();
		return $button;
	}

	public function show_express_checkout_button() {
		$gw = \Vipps::instance()->gateway();
		if ( ! $gw->cart_supports_express_checkout() ) return false;
		return $gw->show_express_checkout();
	}

	public function process_gutenberg_checkout_payment( $context, $result ) {
		$this->log_gutenberg_checkout( 'Store API payment hook invoked.', array(
			'context_payment_method' => isset( $context->payment_method ) ? $context->payment_method : null,
			'has_order'              => isset( $context->order ),
			'payment_data'           => isset( $context->payment_data ) ? $context->payment_data : null,
		) );

		if ( empty( $context->payment_method ) || 'vipps' !== $context->payment_method ) {
			$this->log_gutenberg_checkout( 'Ignoring Store API payment hook for non-Vipps method.' );
			return;
		}

		$order = isset( $context->order ) ? $context->order : null;

		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
			$this->log_gutenberg_checkout( 'Missing or invalid order in Store API payment context.', array(), 'error' );
			throw new \Exception( $this->get_payment_method_error_message( __( '%s could not process the order.', 'woo-vipps' ) ) );
		}

		$this->log_gutenberg_checkout( 'Processing Store API payment for order.', array(
			'order_id' => $order->get_id(),
		) );

		$gateway = $this->get_gutenberg_checkout_gateway();

		if ( ! $gateway ) {
			$this->log_gutenberg_checkout( 'Gateway lookup failed.', array(), 'error' );
			throw new \Exception( $this->get_payment_method_error_message( __( '%s is not available.', 'woo-vipps' ) ) );
		}

		$this->log_gutenberg_checkout( 'Gateway lookup succeeded.', array(
			'gateway_class' => get_class( $gateway ),
		) );

		$payment_result = apply_filters(
			'woo_vipps_gutenberg_checkout_process_payment_result',
			null,
			$context,
			$result,
			$gateway
		);

		$this->log_gutenberg_checkout( 'Payment result after pre-process filter.', array(
			'payment_result' => $payment_result,
		) );

		if ( null === $payment_result ) {
			if ( ! is_callable( array( $gateway, 'process_payment' ) ) ) {
				$this->log_gutenberg_checkout( 'Gateway has no callable process_payment method.', array(), 'error' );
				throw new \Exception( $this->get_payment_method_error_message( __( '%s could not start the payment.', 'woo-vipps' ) ) );
			}

			$this->log_gutenberg_checkout( 'Calling gateway process_payment.', array(
				'order_id' => $order->get_id(),
			) );

			$payment_result = $gateway->process_payment( $order->get_id() );
		}

		$this->log_gutenberg_checkout( 'Payment result before post-process filter.', array(
			'payment_result' => $payment_result,
		) );

		$payment_result = apply_filters(
			'woo_vipps_gutenberg_checkout_processed_payment_result',
			$payment_result,
			$context,
			$result,
			$gateway
		);

		$this->log_gutenberg_checkout( 'Payment result after post-process filter.', array(
			'payment_result' => $payment_result,
		) );

		if ( ! is_array( $payment_result ) ) {
			$this->log_gutenberg_checkout( 'Payment result is not an array.', array(
				'payment_result' => $payment_result,
			), 'error' );
			throw new \Exception( $this->get_payment_method_error_message( __( '%s returned an invalid payment response.', 'woo-vipps' ) ) );
		}

		if ( isset( $payment_result['result'] ) && 'success' !== $payment_result['result'] ) {
			$this->log_gutenberg_checkout( 'Payment result was not successful.', array(
				'payment_result' => $payment_result,
			), 'error' );
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

		$this->log_gutenberg_checkout( 'Payment URL after filter.', array(
			'payment_url'    => $payment_url,
			'redirect_value' => isset( $payment_result['redirect'] ) ? $payment_result['redirect'] : null,
		) );

		if ( ! $payment_url ) {
			$this->log_gutenberg_checkout( 'Payment URL missing after successful payment result.', array(
				'payment_result' => $payment_result,
			), 'error' );
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

		$this->log_gutenberg_checkout( 'Payment reference after filter.', array(
			'payment_reference' => $payment_reference,
		) );

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

		$this->log_gutenberg_checkout( 'Setting Store API payment result.', array(
			'payment_details' => $payment_details,
		) );

		$result->set_status( 'success' );
		$result->set_payment_details( $payment_details );
	}

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

	protected function get_payment_method_error_message( $message ) {
		return sprintf( $message, $this->payment_method_name );
	}

	protected function log_gutenberg_checkout( $message, $context = array(), $level = 'debug' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log(
				$level,
				$message,
				array(
					'source'  => 'woo-vipps-gutenberg-checkout',
					'context' => $context,
				)
			);
			return;
		}

		if ( function_exists( 'error_log' ) ) {
			error_log( '[woo-vipps-gutenberg-checkout] ' . $message . ' ' . wp_json_encode( $context ) );
		}
	}

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

		$this->log_gutenberg_checkout( 'Providing Blocks payment method data.', $data );

		return $data;
	}
}
