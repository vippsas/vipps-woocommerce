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
        add_action('woocommerce_fulfillment_before_fulfill', [$this, 'woocommerce_fulfillment_before_fulfill']); // FIXME: is prio important here? Idk. LP 2025-10-08
        add_action('woocommerce_fulfillment_before_delete', [$this, 'woocommerce_fulfillment_before_delete']); // FIXME: is prio important here? Idk. LP 2025-10-08
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

    public function get_order_fulfillments($order) {
        $data_store = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Fulfillments\FulfillmentsDataStore::class);
        return $data_store->read_fulfillments('WC_Order', $order->get_id());
    }

    /** Partially capture Vipps order using the fulfilled items from woo. LP 2025-10-08
     *
     *  This hook will also run on fulfillments edits, after the hook '...before_update'
     *  therefore here we loop over all fulfillments and only capture if the total sum is more than what is already captured.
     *  Else stop fulfillment with failure message.
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

        try {
            $fulfillments = $this->get_order_fulfillments($order);
        } catch (Exception $e) {
            $this->gateway->log(sprintf(__('Could not get previous fulfillments for order %1$s: ', 'woo-vipps'), $order->get_id()) . $e->getMessage(), 'error');
            $this->fulfillment_fail(__('Could not find fulfillments for the order. Please check the logs for more information.', 'woo-vipps'));
        }

        // Add new to array of all fulfillments to first position, to stop duplicate captures for cases with updated/edited fulfillments. LP 2025-10-15
        array_unshift($fulfillments, $new_fulfillment);
        error_log("LP fulfill_update total number of fulfillments is " . count($fulfillments));

        $sum = 0;
        $new_is_handled = false;
        foreach ($fulfillments as $fulfillment) {
            // Make sure to don't capture duplicate if this is a fulfillment update, by skipping the old one. LP 2025-10-15
            if ($fulfillment->get_id() === $new_fulfillment->get_id()) {
                error_log("LP This is the new fulfillment, has already been handled (update/edit fulfillment)?: $new_is_handled");
                if ($new_is_handled) {
                    continue;
                }
                $new_is_handled = true;
            }

            foreach ($fulfillment->get_items() as $item) {
                error_log('LP item: ' . print_r($item, true));
                $item_id = $item['item_id'];
                $item_quantity = $item['qty'];
                $order_item = $order->get_item($item_id);
                error_log('LP item_name: ' . print_r($order_item->get_name(), true));
                if (!$order_item) {
                    $this->fulfillment_fail('Something went wrong, could not find fulfillment item'); // how did this happen
                }

                $item_sum = $order->get_item_total($order_item, true, false) * $item_quantity;
                error_log('LP item_sum: ' . print_r($item_sum, true));
                $sum += $item_sum;
            }
        }

        $sum = round(wc_format_decimal($sum, '') * 100);
        error_log('LP before_fulfill sum: ' . print_r($sum, true));
        $captured = intval($order->get_meta('_vipps_captured'));
        error_log('LP before_fulfill captured: ' . print_r($captured, true));

        // If new sum is less than already captured, stop and return failure message to admin. We don't want to refund here. LP 2025-10-15
        if ($sum < $captured) {
            /* translators: %1$s = company name */
            $this->fulfillment_fail(sprintf(__('New capture sum is less than what is already captured at %1$s, cannot fulfill less products than before', 'woo-vipps'), Vipps::CompanyName()));
        }


        // New sum is greater than already captured, send capture. LP 2025-10-15
        $to_capture = $sum - $captured;
        error_log('LP before_fulfill new capture ' . print_r($to_capture, true));
        if ($to_capture == 0) {
            return $new_fulfillment;
        }

        $ok = $this->gateway->capture_payment($order, $to_capture);
        if (!$ok) {
            error_log("LP before_fulfill capture was not ok!");
            /* translators: %1$s = number, %2$s = this payment method company name */
            $this->fulfillment_fail(sprintf(__('Could not capture the fulfillment capture difference of %1$s at %2$s. Please check the logs for more information.', 'woo-vipps'), $to_capture, Vipps::CompanyName()));
        }
        error_log("LP before_fulfillment capture ok! Accepting fulfillment");
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
     * different quantities of fulfillments compared to quantity in the current refund, and compared to the total quantity in the order. LP 2025-10-24
     */
    public function handle_refund($order, $refund_amount) {
        $current_refund = apply_filters('woo_vipps_currently_active_refund', []);
        // Reset just in case...
        add_filter('woo_vipps_currently_active_refund', function () {
            return null;
        });

        $noncapturable_sum = 0;
        $refund_sum = $refund_amount;

        /* translators: %1$s = the payment method name */
        if (!$current_refund) {
            return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not access the refund object.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
        }

        try {
            $orderid = $order->get_id();
            error_log("LP order id for refund is $orderid");
            $order_items = $order->get_items();

            $fulfillments = $this->get_order_fulfillments(wc_get_order($orderid));
            error_log('LP fulfillments count: ' . count($fulfillments));
            $fulfilled_item_quantities = $this->get_fulfillments_item_quantities($fulfillments);
            error_log('LP fulfilled_item_quantitites: ' . print_r($fulfilled_item_quantities, true));
            $fulfilled_item_ids = array_keys($fulfilled_item_quantities);


            // These refund items are WC_Order_Item (specifically WC_Order_Item_Product for products)
            foreach ($current_refund->get_items() as $refund_item) {
                $item_id = $refund_item->get_meta('_refunded_item_id');
                if (!$item_id) {
                    return new WP_Error('Vipps', sprintf(__('Cannot refund through %1$s - could not read the refund item id.', 'woo-vipps'), $this->gateway->get_payment_method_name()));
                }
                error_log('LP refund order item id: ' . print_r($item_id, true));

                $refund_item_quantity = $refund_item->get_quantity() * -1; // NB: refund items have negative quantity. LP 2025-10-22
                $item_price = $current_refund->get_item_total($refund_item, true, false);
                $refund_item_sum = $item_price * $refund_item_quantity;
                $order_item = $order_items[$item_id];
                $order_item_quantity = $order_item->get_quantity();
                error_log('LP order_item_quantity: ' . print_r($order_item_quantity, true));
                error_log('LP refund_item_quantity: ' . print_r($refund_item_quantity, true));
                error_log('LP item_price: ' . print_r($item_price, true));
                error_log('LP refund_item_sum: ' . print_r($refund_item_sum, true));

                // The different partial capture cases:

                $has_no_fulfillments = !in_array($item_id, $fulfilled_item_ids);
                error_log('LP has_no_fulfillments: ' . print_r($has_no_fulfillments, true));
                // Safe to set all of this item to noncapturable. LP 2025-10-24
                if ($has_no_fulfillments) {
                    $noncapturable_sum += $refund_item_sum;
                    error_log("LP case no fulfillments, mark all as noncaptureable, noncapturable+=$refund_item_sum");
                    continue;
                };

                $fulfilled_item_quantity = $fulfilled_item_quantities[$item_id];
                $fulfilled_item_sum = $fulfilled_item_quantity * $item_price;
                $remaining_item_quantity = $order_item_quantity - $fulfilled_item_quantity;
                error_log('LP fulfilled_item_quantity: ' . print_r($fulfilled_item_quantity, true));
                error_log('LP fulfilled_item_sum: ' . print_r($fulfilled_item_sum, true));
                error_log('LP remaining_item_quantity: ' . print_r($remaining_item_quantity, true));


                $enough_items_remaining = $refund_item_quantity <= $remaining_item_quantity;
                error_log('LP enough_items_remaining: ' . print_r($enough_items_remaining, true));
                // Same as no fulfillments case above, safe to set all of this item to noncapturable. LP 2025-10-24
                if ($enough_items_remaining) {
                    $noncapturable_sum += $refund_item_sum;
                    error_log("LP case enough items remaining, mark all as noncaptureable, noncapturable+=$refund_item_sum");
                    continue;
                };

                $refunding_whole_quantity = $refund_item_quantity == $order_item_quantity;
                error_log('LP refunding_whole_quantity: ' . print_r($refunding_whole_quantity, true));
                // Need to refund all the item fulfillments and then set noncapture for the quantity not already fulfilled. LP 2025-10-23
                if ($refunding_whole_quantity) {
                    $to_noncapture = $refund_item_sum - $fulfilled_item_sum;
                    error_log("LP Case refunding the whole quantity, need to refund fulfilled + set rest noncapturable, to_refund=" . $fulfilled_item_sum  . ", to_noncapture=$to_noncapture");
                    $noncapturable_sum += $to_noncapture;
                    continue;
                }

                // Final case: There are NOT enough of this item left in the order to mark all as noncapture,
                // we need to set noncapture of all we can, then refund the difference. LP 2025-10-24
                $to_refund = ($refund_item_quantity - $remaining_item_quantity) * $item_price;
                $to_noncapture = $refund_item_sum - $to_refund;
                error_log("LP case not enough items left nonfulfilled, need to refund fulfilled + set rest noncapturable. to_refund=$to_refund, to_noncapture=$to_noncapture");
                $noncapturable_sum += $to_noncapture;
            }


            // Finally update noncapturable meta and subtract the same from the actual refund amount output. LP 2025-10-24
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

    /** Whether fulfillments is supported in the WC version and is an enabled feature. LP 2025-10-27 */
    public static function is_supported() {
        return version_compare(WC_VERSION, '10.2', '>=') && get_option('woocommerce_feature_fulfillments_enabled') == 'yes';
    }

    /** Returns associative array ['item_id' => 'quantity'] for all items in the fulfillments array of Fulfillment objects. LP 2025-10-23 */
    public static function get_fulfillments_item_quantities($fulfillments) {
        $items = [];
        foreach ($fulfillments as $fulfillment) {
            foreach ($fulfillment->get_items() as $item) {
                $id = $item['item_id'];
                $quantity = $item['qty'];
                if (array_key_exists($id, $items)) {
                    $items[$id] += $quantity;
                    continue;
                }
                $items[$id] = $quantity;
            }
            return $items;
        }
    }
}
