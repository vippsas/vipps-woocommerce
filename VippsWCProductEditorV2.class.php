<?php
/*
   This class is for extending the WooCommerce product editor with Vipps-specific settings.
   NT 2024-05-16
   For WP-specific interactions.


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
Copyright (c) 2023 WP-Hosting AS

MIT License

Copyright (c) 2023 WP-Hosting AS

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

class VippsWCProductEditorV2
{
    private static $instance = null;
    private $gw = null;

    public static function instance()
    {
        if (!static::$instance)
            static::$instance = new VippsWCProductEditorV2();
        return static::$instance;
    }

    public function gateway()
    {
        if ($this->gw)
            return $this->gw;
        $this->gw = Vipps::instance()->gateway();
        return $this->gw;
    }

    public static function register_hooks()
    {
        $VippsWCProductEditorV2 = static::instance();
        add_action('init', array($VippsWCProductEditorV2, 'init'));
    }

    public function init()
    {
        // Only enable the product editor extensions when the payment method is Vipps, since MobilePay is not supported as of now.
        if (Vipps::instance()->get_payment_method_name() == "Vipps") {
            // Inject some extra data to the product object when it is returned from the REST API.
            // This is useful for the product editor blocks, especially when we want to show/hide blocks based on the product's properties and/or the global settings.
            add_filter('woocommerce_rest_prepare_product_object', function ($response, $product) {
                if (empty($response->data)) {
                    return $response;
                }
                $gw = $this->gateway();
                // Check if the product can be bought with express checkout
                $can_be_bought_with_express_checkout = is_a($product, 'WC_Product') && $gw->product_supports_express_checkout($product);
                $response->data['vipps_product_can_be_bought_with_express_checkout'] = $can_be_bought_with_express_checkout;
                // Check if express checkout is enabled at all
                $response->data['vipps_global_singleproductexpress'] = $gw->get_option('singleproductexpress');
                // Inject a nonce for product editor's shareable links
                $response->data['share_link_nonce'] = wp_create_nonce('share_link_nonce');
                return $response;
            }, 10, 2);

            // Initialize the Vipps product tab
            add_action('rest_api_init', function () {
                add_action('woocommerce_block_template_area_product-form_after_add_block_inventory', array($this, 'init_woo_vipps_product_tab'));
            });
        }
    }


    public function init_woo_vipps_product_tab($general_group)
    {
        $gw = $this->gateway();
        $parent = $general_group->get_parent();
        $payment_method_name = $gw->get_payment_method_name();

        // Add the Vipps/MobilePay tab
        $gr = $parent->add_group(
            [
                'id' => 'woo-vipps-product-group',
                'order' => $general_group->get_order() + 5,
                'attributes' => [
                    'title' => $payment_method_name,
                ],
            ]
        );

        // Badges section
        $badges_section = $gr->add_section(
            [
                'id' => 'woo-vipps-badges-section',
                'order' => 1,
                'attributes' => [
                    'title' => __("On-site messaging badge", 'woo-vipps'),
                    'description' => __("On-site messaging badges are small badges that can be added to your product pages to show that you accept Vipps payments.", 'woo-vipps'),
                ],
            ]
        );
        $badges_section->add_block(
            [
                'id' => 'woo-vipps-show-badge',
                'blockName' => 'woocommerce/product-select-field',
                'order' => 2,
                'attributes' => [
                    'label' => __('Override default settings', 'woo-vipps'),
                    'property' => 'meta_data._vipps_show_badge',
                    'autoFocus' => false,
                    'help' => __('Choose a badge to show on this product', 'woo-vipps'),
                    'options' => array(
                        ['value' => '', 'label' => __('Default setting', 'woo-vipps')],
                        ['value' => 'none', 'label' => __('No badge', 'woo-vipps')],
                        ['value' => 'white', 'label' => __('White', 'woo-vipps')],
                        ['value' => 'grey', 'label' => __('Grey', 'woo-vipps')],
                        ['value' => 'orange', 'label' => __('Orange', 'woo-vipps')],
                        ['value' => 'light-orange', 'label' => __('Light Orange', 'woo-vipps')],
                        ['value' => 'purple', 'label' => __('Purple', 'woo-vipps')],
                    ),
                ],
            ]
        );

        $badges_section->add_block(
            [
                'id' => 'woo-vipps-overrides-later',
                'blockName' => 'woocommerce/product-select-field',
                'order' => 3,
                'attributes' => [
                    'label' => sprintf(__('Override %1$s Later', 'woo-vipps'), $payment_method_name),
                    'property' => 'meta_data._vipps_badge_pay_later',
                    'autoFocus' => false,
                    'help' => __('Choose if this product should use Vipps Later', 'woo-vipps'),
                    'options' => array(
                        ['value' => '', 'label' => __('Default setting', 'woo-vipps')],
                        ['value' => 'later', 'label' => sprintf(__('Use %1$s Later', 'woo-vipps'), $payment_method_name)],
                        ['value' => 'no', 'label' => sprintf(__('Do not use %1$s Later', 'woo-vipps'), $payment_method_name)],
                    ),
                ],
            ]
        );

        // Buy now section
        $buy_now_section = $gr->add_section(
            [
                'id' => 'woo-vipps-buy-now-section',
                'order' => 2,
                'attributes' => [
                    'title' => __("Buy Now Button", 'woo-vipps'),
                ],
            ]
        );
        // This block is shown if the product supports express checkout and the global setting is set to 'some'
        $buy_now_section->add_block(
            [
                'id' => 'woo-vipps-buy-now-button-some',
                'blockName' => 'woocommerce/product-checkbox-field',
                'order' => 1,
                'hideConditions' => array(
                    array(
                        'expression' => 'editedProduct.vipps_global_singleproductexpress !== "some"',
                    ),
                ),
                'attributes' => [
                    'label' => sprintf(__('Add %1$s Buy Now Button', 'woo-vipps'), $payment_method_name),
                    'property' => 'meta_data._vipps_buy_now_button',
                    'help' => __('Add a Buy Now button to this product', 'woo-vipps'),
                    'disabled' => false,
                    'tooltip' => sprintf(__('Add a \'Buy now with %1$s\'-button to this product', 'woo-vipps'), $payment_method_name),
                ],
            ]
        );

        // This block is shown if the global setting is set to 'all' and the product supports express checkout
        $buy_now_section->add_block(
            [
                'id' => 'woo-vipps-buy-now-button-all-not-supported',
                'blockName' => 'woocommerce/product-section-description',
                'order' => 1,
                'hideConditions' => array(
                    array(
                        'expression' => 'editedProduct.vipps_global_singleproductexpress !== "all" || editedProduct.vipps_product_can_be_bought_with_express_checkout === true',
                    ),
                ),
                'attributes' => [
                    'content' => sprintf(__("The %1\$s settings are currently set up so all products that can be bought with Express Checkout will have a Buy Now button.", 'woo-vipps'), $payment_method_name) . ' ' . __("This product does not support express checkout, and so will not have a Buy Now button.", 'woo-vipps')
                ],
            ]
        );
        // This block is shown if the global setting is set to 'all' and the product supports express checkout
        $buy_now_section->add_block(
            [
                'id' => 'woo-vipps-buy-now-button-all-supported',
                'blockName' => 'woocommerce/product-section-description',
                'order' => 1,
                'hideConditions' => array(
                    array(
                        'expression' => 'editedProduct.vipps_global_singleproductexpress !== "all" || editedProduct.vipps_product_can_be_bought_with_express_checkout === false',
                    ),
                ),
                'attributes' => [
                    'content' => sprintf(__("The %1\$s settings are currently set up so all products that can be bought with Express Checkout will have a Buy Now button.", 'woo-vipps'), $payment_method_name) . ' ' . __("This product supports express checkout, and so will have a Buy Now button.", 'woo-vipps')
                ],
            ]
        );

        // This block is hidden if the product does not support express checkout
        $buy_now_section->add_block(
            [
                'id' => 'woo-vipps-buy-now-button-none',
                'blockName' => 'woocommerce/product-section-description',
                'order' => 1,
                'hideConditions' => array(
                    array(
                        'expression' => 'editedProduct.vipps_global_singleproductexpress !== "none"',
                    ),
                ),
                'attributes' => [
                    'content' => sprintf(__("The %1\$s settings are configured so that no products will have a Buy Now button - including this.", 'woo-vipps'), $payment_method_name),
                ],
            ]
        );


    }



}
