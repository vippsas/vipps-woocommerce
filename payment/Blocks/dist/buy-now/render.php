<?php
$vipps = Vipps::instance();
$supported = false;

$variation_id = null;

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
    // Variation products have a parent id set, which is the product_id we need to use. LP 2026-01-22
    if ($block->attributes['productParentId']) {
        $pid = $block->attributes['productParentId'] ?? 0;
        $variation_id = $block->attributes['productId'] ?? 0;
    } else {
        $pid = $block->attributes['productId'] ?? 0;
    }

    $product = $pid ? wc_get_product($pid) : 0;
    if ($product && is_a($product, 'WC_Product')) {
        $supported = $vipps->loop_single_product_is_express_checkout_purchasable($product);
    }
}

// Only create button if the product has woo-vipps express checkout enabled. LP 29.11.2024
if ($supported) {
    echo "<div class='wp-block-button wc-block-components-product-button wc-block-button-vipps'>" . $vipps->get_buy_now_button_manual($product->get_id(), $variation_id, null, false, '', $block->attributes['variant'], $block->attributes['language']) . "</div>";
}
