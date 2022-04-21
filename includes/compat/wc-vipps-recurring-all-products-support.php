<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_All_Products_Support {
	/**
	 * Initialize Vipps Recurring All Products Support class.
	 */
	public static function init() {
		add_filter( 'wc_vipps_recurring_cart_has_subscription_product', [
			__CLASS__,
			'cart_has_subscription_product'
		], 10, 2 );

		add_filter( 'wc_vipps_recurring_item_is_subscription', [ __CLASS__, 'item_is_subscription' ], 10, 2 );
	}

	public static function cart_has_subscription_product( $has_subscription_product, $cart_content ) {
		foreach ( $cart_content as $values ) {
			$is_wcs_att_sub = WCS_ATT_Cart::get_subscription_scheme( $values );
			$has_subscription_product = ! is_bool( $is_wcs_att_sub );
		}

		return $has_subscription_product;
	}

	public static function item_is_subscription( $item_is_subscription, $item ) {
		$is_wcs_att_sub = WCS_ATT_Cart::get_subscription_scheme( $item );

		return ! is_bool( $is_wcs_att_sub );
	}
}
