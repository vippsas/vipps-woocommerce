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

use Automattic\WooCommerce\Internal\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore;

class VippsFulfillments {
    private $gateway;

    public function __construct($gateway) {
        $this->gateway = $gateway;
    }


    public function register_hooks() {
        if ($this->is_enabled()) {
            add_filter('woocommerce_fulfillment_before_fulfill', [$this, 'woocommerce_fulfillment_before_fulfill']);
            add_filter('woocommerce_fulfillment_before_delete', [$this, 'woocommerce_fulfillment_before_delete']);
            add_filter('woocommerce_fulfillment_before_update', [$this, 'woocommerce_fulfillment_before_update']);
        }
    }


    /** Whether fulfillments is supported in the WC version and is an enabled feature. LP 2025-10-27 */
    public function is_supported() {
        return version_compare(WC_VERSION, '10.2', '>=') && get_option('woocommerce_feature_fulfillments_enabled') == 'yes';
    }


    /** Whether the plugin has enabled support for partial capture in fulfillment. LP 2025-10-22 */
    public function is_enabled() {
        return $this->is_supported() && $this->gateway->get_option('fulfillments_enabled') == 'yes';
    }


    /** Returns a Fulfillment object if one is found with the given id, else false. LP 2025-11-25 */
    public function get_fulfillment($id) {
        if (!class_exists('Automattic\WooCommerce\Internal\Fulfillments\Fulfillment')
        || !class_exists('Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore')) {
            /* translators:  %1 and %2 are class names */
            $this->gateway->log(sprintf(__('Class %1$s or %2$s was not found', "woo-vipps"), 'Fulfillment', 'FulfillmentsDataStore'), 'error');
            return false;
        }
        try {
            $fulfillment = new Fulfillment($id);
            $data_store = wc_get_container()->get(FulfillmentsDataStore::class);
            $data_store->read($fulfillment);
            return $fulfillment;
        } catch (Exception $e) {
            /* translators:  %1 = id, %2 = exception message */
            $this->gateway->log(sprintf(__('Could not read fulfillment with id %1$s: %2$s', "woo-vipps"), $id, $e->getMessage(), 'error'));
            return false;
        }
    }


    /** This runs on fulfillment edits, we need to mark this fulfillment as being an edit to
     * calculate correct new capture in woocommerce_fulfillment_before_fulfill. LP 2025-11-07.
     *
     *  We also need to check if this new updated version has all the items that the old one had, 
     *  i.e. this is the edge case where they remove all quantity of an item, then it won't be found in get_items()
     *  and therefore not caught in calculate_item_captures which runs on in woocommerce_fulfillment_before_fulfill. LP 2025-11-25
     */
    public function woocommerce_fulfillment_before_update($fulfillment) {

        error_log('LP Running fulfillment_before_update to mark it as being edit. Id is ' . $fulfillment->get_id());
        $fulfillment->update_meta_data('_vipps_fulfillment_is_edit', 1);

        $original_fulfillment = $this->get_fulfillment($fulfillment->get_id());
        if (!is_a($fulfillment, 'Automattic\WooCommerce\Internal\Fulfillments\Fulfillment')) {
                $this->fulfillment_fail(__('Something went wrong, did not find the original fulfillment to edit', 'woo-vipps'));
                /* translators: %1 = id, %2 = method/hook name */
                $this->gateway->log(sprintf(__('Did not find original fulfillment with id %1$s in %2$s, could not guarantee this is safe to fulfill, it was not accepted.', 'woo-vipps'), $fulfillment->get_id(), 'woocommerce_fulfillment_before_update'), 'error');
        }

        $item_ids = array_map(fn ($item) => $item['item_id'], $fulfillment->get_items());
        $original_item_ids = array_map(fn ($item) => $item['item_id'], $original_fulfillment->get_items());
        error_log('LP before_update item_ids: ' . print_r($item_ids, true));
        error_log('LP before_update original_item_ids: ' . print_r($original_item_ids, true));

        // Ensure all item ids from before update exists in this updated fulfillment. LP 2025-11-25
        foreach($original_item_ids as $id) {
            if (!in_array($id, $item_ids)) {
                try {
                    $item_name = $fulfillment->get_order()->get_item($id)->get_name();
                } catch (Exception $e) {
                    /* translators: %1 = id, %2 = method/hook name, %3 = exception message */
                    $this->gateway->log(sprintf(__('Could not get item name for fulfillment id %1$s in %2$s: %3$s', 'woo-vipps'), $fulfillment->get_id(), 'woocommerce_fulfillment_before_update', $e->getMessage()), 'error');
                    $item_name = 'unknown item name';
                }
                /* translators: %1 = item name, %2 = company name */
                $this->fulfillment_fail(sprintf(__('Item \'%1$s\' is already captured at %2$s, cannot remove it', 'woo-vipps'), $item_name, Vipps::CompanyName()));
            }
        }

        return $fulfillment;
    }


    /** Disable deletion for vipps fulfillments, since we capture fulfillments and don't want to refund (they can't be recaptured). LP 2025-10-22 */
    public function woocommerce_fulfillment_before_delete($fulfillment) {
        $order = $fulfillment->get_order();
        if (!$order) {
            $this->fulfillment_fail(__('Something went wrong, could not find order when handling fulfillments', 'woo-vipps'));
        }

        $payment_method = $order->get_payment_method();
        error_log('LP before_delete payment_method: ' . print_r($payment_method, true));
        if ($payment_method != 'vipps') {
            return $fulfillment;
        }

        error_log("LP woocommerce_fulfillment_before_delete on vipps order. Stopping...");
        $this->fulfillment_fail(sprintf(__('Cannot delete this fulfillment - its value has been captured at %1$. Refunding the items is possible.', 'woo-vipps'), Vipps::CompanyName()));
    }


    /** 
     * Partially capture Vipps order using the fulfilled items from woo. LP 2025-10-08
     *
     *  If support for this is turned on in the settings; When completing a fulfilment, we will sum up the total values of the items fulfilled and do
     *  a partial capture through the Vipps Api for this order. Since fulfillments can be edited, deleted etc, we will at the same time annotate on 
     *  each order line how much has been captured for the given orderline. The process-refund method of the gateway will likewise annotate the refunded
     *  amounts, and the amount to be cancelled (if the order has not been captured yet.). If we cannot (safely) do a partial capture, we will throw an error instead.
     *  The user may then have to turn off this support to be able to manage the fulfillments as required.
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

        // Stop early if the order has anything cancelled, we don't support partial capture as of now. LP 2025-11-21
        if (intval($order->get_meta('_vipps_cancelled')) > 0) {
            /* translators: %1=company name */
            $this->fulfillment_fail(sprintf(__('Order is cancelled at %1$s and can not be modified.', 'woo_vipps'), Vipps::CompanyName()));
        }

        // Stop if the order meta amounts does not add up with sum of its items, this could mean that
        // the order was mutated outside of WP (e.g. refund through business portal) LP 2025-11-07
        if (!$this->gateway->order_meta_coincides_with_items_meta($order)) {
            $this->gateway->log(sprintf(__('The order meta data does not coincide with the sums of the order items\' meta data, we can\'t do partial capture for this fulfillment. There may have been a refund or capture through the %1$s business portal', 'woo-vipps'), Vipps::CompanyName()), 'error');
            $this->fulfillment_fail(sprintf(__('Cannot do partial capture through %1$s - order status is unclear, the order may have been changed through the %2$s business portal.', 'woo-vipps'), $this->gateway->get_payment_method_name(), Vipps::CompanyName()));
        }

        // Don't process anything further there is nothing to capture. Note: the meta '_vipps_capture_remaining' 
        // might be unset if there is no capture on this order yet, so we instead need to calculate it here. LP 2025-11-07
        // We also should *not* subtract refunded, because it is a subset of captured! LP 2025-11-19
        $capture_remaining = intval($order->get_meta('_vipps_amount'))
            - intval($order->get_meta('_vipps_captured'))
            - intval($order->get_meta('_vipps_noncapturable'));
        error_log('LP before_fulfill capture_remaining: ' . print_r($capture_remaining, true));
        if ($capture_remaining <= 0) {
            $this->fulfillment_fail(sprintf(__('Order has nothing left to capture at %1$s, cannot fulfill these items.', 'woo_vipps'), Vipps::CompanyName()));
        }

        $currency = $order->get_currency();

        // Loop over each item and calculate what to actually capture. LP 2025-11-19
        // No capture will be made until we are sure *all* the items in the fulfillment can be captured for.
        $item_capture_table = $this->calculate_item_captures($fulfillment);
        $to_capture_sum = array_sum($item_capture_table);

        error_log('LP item_capture_table: ' . print_r($item_capture_table, true));
        error_log('LP to_capture_sum: ' . print_r($to_capture_sum, true));

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


    /** Loops over each fulfillment item and calculates the amount to actually capture at Vipps MobilePay,
     * also checks certain error cases and calls fulfillment_fail for these.
     *
     * Returns a table mapping fulfillment item id to capture amount. LP 2025-11-24
     */
    public function calculate_item_captures($fulfillment) {
        $order = $fulfillment->get_order();

        // We need to know if this fulfillment is an edit of an existing fulfillment to calculate correct capture,
        // this meta is created in the filter woocommerce_fulfillment_before_update. LP 2025-11-19
        $is_an_edit = intval($fulfillment->get_meta('_vipps_fulfillment_is_edit'));

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
            $fulfill_sum = round(
                100 * $order->get_item_total($order_item, true, false) * $fulfill_quantity
            , 0);
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

            // We can skip the rest if to_capture is zero, don't capture anything for this item. LP 2025-11-19
            if ($to_capture <= 0) {
                error_log("LP before_fulfill, got a nonpositive to_capture=$to_capture, skipping this item");
                continue;
            }

            // Stop if this fulfillment would capture more than the items actual outstanding amount. LP 2025-11-07
            $order_item_sum = round(
                100 * $order->get_item_total($order_item, true, false) * $order_item->get_quantity()
            , 0);
            $noncapturable = intval($order_item->get_meta('_vipps_item_noncapturable'));
            $item_outstanding = $order_item_sum - $noncapturable - $captured;
            if ($to_capture > $item_outstanding) {
                $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is more than is available to capture at %2$s.', 'woo_vipps'), $item_name, Vipps::CompanyName()));
            }

            $item_capture_table[$item_id] = $to_capture;
        }

        return $item_capture_table;
    }


    /** Returns a failure message to the fulfillment admin interface by throwing FulfillmentException. LP 2025-10-15 */
    public function fulfillment_fail($msg) {
        if (class_exists('Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException')) {
            /* translators:  %1 = exception message */
            $this->gateway->log(sprintf(__('Error handling fulfillment: %1$s', "woo-vipps"), $msg), 'error');
            throw new Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException($msg);
        }
        /* translators: %1 = class name, %2 = exception message */
        $this->gateway->log(sprintf(__('%1$ did not exist to throw on fulfillment fail, the fail message was: %2$s', "woo-vipps"), 'error'), 'FulfillmentException', $msg);
        throw new Exception($msg);
    }


    /** Returns all fulfillments on the given order. LP 2025-11-19 */
    public function get_order_fulfillments($order) {
        if (!class_exists('Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore')) {
            /* translators: %1 = class name, %2 = order id */
            $this->gateway->log(sprintf(__('%1$s was not found, can\'t retrieve order fulfillments for order %1$s'), 'FulfillmentsDataStore', $order->get_id()), 'error');
            return false;
        }
        $data_store = wc_get_container()->get(FulfillmentsDataStore::class);
        return $data_store->read_fulfillments('WC_Order', $order->get_id());
    }


    public function order_has_fulfillments($order) {
        return !empty($this->get_order_fulfillments($order));
    }
}
