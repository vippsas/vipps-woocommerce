<?php

defined( 'ABSPATH' ) || exit;

/**
 * Responsible for writing to the WooCommerce log.
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class WC_Vipps_Recurring_Logger {

	public static $logger;
	const WC_LOG_FILENAME = 'woocommerce-gateway-vipps-mobilepay-recurring';

	/**
	 * @uses WC_Logger class
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 *
	 * @param $message
	 * @param null $start_time
	 * @param null $end_time
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( apply_filters( 'wc_vipps_recurring_logging', true, $message ) ) {
			if ( empty( self::$logger ) ) {
				self::$logger = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' )
					? new WC_Logger()
					: wc_get_logger();
			}

			$settings = get_option( 'woocommerce_vipps_recurring_settings' );

			if ( empty( $settings ) || ( isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) ) {
				return;
			}

			if ( $start_time !== null ) {
				$formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
				$end_time             = $end_time ?? current_time( 'timestamp' );
				$formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
				$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

				$log_entry = "\n" . '==== Vipps/MobilePay Recurring Version: ' . WC_VIPPS_RECURRING_VERSION . ' ====' . "\n";
				$log_entry .= '# Start Log ' . $formatted_start_time . "\n" . $message . "\n";
				$log_entry .= '# End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')' . "\n\n";
			} else {
				$log_entry = "\n" . '==== Vipps/MobilePay Recurring Version: ' . WC_VIPPS_RECURRING_VERSION . ' ====' . "\n";
				$log_entry .= $message . "\n\n";
			}

			WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' )
				? self::$logger->add( self::WC_LOG_FILENAME, $log_entry )
				: self::$logger->debug( $log_entry, [ 'source' => self::WC_LOG_FILENAME ] );
		}
	}
}
