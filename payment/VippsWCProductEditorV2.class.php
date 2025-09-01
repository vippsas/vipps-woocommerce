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
        add_action('wp_ajax_vipps_generate_unused_shareable_meta_key', array($this, 'ajax_vipps_generate_unused_shareable_meta_key'));
        // Only enable the product editor extensions when the payment method is Vipps, since MobilePay is not supported as of now.
        // IOK 2025-09-01 no longer the case.
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
            $response->data['vipps_share_link_nonce'] = wp_create_nonce('vipps_share_link_nonce');
            $response->data['vipps_buy_product_url'] = Vipps::instance()->buy_product_url();
            return $response;
        }, 10, 2);

        // Initialize the Vipps/MobilePay product tab
        add_action('rest_api_init', function () {
            add_action('woocommerce_block_template_area_product-form_after_add_block_inventory', array($this, 'init_woo_vipps_product_tab'));
        });
    }

    // This creates and returns an URL and a key that can be used to buy a product directly. The metadata key is stored in the product's metadata by the product editor.
    // Only products with these links can be bought like this; both to avoid having to create spurious orders from griefers and to ensure
    // that a link can be retracted if it has been printed or shared in emails with a specific price. IOK 2018-10-03
    public function ajax_vipps_generate_unused_shareable_meta_key()
    {
        check_ajax_referer('vipps_share_link_nonce', 'vipps_share_shareable_link_nonce');
        if (!current_user_can('manage_woocommerce')) {
            echo json_encode(array('ok' => 0, 'msg' => __('You don\'t have sufficient rights to edit this product', 'woo-vipps')));
            wp_die();
        }
        $prodid = intval($_POST['prodid']);
        $varid = intval($_POST['varid']);

        $product = '';
        $variant = '';
        $varname = '';
        try {
            $product = wc_get_product($prodid);
            $variant = $varid ? wc_get_product($varid) : null;
            $varname = $variant ? $variant->get_id() : '';
            if ($variant && $variant->get_sku()) {
                $varname .= ":" . sanitize_text_field($variant->get_sku());
            }
        } catch (Exception $e) {
            echo json_encode(array('ok' => 0, 'msg' => $e->getMessage()));
            wp_die();
        }
        if (!$product) {
            echo json_encode(array('ok' => 0, 'msg' => __('The product doesn\'t exist', 'woo-vipps')));
            wp_die();
        }

        // Find a free shareable link by generating a hash and testing it. Normally there won't be any collisions at all.
        $key = '';
        while (!$key) {
            global $wpdb;
            $key = substr(sha1(mt_rand() . ":" . $prodid . ":" . $varid), 0, 8);
            $existing = $wpdb->get_row("SELECT post_id from {$wpdb->prefix}postmeta where meta_key='_vipps_shareable_link_$key' limit 1", 'ARRAY_A');
            if (!empty($existing))
                $key = '';
        }
        $url = add_query_arg('pr', $key, Vipps::instance()->buy_product_url());
        echo json_encode(array('ok' => 1, 'msg' => 'ok', 'product_id' => $prodid, 'variant' => $varname, 'key' => $key, 'url' => $url));
        wp_die();
    }


    public function init_woo_vipps_product_tab($general_group)
    {
        $gw = $this->gateway();
        $parent = $general_group->get_parent();
        $payment_method_name = $gw->get_payment_method_name();
        $company_name = Vipps::CompanyName();

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
                    'description' => sprintf(__('On-site messaging badges are small badges that can be added to your product pages to show that you accept %1$s payments.', 'woo-vipps'), $company_name),
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
                        ['value' => 'filled', 'label' => __('Filled', 'woo-vipps')],
                        ['value' => 'light', 'label' => __('Light', 'woo-vipps')],
                        ['value' => 'purple', 'label' => __('Purple', 'woo-vipps')],
                    ),
                ],
            ]
        );

        if ($payment_method_name == "Vipps") {
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
                        'checkedValue' => 'yes',
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

            // Shareable links section
            $qradmin = admin_url("/edit.php?post_type=vipps_qr_code");
            $shareables_section = $gr->add_section(
                [
                    'id' => 'woo-vipps-shareable-links-section',
                    'order' => 3,
                    'attributes' => [
                        'title' => __("Shareable links", 'woo-vipps'),
                        'description' => sprintf(__('Shareable links are links you can share externally on banners or other places that when followed will start %1$s of this product immediately. Maintain these links here for this product.', 'woo-vipps'), $payment_method_name),
                    ],
                ]
            );
            $shareables_section->add_block(
                [
                    'id' => 'woo-vipps-shareable-links',
                    'blockName' => 'woo-vipps/product-shareable-link',
                    'order' => 1,
                    'attributes' => [
                        'property' => 'meta_data._vipps_shareable_links',
                        'title' => sprintf(__('Shareable links are links you can share externally on banners or other places that when followed will start %1$s of this product immediately. Maintain these links here for this product.', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'message' => sprintf(__("To create a QR code for your shareable link, we recommend copying the URL and then using the <a href='%2\$s'>%1\$s QR Api</a>", 'woo-vipps'), "Vipps", $qradmin),
                    ],
                ]
            );
        }
    }
}
