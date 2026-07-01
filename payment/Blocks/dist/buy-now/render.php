<?php
$vipps = Vipps::instance();
$supported = false;

// hasProductContext is set if we are in a product template parent block context. LP 2026-01-19
if ($block->attributes['hasProductContext']) {
    $pid = $block->context['postId'] ?? 0;
    $product = $pid ? wc_get_product($pid) : 0;
    if ($product && is_a($product, 'WC_Product')) {
        $supported = $vipps->loop_single_product_is_express_checkout_purchasable($product);
    }
}

// else we need to get product from the block config. LP 2026-01-19
else {
    $pid = $block->attributes['productId'] ?? 0;
    $product = $pid ? wc_get_product($pid) : 0;
    if ($product && is_a($product, 'WC_Product')) {
        $supported = $vipps->loop_single_product_is_express_checkout_purchasable($product);
    }
}

// Only create button if the product has woo-vipps express checkout enabled. LP 29.11.2024
if ($supported) {
    // In retrospect, the web component button args should probably be in one attribute object instead, to avoid stuff like this. LP 2026-07-01
    $button_args = [];
    foreach(['compact', 'rounded'] as $bool_attr) {
        $button_args[$bool_attr] = ($block->attributes[$bool_attr] ?? false) ? 'true' : 'false';
    }
    foreach(['verb', 'variant', 'language'] as $str_attr) {
        $button_args[$str_attr] = $block->attributes[$str_attr] ?? '';
    }

    echo "<div class='wp-block-button wc-block-components-product-button wc-block-button-vipps'>" . $vipps->get_buy_now_button($product->get_id(), null, null, false, '', 'gutenberg', $button_args) . "</div>";
}
