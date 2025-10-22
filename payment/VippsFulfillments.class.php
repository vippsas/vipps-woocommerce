<?php
/*
    This class contains the logic for handling WooCommerce fulfillments. LP 2025-10-22


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class VippsFulfillments {
    public static function init() {
        add_action('woocommerce_fulfillment_before_fulfill', array('VippsFulfillments', 'woocommerce_fulfillment_before_fulfill')); // FIXME: is prio important here? Idk. LP 2025-10-08
        add_action('woocommerce_fulfillment_before_delete', array('VippsFulfillments', 'woocommerce_fulfillment_before_delete')); // FIXME: is prio important here? Idk. LP 2025-10-08
    }

    /** Returns failure message to fulfillment admin interface by throwing FulfillmentException. LP 2025-10-15 */
    public static function fulfillment_fail($msg = "") {
            if (class_exists('Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException')) {
                throw new Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException($msg);
            }
            global $Vipps;
            $Vipps->gateway()->log(__('FulfillmentException does not exist', 'woo-vipps'), 'debug');
            throw new Exception($msg);
        }

    /** Disable deletion for vipps fulfillments, since we capture fulfillments and don't want to refund (they can't be recaptured). LP 2025-10-22 */
    public static function woocommerce_fulfillment_before_delete($fulfillment) {
        static::fulfillment_fail(sprintf(__('Fulfillment is already captured at %1$s, cannot delete this fulfillment.', 'woo-vipps'), Vipps::companyName()));
    }

    /** Partially capture Vipps order using the fulfilled items from woo. LP 2025-10-08
     *
     *  This hook will also run on fulfillments edits, after the hook '...before_update'
     *  therefore here we loop over all fulfillments and only capture if the total sum is more than what is already captured. 
     *  Else stop fulfillment with failure message. 
     */
    public static function woocommerce_fulfillment_before_fulfill($new_fulfillment) {
        error_log("LP running woocommerce_fulfillment_before_fulfill");

        $order = $new_fulfillment->get_order();
        if (!$order) static::fulfillment_fail(__('Something went wrong, could not find order', 'woo-vipps'));

        // Get all previous fulfillments for this order
        try {
            $data_store = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore::class);
            $fulfillments = $data_store->read_fulfillments('WC_Order', $order->get_id());
        } catch (Exception $e) {
            global $Vipps;
            $Vipps->gateway()->log(sprintf(__('Could not get previous fulfillments for order %1$s: ', 'woo-vipps'), $order->get_id()) . $e->getMessage(), 'error');
            static::fulfillment_fail(__('Could not find fulfillments for the order. Please check the logs for more information.', 'woo-vipps'));
        }

        // Add new to array of all fulfillments to first position, to stop duplicate captures for cases with updated/edited fulfillments. LP 2025-10-15
        array_unshift($fulfillments, $new_fulfillment);
        error_log("LP fulfill_update total number of fulfillments is " . count($fulfillments));

        $sum = 0;
        $new_is_handled = false;
        foreach ($fulfillments as $fulfillment) {
            $items = $fulfillment->get_items();

            // Make sure to don't capture duplicate if this is a fulfillment update, by skipping the old one. LP 2025-10-15
            if ($fulfillment->get_id() === $new_fulfillment->get_id()) {
                error_log("LP This is the new fulfillment, has already been handled (update/edit fulfillment)?: $new_is_handled");
                if ($new_is_handled) continue;
                $new_is_handled = true;
            }


            foreach ($items as $item) {
                $item_id = $item['item_id'];
                $item_quantity = $item['qty'];
                $order_item = $order->get_item($item_id);
                if (!$order_item) static::fulfillment_fail('Something went wrong, could not find fulfillment item'); // how did this happen

                // Calculate unit price of product. LP 2025-10-08
                $total_no_tax = $order_item->get_total() ?: "0";
                $tax = $order_item->get_total_tax() ?: "0";
                $total = $tax + $total_no_tax;
                $product_quantity = $order_item->get_quantity();
                $unit_price = $total / $product_quantity;

                $item_sum = $unit_price * $item_quantity;
                $sum += $item_sum;
            }
        }

        $sum = round(wc_format_decimal($sum, '') * 100);
        error_log('LP before_fulfill sum: ' . print_r($sum, true));
        $captured = intval($order->get_meta('_vipps_captured'));
        error_log('LP before_fulfill captured: ' . print_r($captured, true));

        if ($sum === $captured) return $fulfillment;

        // If new sum is less than already captured, stop and return failure message to admin. We don't want to refund here. LP 2025-10-15
        /* translators: %1$s = this payment method company name */
        if ($sum < $captured) static::fulfillment_fail(sprintf(__('New capture sum is less than what is already captured at %1$s, cannot fulfill less products than before', 'woo-vipps'), Vipps::companyName()));

        // New sum is greater than already captured, send capture. LP 2025-10-15
        $to_capture = $sum - $captured;
        error_log('LP before_fulfill new capture' . print_r($to_capture, true));
        global $Vipps;
        $ok = $Vipps->gateway()->capture_payment($order, $to_capture);
        if (!$ok) {
            error_log("LP before_fulfill capture was not ok!");
            /* translators: %1$s = number, %2$s = this payment method company name */
            static::fulfillment_fail(sprintf(__('Could not capture the fulfillment capture difference of %1$s at %2$s. Please check the logs for more information.', 'woo-vipps'), $to_capture, Vipps::companyName()));
        }
        error_log("LP before_fulfillment capture ok! Accepting fulfillment");
        return $fulfillment;
    }
}
