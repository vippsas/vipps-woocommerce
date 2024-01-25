<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_All_Products_Support {
	/**
	 * Initialize Vipps/MobilePay Recurring All Products Support class.
	 */
	public static function init() {
		add_filter( 'wc_vipps_recurring_cart_has_subscription_product', [
			__CLASS__,
			'cart_has_subscription_product'
		], 10, 2 );

		add_filter( 'wc_vipps_recurring_item_is_subscription', [ __CLASS__, 'item_is_subscription' ], 10, 2 );
	}

	public static function cart_has_subscription_product( $has_subscription_product, $cart_content ) {
		if ( class_exists( 'WCS_ATT_Cart' ) ) {
			foreach ( $cart_content as $values ) {
				$is_wcs_att_sub = WCS_ATT_Cart::get_subscription_scheme( $values );
				if ( $is_wcs_att_sub !== null ) {
					$has_subscription_product = ! is_bool( $is_wcs_att_sub );
				}
			}
		}

		return $has_subscription_product;
	}

	public static function item_is_subscription( $item_is_subscription, $item ) {
		if ( class_exists( 'WCS_ATT_Cart' ) && ! $item_is_subscription ) {
			$is_wcs_att_sub = WCS_ATT_Cart::get_subscription_scheme( $item );

			return ! is_bool( $is_wcs_att_sub );
		}

		return $item_is_subscription;
	}
}
