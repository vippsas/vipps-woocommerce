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
        add_filter('woocommerce_fulfillment_before_fulfill', [$this, 'woocommerce_fulfillment_before_fulfill']);
        add_filter('woocommerce_fulfillment_before_delete', [$this, 'woocommerce_fulfillment_before_delete']);
        add_filter('woocommerce_fulfillment_before_update', [$this, 'woocommerce_fulfillment_before_update']);
    }


    /** Whether fulfillments is supported in the WC version and is an enabled feature. LP 2025-10-27 */
    public function is_supported() {
        return version_compare(WC_VERSION, '10.2', '>=') && get_option('woocommerce_feature_fulfillments_enabled') == 'yes';
    }


    /** Whether the plugin has enabled fulfillment support. LP 2025-10-22 */
    public function is_enabled() {
        return $this->is_supported() && $this->gateway->get_option('fulfillments_enabled') == 'yes';
    }


    /** Mark a fulfillment edit as being an edit so we can decide the correct capture amount in woocommerce_fulfillment_before_fulfill. LP 2025-11-07 */
    public function woocommerce_fulfillment_before_update($fulfillment) {
        error_log('LP Running fulfillment_before_update to mark it as being edit. Id is ' . $fulfillment->get_id());
        $fulfillment->update_meta_data('_vipps_fulfillment_is_edit', 1);
        return $fulfillment;
    }


    /** Disable deletion for vipps fulfillments, since we capture fulfillments and don't want to refund (they can't be recaptured). LP 2025-10-22 */
    public function woocommerce_fulfillment_before_delete($fulfillment) {
        $order = $fulfillment->get_order();
        if (!$order) {
            $this->fulfillment_fail(__('Something went wrong, could not find order', 'woo-vipps'));
        }

        $payment_method = $order->get_payment_method();
        error_log('LP before_delete payment_method: ' . print_r($payment_method, true));
        if ($payment_method != 'vipps') {
            return $fulfillment;
        }

        error_log("LP woocommerce_fulfillment_before_delete on vipps order. Stopping...");
        $this->fulfillment_fail(sprintf(__('Fulfillment is already captured at %1$s, cannot delete it.', 'woo-vipps'), Vipps::CompanyName()));
    }


    /** Partially capture Vipps order using the fulfilled items from woo. LP 2025-10-08
     *
     *  This hook will also run on fulfillments edits, after the hook '...before_update'
     *  therefore here we loop over the fulfillment items and stop if the new fulfill sum is less than what is already captured at Vipps MobilePay. LP 2025-10-31
     */
    public function woocommerce_fulfillment_before_fulfill($fulfillment) {
        error_log("LP running woocommerce_fulfillment_before_fulfill");

        $order = $fulfillment->get_order();
        if (!$order) {
            $this->fulfillment_fail(__('Something went wrong, could not find order', 'woo-vipps'));
        }

        try {
            $order = $this->gateway->update_vipps_payment_details($order);
        } catch (Exception $e) {
            //Do nothing with this for now
            $this->gateway->log(__("Error getting payment details before processing fulfillment: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }

        // This is handled in gateway->capture_payment(), but might as well stop early here if not Vipps MobilePay. LP 2025-10-27
        $payment_method = $order->get_payment_method();
        error_log('LP before_fulfill payment_method: ' . print_r($payment_method, true));
        if ($payment_method != 'vipps') {
            return $fulfillment;
        }

        // Stop if the order meta amounts does not add up with sum of its items, this could mean that
        // the order was mutated outside of WP (e.g. refund through business portal) LP 2025-11-07
        if (!$this->gateway->order_meta_coincides_with_items_meta($order)) {
            $this->gateway->log(sprintf(__('The order meta data does not coincide with the sums of the order items\' meta data, we can\'t process this refund correctly. There may have been a refund or alike through the %1$s business portal', 'woo-vipps'), Vipps::CompanyName()), 'error');
            $this->fulfillment_fail(sprintf(__('Cannot refund through %1$s - order status is unclear, the order may have been changed through the %2$s business portal.', 'woo-vipps'), $this->gateway->get_payment_method_name(), Vipps::CompanyName()));
        }

        // Don't process anything further if the order has nothing remaining to capture
        // Note: the meta '_vipps_capture_remaining' might be unset at this point so we instead need to calculate it. LP 2025-11-07
        // We also don't subtract refunded, because it is a subset of captured. LP 2025-11-19
        $capture_remaining = intval($order->get_meta('_vipps_amount')) -
            intval($order->get_meta('_vipps_captured')) -
            intval($order->get_meta('_vipps_cancelled')) -
            intval($order->get_meta('_vipps_noncapturable'));
        error_log('LP before_fulfill capture_remaining: ' . print_r($capture_remaining, true));
        if ($capture_remaining <= 0) { // Might be less than zero because we subtract both noncapturable and cancelled. LP 2025-11-19
            $this->fulfillment_fail(sprintf(__('Order has nothing left to capture at %1$s, cannot fulfill these items.', 'woo_vipps'), Vipps::CompanyName()));
        }

        // We need to know if this fulfillment is an edit of an existing fulfillment to calculate correct capture,
        // this meta is created in the filter woocommerce_fulfillment_before_update. LP 2025-11-19
        $is_an_edit = intval($fulfillment->get_meta('_vipps_fulfillment_is_edit'));

        $currency = $order->get_currency();

        // Loop over each item and calculate what to actually capture. LP 2025-11-19
        $to_capture_sum = 0;
        // Also store captures separately in a table, so we can update order items' meta upon capture success. LP 2025-10-31
        $item_capture_table = []; 

        foreach ($fulfillment->get_items() as $item) {
            $item_id = $item['item_id'];
            error_log('LP before_fulfill item_id: ' . print_r($item_id, true));
            $fulfill_quantity = $item['qty'];
            $order_item = $order->get_item($item_id);
            if (!$order_item) {
                $this->fulfillment_fail('Something went wrong, could not find fulfillment item'); // how did this happen
            }

            $item_name = $order_item->get_name();
            error_log('LP before_fulfill item_name: ' . print_r($item_name, true));
            $fulfill_sum = intval(
                100 * $order->get_item_total($order_item, true, false) * $fulfill_quantity
            );
            error_log('LP before_fulfill fulfill_sum: ' . print_r($fulfill_sum, true));
            $captured = intval($order_item->get_meta('_vipps_item_captured'));
            error_log('LP before_fulfill captured: ' . print_r($captured, true));

            $to_capture = $fulfill_sum;

            // If this is an edit of an existing fulfillment, make sure to only capture the difference between the new sum, don't capture duplicate. LP 2025-11-07
            if ($is_an_edit) {
                // Stop if user tries to remove already-captured amounts in this edit. LP 2025-10-31
                if ($to_capture < $captured) {
                    /* translators: %1 = item product name, %2 = company name */
                    $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is less than what is already captured at %2$s, please consider refunding the item instead.', 'woo-vipps'), $item_name, Vipps::CompanyName()));
                }

                error_log('LP before_fulfull, yes this was an edit! subtract captured amount of '. $captured);
                $to_capture -= $captured;
            }
            error_log('LP to_capture for item: ' . print_r($to_capture, true));

            // We can skip the rest if to_capture is zero (note: it shouldn't be negative here since check for this in the edit-branch above). LP 2025-11-19
            if ($to_capture <= 0) {
                error_log("LP before_fulfill, got a nonpositive to_capture=$to_capture, skipping this item");
                continue;
            }

            // Stop if this fulfillment would capture more than the items actual outstanding amount. LP 2025-11-07
            $order_item_sum = intval(
                100 * $order->get_item_total($order_item, true, false) * $order_item->get_quantity()
            );
            $noncapturable = intval($order_item->get_meta('_vipps_item_noncapturable'));
            $item_outstanding = $order_item_sum - $noncapturable - $captured;
            if ($to_capture > $item_outstanding) {
                $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is more than is available to capture at %2$s.', 'woo_vipps'), $item_name, Vipps::CompanyName()));
            }

            $item_capture_table[$item_id] = $to_capture;
            $to_capture_sum += $to_capture;
        }


        // Do the capture. LP 2025-11-19
        $ok = $this->gateway->capture_payment($order, $to_capture_sum);
        if (!$ok) {
            error_log("LP before_fulfill capture was not ok!");
            /* translators: %1 = number, %2 = currency string, %3 = this payment method name */
            $this->fulfillment_fail(sprintf(__('Could not capture the fulfillment of %1$s %2$s at %3$s. Please check the logs for more information.', 'woo-vipps'), $to_capture_sum, $currency, $this->gateway->get_payment_method_name()));
        }

        // Capture was successfull, now we need to update the captured meta for each item. LP 2025-10-31
        error_log("LP before_fulfillment capture ok! Updating item metas, then accepting fulfillment");
        foreach ($item_capture_table as $item_id => $new_capture) {
            $item = $order->get_item($item_id);
            $captured = intval($item->get_meta('_vipps_item_captured')) + $new_capture;
            $item->update_meta_data('_vipps_item_captured', $captured);
            $item->save_meta_data();
        }

        return $fulfillment;
    }


    /** Returns a failure message to the fulfillment admin interface by throwing FulfillmentException. LP 2025-10-15 */
    public function fulfillment_fail($msg) {
        if (class_exists('Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException')) {
            throw new Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException($msg);
        }
        /* translators: %1 = class name, %2 = exception message */
        $this->gateway->log(sprintf(__('%1$s does not exist, the exception was: %2$s', "woo-vipps"), 'error'), 'FulfillmentException', $msg);
        throw new Exception($msg);
    }


    /** Returns all fulfillments on the given order. LP 2025-11-19 */
    public function get_order_fulfillments($order) {
        if (!class_exists('Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore')) {
            /* translators: %1 = class name, %2 = order id */
            $this->gateway->log(sprintf(__('%1$s was not found, can\'t retrieve order fulfillments for order %1$s'), 'FulfillmentsDataStore', $order->get_id()), 'error');
            return false;
        }
        $data_store = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore::class);
        return $data_store->read_fulfillments('WC_Order', $order->get_id());
    }


    public function order_has_fulfillments($order) {
        return !empty($this->get_order_fulfillments($order));
    }
}
