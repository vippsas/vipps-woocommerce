<?php
/* Recurring gateway React settings. Copyright (c) WP-Hosting AS. */

defined( 'ABSPATH' ) || exit;

class VippsRecurringAdminSettings {
	private const ACTION = 'vipps_recurring_save_settings';
	private const NONCE = 'vipps_recurring_settings';
	private static ?VippsRecurringAdminSettings $instance = null;

	public static function instance(): VippsRecurringAdminSettings {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function gateway(): WC_Gateway_Vipps_Recurring {
		return WC_Vipps_Recurring::get_instance()->gateway();
	}

	/** Keep this action separate from Recurring's order-action AJAX nonce. */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'ajax_save' ] );
		add_action( 'in_admin_header', [ $this, 'suppress_notices' ], 9999 );
	}

	/** Match the Payment and Login settings screens: the app renders its own notices. */
	public function suppress_notices(): void {
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'vipps-mobilepay_page_vipps_recurring__settings_menu' ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient privileges', 'woo-vipps' ) );
		}
		echo '<div class="wrap vipps-recurring-admin-settings-page"><div class="wp-header-end"></div><div id="vipps-recurring-react-ui"></div></div>';
	}

	/** The existing Recurring admin assets load globally; this bundle only loads on its settings screen. */
	public function enqueue( $hook_suffix ): void {
		if ( $hook_suffix !== 'vipps-mobilepay_page_vipps_recurring__settings_menu' || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$directory = dirname( __DIR__ ) . '/settings/dist';
		$script = $directory . '/plugin.js';
		$style = $directory . '/plugin.css';
		if ( ! file_exists( $script ) ) {
			return;
		}
		wp_enqueue_script( 'vipps-recurring-react-ui', plugins_url( 'dist/plugin.js', __FILE__ ), [ 'wp-element' ], filemtime( $script ), true );
		if ( file_exists( $style ) ) {
			wp_enqueue_style( 'vipps-recurring-react-ui', plugins_url( 'dist/plugin.css', __FILE__ ), [], filemtime( $style ) );
		}
		wp_localize_script( 'vipps-recurring-react-ui', 'VippsRecurringReactSettings', $this->bootstrap() );
	}

	/** Translate the live WooCommerce field definitions to a small, read-only UI schema. */
	private function sections( WC_Gateway_Vipps_Recurring $gateway ): array {
		$sections = [];
		$section = 'general';
		$titles = [
			'title_brand' => 'general',
			'title_checkout' => 'checkout',
			'title_api' => 'keys',
			'title_orders' => 'orders',
			'title_cron' => 'cron',
			'title_developer' => 'advanced',
			'title_test_api' => 'test_keys',
		];
		foreach ( $gateway->get_form_fields() as $key => $field ) {
			if ( isset( $titles[ $key ] ) ) {
				$section = $titles[ $key ];
				if ( ! isset( $sections[ $section ] ) ) {
					$sections[ $section ] = [ 'id' => $section, 'title' => wp_strip_all_tags( $field['title'] ?? '' ), 'description' => wp_kses_post( $field['description'] ?? '' ), 'fields' => [] ];
				}
				continue;
			}
			if ( ! isset( $sections[ $section ] ) ) {
				$sections[ $section ] = [ 'id' => $section, 'title' => __( 'General', 'woo-vipps' ), 'description' => '', 'fields' => [] ];
			}
			if ( ( $field['type'] ?? '' ) === 'title' ) {
				continue;
			}
			$type = $field['type'] ?? 'text';
			$options = [];
			if ( $type === 'page_dropdown' ) {
				// wp_dropdown_pages() uses -1 for its "none" option by default.
				$options['-1'] = wp_strip_all_tags( $field['show_option_none'] ?? __( 'Default', 'woo-vipps' ) );
				foreach ( get_pages() as $page ) {
					$options[ (string) $page->ID ] = wp_strip_all_tags( $page->post_title );
				}
			} elseif ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $value => $label ) {
					$options[ (string) $value ] = wp_strip_all_tags( $label );
				}
			}
			$sections[ $section ]['fields'][ $key ] = [
				'type' => $type,
				'title' => wp_strip_all_tags( $field['title'] ?? $key ),
				'label' => wp_strip_all_tags( $field['label'] ?? '' ),
				'description' => wp_kses_post( ( $field['description'] ?? '' ) . ( is_string( $field['desc_tip'] ?? null ) ? ' ' . $field['desc_tip'] : '' ) ),
				'default' => $field['default'] ?? '',
				'disabled' => ! empty( $field['disabled'] ),
				'options' => $options,
			];
		}
		return array_values( $sections );
	}

	/** Reads effective values from the one WooCommerce gateway option. */
	public function bootstrap(): array {
		$gateway = $this->gateway();
		$gateway->init_settings();
		$gateway->init_form_fields();
		$values = [];
		foreach ( $gateway->get_form_fields() as $key => $field ) {
			if ( ( $field['type'] ?? '' ) !== 'title' ) {
				$values[ $key ] = $key === 'test_mode' && WC_VIPPS_RECURRING_TEST_MODE ? 'yes' : $gateway->get_option( $key );
			}
		}
		return [
			'values' => $values,
			'sections' => $this->sections( $gateway ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'action' => self::ACTION,
			'nonce' => wp_create_nonce( self::NONCE ),
			'native_url' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vipps_recurring' ),
			'translations' => [
				'page_title' => __( 'Recurring Payments', 'woo-vipps' ),
				'save_changes' => __( 'Save changes', 'woo-vipps' ),
				'settings_saved' => __( 'Settings saved', 'woo-vipps' ),
				'save_failed' => __( 'Could not save settings. Please try again.', 'woo-vipps' ),
				'connection_failed' => __( 'Settings saved, but the Vipps MobilePay API could not be authenticated.', 'woo-vipps' ),
				'unsaved_changes' => __( 'You have unsaved changes.', 'woo-vipps' ),
				'unsupported_field' => __( 'This additional field is available on the WooCommerce settings page.', 'woo-vipps' ),
				'open_woocommerce' => __( 'Open WooCommerce settings', 'woo-vipps' ),
			],
		];
	}

	/** Submit through WC_Settings_API, preserving gateway validation, filters and side effects. */
	public function ajax_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient privileges', 'woo-vipps' ) ], 403 );
		}
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Your session has expired. Reload the page and try again.', 'woo-vipps' ) ], 403 );
		}
		// WooCommerce's field validators remove the WordPress-added slashes once.
		$values = isset( $_POST['values'] ) && is_array( $_POST['values'] ) ? $_POST['values'] : null;
		if ( $values === null ) {
			wp_send_json_error( [ 'message' => __( 'Invalid settings data.', 'woo-vipps' ) ], 400 );
		}
		$gateway = $this->gateway();
		$gateway->init_settings();
		// Conditional fields must be available on the first save that enables test mode or Checkout.
		$original = $gateway->settings;
		foreach ( [ 'test_mode', 'checkout_enabled' ] as $key ) {
			if ( isset( $values[ $key ] ) && in_array( $values[ $key ], [ 'yes', 'no' ], true ) ) {
				$gateway->settings[ $key ] = $values[ $key ];
			}
		}
		$gateway->init_form_fields();
		$fields = $gateway->get_form_fields();
		$gateway->settings = $original;

		foreach ( $values as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) || ( $fields[ $key ]['type'] ?? '' ) === 'title' || ! is_scalar( $value ) || ! empty( $fields[ $key ]['disabled'] ) ) {
				wp_send_json_error( [ 'message' => __( 'An unknown or unavailable setting was submitted.', 'woo-vipps' ) ], 400 );
			}
			$type = $fields[ $key ]['type'] ?? 'text';
			if ( $type === 'checkbox' && ! in_array( $value, [ 'yes', 'no' ], true ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid checkbox value.', 'woo-vipps' ) ], 400 );
			}
			if ( $type === 'select' && ! isset( $fields[ $key ]['options'][ $value ] ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid selection.', 'woo-vipps' ) ], 400 );
			}
		}
		$post_data = [];
		foreach ( $fields as $key => $field ) {
			if ( ( $field['type'] ?? '' ) === 'title' || ! empty( $field['disabled'] ) ) {
				continue;
			}
			// Newly revealed fields were absent from the old UI schema. Re-post
			// their stored values with the same slash layer as a normal WP POST.
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : ( isset( $original[ $key ] ) ? wp_slash( $original[ $key ] ) : null );
			if ( $value === null || ( $field['type'] ?? '' ) === 'checkbox' && $value === 'no' ) {
				continue;
			}
			$post_data[ 'woocommerce_vipps_recurring_' . $key ] = $value;
		}
		$gateway->set_post_data( $post_data );
		$saved = $gateway->process_admin_options();
		$errors = $gateway->get_errors();
		// update_option() returns false for an unchanged option, which is still
		// a successful save. Compare the gateway's final values with storage.
		$persisted = get_option( $gateway->get_option_key(), [] );
		$saved = $saved || $gateway->settings === $persisted;
		$data = $this->bootstrap();
		$data['saved'] = (bool) $saved;
		$data['form_errors'] = $errors;
		// Recurring authenticates during process_admin_options(); that result is
		// separate from whether WooCommerce persisted the settings.
		$data['connection_ok'] = $gateway->get_option( 'enabled' ) !== 'yes' || (bool) get_option( WC_Vipps_Recurring_Helper::OPTION_CONFIGURED );
		wp_send_json_success( $data );
	}
}
