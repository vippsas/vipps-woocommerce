<?php
if (! defined('ABSPATH')) {
    exit;
}

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'wp-block-button wc-block-components-product-button wc-block-button-vipps',
]);
echo "<div $wrapper_attributes>";
// inverting the condition with "!" doesn't work, even though the docs use it (although different example), so thats why we use "cart_hide_express" instead of a normal "cart_show_express". (https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/) LP 2026-02-10
echo '<span data-wp-bind--hidden="woocommerce::state.cart.extensions.woo-vipps.cart_hide_express">';
Vipps::instance()->cart_express_checkout_button_html(true); // always print the html, the directive 'data-wp-bind--hidden' above will show or hide it. LP 2026-02-10
echo '</span>';
echo "</div>";
