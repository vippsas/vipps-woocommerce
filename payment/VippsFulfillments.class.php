<?php
/*
    This class contains the logic for handling WooCommerce fulfillments. We will capture fulfilled order items. LP 2025-10-22


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
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class VippsFulfillments {
    private $gateway;

    public function __construct($gateway) {
        $this->gateway = $gateway;
    }

    public function register_hooks() {
        add_action('woocommerce_fulfillment_before_fulfill', [$this, 'woocommerce_fulfillment_before_fulfill']);
        add_action('woocommerce_fulfillment_before_delete', [$this, 'woocommerce_fulfillment_before_delete']);
    }

    /** Whether the plugin has enabled fulfillment support. LP 2025-10-22 */
    public function is_enabled() {
        return $this->is_supported() && $this->gateway->get_option('fulfillments_enabled') == 'yes';
    }


    /** Disable deletion for vipps fulfillments, since we capture fulfillments and don't want to refund (they can't be recaptured). LP 2025-10-22 */
    public function woocommerce_fulfillment_before_delete($fulfillment) {
        error_log("LP woocommerce_fulfillment_before_delete. Stopping...");
        $this->fulfillment_fail(sprintf(__('Fulfillment is already captured at %1$s, cannot delete this fulfillment.', 'woo-vipps'), Vipps::CompanyName()));
    }

    /** Partially capture Vipps order using the fulfilled items from woo. LP 2025-10-08
     *
     *  This hook will also run on fulfillments edits, after the hook '...before_update'
     *  therefore here we loop over all fulfillment items and stop if the new fulfill sum is less than what is already captured at Vipps MobilePay. LP 2025-10-31
     */
    public function woocommerce_fulfillment_before_fulfill($fulfillment) {
        error_log("LP running woocommerce_fulfillment_before_fulfill");

        $order = $fulfillment->get_order();
        if (!$order) {
            $this->fulfillment_fail(__('Something went wrong, could not find order', 'woo-vipps'));
        }

        // This is handled in gateway->capture_payment(), but might as well return early here if not Vipps MobilePay. LP 2025-10-27
        $payment_method = $order->get_payment_method();
        error_log('LP before_fulfill payment_method: ' . print_r($payment_method, true));
        if ($payment_method != 'vipps') {
            return $fulfillment;
        }

        // Stop if the order amounts does not coincide with the sum of its items, this could mean there
        // was a refund or other outside WP (e.g. refund through business portal) LP 2025-11-07
        if (!$this->gateway->order_meta_coincides_with_items_meta($order)) {
            $this->gateway->log(sprintf(__('The order meta data does not coincide with the sums of the order items\' meta data, we can\'t process this refund correctly. There may have been a refund through the %1$s business portal', 'woo-vipps'), Vipps::CompanyName()), 'error');
            $this->fulfillment_fail(sprintf(__('Cannot refund through %1$s - status is not clear, there may have been a refund through the %2$s business portal.', 'woo-vipps'), $this->gateway->get_payment_method_name(), Vipps::CompanyName()));
        }

        // Don't process anything further if the order is entirely captured already,
        // the meta '_vipps_capture_remaining' might be unset so we need to calculate it instead. LP 2025-11-07
        $capture_remaining = intval($order->get_meta('_vipps_amount')) -
            intval($order->get_meta('_vipps_captured')) -
            intval($order->get_meta('_vipps_noncapturable'));
        error_log('LP before_fulfill capture_remaining: ' . print_r($capture_remaining, true));
        if ($capture_remaining <= 0) {
            return $fulfillment;
        }

        $currency = $order->get_currency();

        $to_capture = 0;
        $item_capture_table = []; // store new capture sum for each item for the purpose of updating metas when we know capture success. LP 2025-10-31
        foreach ($fulfillment->get_items() as $item) {
            $item_id = $item['item_id'];
            error_log('LP before_fulfill item_id: ' . print_r($item_id, true));
            $fulfill_quantity = $item['qty'];
            $order_item = $order->get_item($item_id);
            if (!$order_item) {
                $this->fulfillment_fail('Something went wrong, could not find fulfillment item'); // how did this happen
            }
            error_log('LP before_fulfill item_name: ' . print_r($order_item->get_name(), true));
            $fulfill_sum = (int) $order->get_item_total($order_item, true, false) * $fulfill_quantity * 100;
            error_log('LP before_fulfill fulfill_sum: ' . print_r($fulfill_sum, true));
            $item_name = $order_item->get_name();
            error_log('LP before_fulfill item_name: ' . print_r($item_name, true));

            // Stop if user tries to remove already-captured amounts for this order item (fulfillment edits). LP 2025-10-31
            $captured = intval($order_item->get_meta('_vipps_item_captured'));
            if ($fulfill_sum < $captured) {
                /* translators: %1 = item product name, %2 = company name */
                $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is less than what is already captured at %2$s, cannot fulfill less products than before', 'woo-vipps'), $item_name, Vipps::CompanyName()));
            }

            // Stop if there is not enough outstanding amount for the item to capture this fulfillment. LP 2025-11-07
            $order_item_sum = (int) $order->get_item_total($order_item, true, false) * $order_item->get_quantity() * 100;
            $noncapturable = intval($order_item->get_meta('_vipps_item_noncapturable'));
            $item_outstanding = $order_item_sum - $noncapturable - $captured;
            if ($fulfill_sum > $item_outstanding) {
                $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is more than is available to capture at %2$s.', 'woo_vipps'), $item_name, Vipps::CompanyName()));
            }

            $item_capture_table[$item_id] = $fulfill_sum + $captured;
            $to_capture += $fulfill_sum;
        }

        error_log('LP before_fulfill to_capture ' . print_r($to_capture, true));
        if ($to_capture <= 0) {
            error_log("LP before_fulfill new capture is zero or below, not sending anything to capture");
            return $fulfillment;
        }

        $ok = $this->gateway->capture_payment($order, $to_capture);
        if (!$ok) {
            error_log("LP before_fulfill capture was not ok!");
            /* translators: %1 = number, %2 = currency string, %3 = this payment method name */
            $this->fulfillment_fail(sprintf(__('Could not capture the fulfillment of %1$s %2$s at %3$s. Please check the logs for more information.', 'woo-vipps'), $to_capture, $currency, $this->gateway->get_payment_method_name()));
        }

        error_log("LP before_fulfillment capture ok! Updating item metas, then accepting fulfillment");
        // Everything ok, now we need to update captured meta for each item. LP 2025-10-31
        foreach ($item_capture_table as $item_id => $new_sum) {
            $item = $order->get_item($item_id);
            if ($new_sum) {
                $item->update_meta_data('_vipps_item_captured', $new_sum);
                $item->save_meta_data();
            }
        }

        return $fulfillment;
    }

    /** Returns failure message to fulfillment admin interface by throwing FulfillmentException. LP 2025-10-15 */
    public function fulfillment_fail($msg = "") {
        if (class_exists('Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException')) {
            throw new Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException($msg);
        }
        $this->gateway->log(__('FulfillmentException does not exist', 'woo-vipps'), 'debug');
        throw new Exception($msg);
    }


    /** Whether fulfillments is supported in the WC version and is an enabled feature. LP 2025-10-27 */
    public function is_supported() {
        return version_compare(WC_VERSION, '10.2', '>=') && get_option('woocommerce_feature_fulfillments_enabled') == 'yes';
    }

    public function get_order_fulfillments($order) {
        $data_store = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore::class);
        return $data_store->read_fulfillments('WC_Order', $order->get_id());
    }

    public function order_has_fulfillments($order) {
        return !empty($this->get_order_fulfillments($order));
    }
}
