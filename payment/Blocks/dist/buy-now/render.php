<?php
$vipps = Vipps::instance();
$supported = false;

    error_log('LP block: ' . print_r($block->attributes, true));
// Cart mode: buy the whole cart context. LP 2026-01-29
if ($block->attributes['isCartMode']) {
    if ($vipps->gateway()->show_express_checkout()) {
        echo $vipps->cart_express_checkout_button_html();
    }
}
// else: single product purchase. LP 2026-01-29
else {
    // we inherit the product id as context->postId if hasProductContext is set. LP 2026-01-19
    if ($block->attributes['hasProductContext']) {
        $pid = $block->context['postId'] ?? 0;
        $product = $pid ? wc_get_product($pid) : 0;
        if ($product && is_a($product, 'WC_Product')) {
            $supported = $vipps->loop_single_product_is_express_checkout_purchasable($product);
        }
    }
    // else, then we need to get product from the user selection. LP 2026-01-19
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
}
