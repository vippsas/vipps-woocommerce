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
        return static::is_supported() && $this->gateway->get_option('fulfillments_enabled') == 'yes';
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
    public function woocommerce_fulfillment_before_fulfill($new_fulfillment) {
        error_log("LP running woocommerce_fulfillment_before_fulfill");

        $order = $new_fulfillment->get_order();
        if (!$order) {
            $this->fulfillment_fail(__('Something went wrong, could not find order', 'woo-vipps'));
        }

        // This is handled in gateway->capture_payment(), but might as well return early here if not Vipps MobilePay. LP 2025-10-27
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            return $new_fulfillment;
        }

        $to_capture = 0;
        foreach ($fulfillment->get_items() as $item) {
            $item_id = $item['item_id'];
            error_log('LP before_fulfill item_id: ' . print_r($item_id, true));
            $item_quantity = $item['qty'];
            $order_item = $order->get_item($item_id);
            if (!$order_item) {
                $this->fulfillment_fail('Something went wrong, could not find fulfillment item'); // how did this happen
            }
            error_log('LP before_fulfill item_name: ' . print_r($order_item->get_name(), true));
            $item_sum = $order->get_item_total($order_item, true, false) * $item_quantity;
            error_log('LP item_sum: ' . print_r($item_sum, true));

            // Stop here and give the user a fail message if they try to remove already-captured amounts for this order item.
            // This is for fulfillment edits. LP 2025-10-31
            $item_captured_sum = intval($order_item->get_meta('_vipps_item_captured'));
            if ($item_sum < $item_captured_sum) {
                /* translators: %1 = item product name, %2 = company name */
                $this->fulfillment_fail(sprintf(__('New capture sum for item \'%1$s\' is less than what is already captured at %2$s, cannot fulfill less products than before', 'woo-vipps'), $order_item->get_name(), Vipps::CompanyName()));
            }

            // We cannot yet update the items captured meta until we know success, so just store it in a table. LP 2025-10-31
            $new_item_sums[$order_item] = $item_sum + $item_captured_sum;

            $to_capture += $item_sum;
        }


        error_log('LP before_fulfill to_capture ' . print_r($to_capture, true));
        if ($to_capture == 0) {
            error_log("LP before_fulfill new capture is zero, not sending anything to capture");
            return $new_fulfillment;
        }

        $ok = $this->gateway->capture_payment($order, $to_capture);
        if (!$ok) {
            error_log("LP before_fulfill capture was not ok!");
            /* translators: %1$s = number, %2$s = this payment method company name */
            $this->fulfillment_fail(sprintf(__('Could not capture the fulfillment capture difference of %1$s at %2$s. Please check the logs for more information.', 'woo-vipps'), $to_capture, Vipps::CompanyName()));
        }

        error_log("LP before_fulfillment capture ok! Updating item metas, then accepting fulfillment");
        // Everything ok, now we need to update captured meta for each item. LP 2025-10-31
        foreach ($new_item_sums as $order_item => $new_sum) {
            $order_item->update_meta('_vipps_item_captured', $new_sum);
        }

        return $new_fulfillment;
    }

    /** Returns failure message to fulfillment admin interface by throwing FulfillmentException. LP 2025-10-15 */
    public function fulfillment_fail($msg = "") {
        if (class_exists('Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException')) {
            throw new Automattic\WooCommerce\Internal\Fulfillments\FulfillmentException($msg);
        }
        $this->gateway->log(__('FulfillmentException does not exist', 'woo-vipps'), 'debug');
        throw new Exception($msg);
    }


    /** Handles the different refund cases for partially captured orders. It is not as simple since order items may have
     * different quantities of fulfillments compared to quantity in the current refund and the total quantity in the order. LP 2025-10-24
     *
     * When refunding a partially captured (via fulfillments) order, we need to see if the actual refund to be processed is one of the
     * items captured in fulfillments or not. If it *is*, then we can process this amount normally in process refund. If it is *not*, then we
     * have to note that the sum in question is "noncapturable" instead - so we need to split the incoming "amount to refund" into these two values.
     * It will also be neccessary to pay attention to the quantities of the items being refunded and so forth. IOK 2025-10-27
     * Note also that doing partial capture through the portal probably will *not* be compatible with this, so if partial capture has been done 
     * *outside* fulfilments, that's an error. IOK 2025-10-27
     */
    public function handle_refund($order, $refund_amount, $current_refund) {
        $noncapturable_sum = 0;
        $refund_sum = $refund_amount;

        /* translators: %1$s = the payment method name */
        if (!$current_refund) {
            return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not access the refund object.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
        }

        try {
            $orderid = $order->get_id();
            error_log("LP order id for refund is $orderid");

            // Loop over the current refund and handle the different refund cases for each item. LP 2025-10-29
            foreach ($current_refund->get_items() as $refund_item) {
                // These refund items are WC_Order_Item (specifically WC_Order_Item_Product for products)
                $item_id = $refund_item->get_meta('_refunded_item_id');
                if (!$item_id) {
                    return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not read the refund item id.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
                }
                error_log('LP refund item id: ' . print_r($item_id, true));
                error_log('LP refund item name: ' . print_r($refund_item->get_name(), true));

                // This is actually the sum for the refund item, not the item's unit price. Not to be confused with getting
                // the item total for an order item, which then actually is the unit price. LP 2025-10-30
                $refund_item_sum = ($refund_item->get_total() + $refund_item->get_total_tax()) * -1; // NB: refund prices are negative. LP 2025-10-29
                error_log('LP refund_item_sum: ' . print_r($refund_item_sum, true));

                $order_item = $order->get_item($item_id);
                if (!$order_item) { // This should not happen since the refund object is based on the order. LP 2025-10-29
                    return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not read order item in refund.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
                }

                $order_item_total = $order->get_item_total($order_item, true, false);
                $order_item_quantity = $order_item->get_quantity();
                $order_item_sum = $order_item_total * $order_item_quantity;
                error_log('LP order_item_quantity: ' . print_r($order_item_quantity, true));
                error_log('LP order_item_total: ' . print_r($order_item_total, true));
                error_log('LP order_item_sum: ' . print_r($order_item_sum, true));
                $item_captured_sum = intval($order_item->get_meta('_vipps_item_captured'));
                error_log('LP item_captured_sum: ' . print_r($item_captured_sum, true));


                // Now, the different refund cases are:

                // If the item has nothing captured/fulfilled - easy: we are safe to set all of this item from the refund to noncapturable. LP 2025-10-24
                if (!$item_captured_sum) {
                    $noncapturable_sum += $refund_item_sum;
                    error_log("LP case items has nothing captured, mark all as noncaptureable, noncapturable+=$refund_item_sum");
                    continue;
                };

                $already_refunded = intval($order_item->get_meta('_vipps_item_refunded'));
                error_log('LP already_refunded: ' . print_r($already_refunded, true));

                // Stop if user tries to refund more than the entire order line contains... User error. LP 2025-10-30
                // FIXME: move this logic out to the gateway. Therefore we also have to move the loop logic, but keep this separate from fulfillment logic. LP 2025-10-31
                if ($refund_item_sum + $already_refunded >= $order_item_sum) {
                    /* translators: %1 = payment method name, %2 = item product name */
                    return new WP_Error('Vipps', sprintf(__("Cannot refund through %1\$s - the refund amount is too large for item '%2\$s'.", 'woo-vipps'), $this->gateway->get_payment_method_name(), $refund_item->get_name()));
                }

                $remaining_item_sum = max($order_item_sum - $item_captured_sum - $item_captured_sum, 0); // Value could be negative e.g. if the item is entirely fulfilled, but also has refunds. LP 2025-10-31
                error_log('LP available_refund: ' . print_r($remaining_item_sum, true));

                // If there is enough money available to refund for this item sum, then we are safe to set all of this item to noncapturable. LP 2025-10-24
                $enough_available = $refund_item_sum <= $remaining_item_sum;
                error_log('LP enough_available: ' . print_r($enough_available, true));
                if ($enough_available) {
                    $noncapturable_sum += $refund_item_sum;
                    error_log("LP case enough items remaining, mark all as noncaptureable, noncapturable+=$refund_item_sum");
                    continue;
                };

                // If this refund is refunding the entire amount of this item, then set noncapture for the amount not already fulfilled. LP 2025-10-23
                $refunding_entire_item = abs($refund_item_sum - $order_item_sum) < PHP_FLOAT_EPSILON;
                error_log('LP refunding_whole_quantity: ' . print_r($refunding_entire_item, true));
                if ($refunding_entire_item) {
                    $to_noncapture = $refund_item_sum - $item_captured_sum;
                    error_log("LP Case refunding the whole quantity, need to refund fulfilled + set rest noncapturable, to_refund=" . $item_captured_sum  . ", to_noncapture=$to_noncapture");
                    $noncapturable_sum += $to_noncapture;
                    continue;
                }

                // Final case: There are NOT enough of this item left in the order to mark all as noncapture,
                // we need to set noncapture for the remaining sum, then refund the rest of the amount. LP 2025-10-24
                $to_noncapture = $remaining_item_sum;
                error_log("LP case not enough items left nonfulfilled, need to refund fulfilled + set rest noncapturable. to_noncapture=$to_noncapture and we will refund the remaining");
                $noncapturable_sum += $to_noncapture;
            }

            // Prepare new refund and noncapturable sums for the output. LP 2025-10-30
            error_log("LP finished item loop, noncapturable_sum=$noncapturable_sum, and OLD refund_sum is $refund_sum");
            $refund_sum -= $noncapturable_sum;
            // just in case, because of subtraction. LP 2025-10-24
            if ($refund_sum < 0) {
                return new WP_Error('Vipps', sprintf(__("Cannot refund through %1\$s - got a negative refund amount, something unexpected occured.", 'woo-vipps'), $this->gateway->get_payment_method_name()));
            }

            error_log("LP finished item loop, NEW refund_sum is $refund_sum");

            return [$refund_sum, $noncapturable_sum];

        } catch (Exception $e) {
            $msg = sprintf(__('Could not retrieve fulfillments or fulfilled items for order %1$s: ', 'woo-vipps'), $orderid) . $e->getMessage();
            $this->gateway->log($msg, 'error');
            return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not access fulfillment data for the order.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
        }

    }

    public function handle_refund_item($order_amount, $refund_amount, $captured_amount) {
        $noncapturable_sum = 0;

        $remaining_item_sum = max($order_amount - $captured_amount - $captured_amount, 0); // Value could be negative e.g. if the item is entirely fulfilled, but also has refunds. LP 2025-10-31
        error_log('LP handle_refund_item available_refund: ' . print_r($remaining_item_sum, true));

        // If there is enough money available to refund for this item sum, then we are safe to set all of this item to noncapturable. LP 2025-10-24
        $enough_available = $refund_amount <= $remaining_item_sum;
        error_log('LP handle_refund_item enough_available: ' . print_r($enough_available, true));
        if ($enough_available) {
            $noncapturable_sum += $refund_amount;
            error_log("LP case enough items remaining, mark all as noncaptureable, noncapturable+=$refund_amount");
            continue;
        };

        // If this refund is refunding the entire amount of this item, then set noncapture for the amount not already fulfilled. LP 2025-10-23
        $refunding_entire_item = abs($refund_amount - $order_amount) < PHP_FLOAT_EPSILON;
        error_log('LP handle_refund_item refunding_whole_quantity: ' . print_r($refunding_entire_item, true));
        if ($refunding_entire_item) {
            $to_noncapture = $refund_amount - $captured_amount;
            error_log("LP Case refunding the whole quantity, need to refund fulfilled + set rest noncapturable, to_refund=" . $captured_amount  . ", to_noncapture=$to_noncapture");
            $noncapturable_sum += $to_noncapture;
            continue;
        }

        // Final case: There are NOT enough of this item left in the order to mark all as noncapture,
        // we need to set noncapture for the remaining sum, then refund the rest of the amount. LP 2025-10-24
        $to_noncapture = $remaining_item_sum;
        error_log("LP handle_refund_item case not enough items left nonfulfilled, need to refund fulfilled + set rest noncapturable. to_noncapture=$to_noncapture and we will refund the remaining");
        $noncapturable_sum += $to_noncapture;
        return $noncapturable_sum;
    }

    /** Whether fulfillments is supported in the WC version and is an enabled feature. LP 2025-10-27 */
    public static function is_supported() {
        return version_compare(WC_VERSION, '10.2', '>=') && get_option('woocommerce_feature_fulfillments_enabled') == 'yes';
    }

    public static function get_order_fulfillments($order) {
        $data_store = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore::class);
        return $data_store->read_fulfillments('WC_Order', $order->get_id());
    }

    public static function order_has_fulfillments($order) {
        return !empty(static::get_order_fulfillments($order));
    }
}
