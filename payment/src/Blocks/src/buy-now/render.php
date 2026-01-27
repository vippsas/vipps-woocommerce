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
    echo "<div class='wp-block-button wc-block-components-product-button wc-block-button-vipps'>" . $vipps->get_buy_now_button_manual($product->get_id(), null, null, false, '', $block->attributes['variant'], $block->attributes['language']) . "</div>";
}
