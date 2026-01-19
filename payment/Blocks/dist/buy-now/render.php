<?php
$vipps = Vipps::instance();
$supports = false;

error_log('LP attributes: ' . print_r($block->attributes, true));
// isInQuery is set if we are in a product template parent block context. LP 2026-01-19
if ($block->attributes['isInQuery']) {
	error_log("In query");
    $pid = $block->context['postId'] ?? 0;
} // else we need to get product from the block config. LP 2026-01-19
else {
	error_log("not In query");
    $pid = $block->attributes['productId'] ?? 0;
}
$product = $pid ? wc_get_product($pid) : 0;
if ($product && is_a($product, 'WC_Product')) {
    $supports = $vipps->loop_single_product_is_express_checkout_purchasable($product);

}
error_log("LP pid: $pid, supports: $supports");

// Only create button if the product has woo-vipps express checkout enabled. LP 29.11.2024
if ($supports) {
    echo "<div class='wp-block-button wc-block-components-product-button wc-block-button-vipps'>" . $vipps->get_buy_now_button_manual($product->get_id(), false, null, false, '', $block->attributes['variant'], $block->attributes['language']) . "</div>";
}
