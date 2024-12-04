<?php
$vipps = Vipps::instance();
$supports = false;
$pid = $block->context['postId'] ?? 0;
$product = $pid ? wc_get_product($pid) : 0;
if ($product && is_a($product, 'WC_Product')) {
	$supports = $vipps->loop_single_product_is_express_checkout_purchasable($product);

}

// Only create button if the product has woo-vipps express checkout enabled. LP 29.11.2024
if ($supports) {
	echo $vipps->get_buy_now_button($product->get_id(), false);
}
