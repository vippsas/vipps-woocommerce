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

		add_filter('wc_vipps_recurring_checkout_product_billing_period', [ __CLASS__, 'product_billing_period' ], 10, 2);
		add_filter('wc_vipps_recurring_checkout_product_billing_interval', [ __CLASS__, 'product_billing_interval' ], 10, 2);
	}

	public static function cart_has_subscription_product( $has_subscription_product, $cart_content ) {
		if ( class_exists( 'WCS_ATT_Cart' ) ) {
			foreach ( $cart_content as $cart_item ) {
				$is_wcs_att_sub = WCS_ATT_Cart::get_subscription_scheme( $cart_item );
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

	public static function product_billing_period( $period, $product ) {
		if ( class_exists( 'WCS_ATT_Product' ) ) {
			return WCS_ATT_Product::get_runtime_meta($product, 'subscription_period');
		}

		return $period;
	}

	public static function product_billing_interval( $interval, $product ) {
		if ( class_exists( 'WCS_ATT_Product' ) ) {
			return WCS_ATT_Product::get_runtime_meta($product, 'subscription_period_interval');
		}

		return $interval;
	}
}
