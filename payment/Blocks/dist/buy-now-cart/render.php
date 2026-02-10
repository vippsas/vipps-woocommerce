<?php
if (! defined('ABSPATH')) {
    exit;
}

error_log('LP rendering the block!');

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'wp-block-button wc-block-components-product-button wc-block-button-vipps',
    'data-wp-bind--hidden' => '!woocommerce::state.cart.extensions.woo_vipps.cart_supports_express'
]);
echo "<div $wrapper_attributes>";
Vipps::instance()->minicart_express_checkout_button();
echo "</div>";
