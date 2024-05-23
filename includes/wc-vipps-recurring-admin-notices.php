<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Admin_Notices {
	private static $instance;
	private $admin_notices;
	private $plugin_path;

	const TYPES = 'error,campaign,warning,info,success';

	private function __construct( $plugin_path ) {
		$this->admin_notices = new stdClass();
		$this->plugin_path   = $plugin_path;

		foreach ( explode( ',', self::TYPES ) as $type ) {
			$this->admin_notices->{$type} = [];
		}

		add_action( 'admin_init', [ &$this, 'action_admin_init' ] );
		add_action( 'admin_notices', [ &$this, 'action_admin_notices' ] );
	}

	public function action_admin_init(): void {
		$dismiss_name = filter_input( INPUT_GET, 'vipps_recurring_dismiss', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_string( $dismiss_name ) ) {
			if ( $dismiss_name === 'one-or-more' ) {
				set_transient( "vipps_recurring_dismissed_$dismiss_name", true, 30 * 24 * HOUR_IN_SECONDS );
			} else {
				update_option( "vipps_recurring_dismissed_$dismiss_name", true );
			}

			wp_die();
		}
	}

	public function error( $message, $dismiss_name = null, $dismiss_option = false ): void {
		$this->notice( 'error', $message, $dismiss_name, $dismiss_option );
	}

	public function warning( $message, $dismiss_name = null, $dismiss_option = false ): void {
		$this->notice( 'warning', $message, $dismiss_name, $dismiss_option );
	}

	public function success( $message, $dismiss_name = null, $dismiss_option = false ): void {
		$this->notice( 'success', $message, $dismiss_name, $dismiss_option );
	}

	public function info( $message, $dismiss_name = null, $dismiss_option = false ): void {
		$this->notice( 'info', $message, $dismiss_name, $dismiss_option );
	}

	public function campaign( $message, $dismiss_name = null, $dismiss_option = false, $logo = null, $theme = null ): void {
		$this->notice( 'campaign', $message, $dismiss_name, $dismiss_option, $logo, $theme );
	}

	private function notice( $type, $message, $dismiss_name, $dismiss_option, $logo = null, $theme = null ): void {
		$notice                 = new stdClass();
		$notice->message        = $message;
		$notice->dismiss_name   = $dismiss_name;
		$notice->dismiss_option = $dismiss_option;
		$notice->logo           = $logo;
		$notice->theme          = $theme;

		$this->admin_notices->{$type}[] = $notice;
	}

	public function action_admin_notices(): void {
		foreach ( explode( ',', self::TYPES ) as $type ) {
			foreach ( $this->admin_notices->{$type} as $admin_notice ) {
				$option = sanitize_title( $admin_notice->dismiss_name );

				$dismiss_url = add_query_arg( [
					'vipps_recurring_dismiss' => sanitize_title( $admin_notice->dismiss_name )
				], admin_url() );

				$gateway = WC_Vipps_Recurring::get_instance()->gateway();

				$logo_url = $admin_notice->logo ?? 'assets/images/vipps-mobilepay-logo.png';
				$logo     = plugins_url( $logo_url, $this->plugin_path );
				$img_html = "<img src='$logo' alt=''>";

				if ( ! get_option( "vipps_recurring_dismissed_{$option}" ) && ! get_transient( "vipps_recurring_dismissed_{$option}" ) ) {
					?>
					<div class="ui message <?php echo $type; ?> notice notice-vipps-recurring <?php echo $gateway->brand; ?> notice-<?php echo $type;
					if ( $admin_notice->theme ) {
						echo " notice-vipps-recurring--$admin_notice->theme";
					}

					if ( $admin_notice->dismiss_option ) {
						echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
					}
					?>">
						<div class='notice-vipps-recurring__inner'>
							<?php echo $img_html; ?>
							<p><?php echo $admin_notice->message; ?></p>
						</div>
					</div>
					<?php
				}
			}
		}
	}

	public static function get_instance( $plugin_path ): WC_Vipps_Recurring_Admin_Notices {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self( $plugin_path );
		}

		return self::$instance;
	}
}
