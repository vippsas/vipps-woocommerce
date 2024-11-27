<?php
$supports = false;
$pid = $block->context['postId'] ?? 0;
$product = $pid ? wc_get_product($pid) : 0;
if ($product && is_a($product, 'WC_Product')) {
	$supports = WC_Gateway_Vipps::instance()->product_supports_express_checkout($product);

	$vipps = Vipps::instance();
}

?>
<?php if ($supports): ?>
	<div <?php echo get_block_wrapper_attributes() ?>
		class="wp-block-button  wc-block-components-product-button wc-block-button-vipps"
	>
		<a 
			class="single-product button vipps-buy-now wp-block-button__link"
			href="javascript: void(0);" 
			title=<?php echo sprintf(__('Buy now with %1$s', 'woo-vipps'), $vipps->get_payment_method_name()); ?>
		>
			<span class="vippsbuynow">
				<?php _e('Buy now with', 'woo-vipps'); ?>
			</span>
			<img class="inline vipps-logo-negative" src={VippsConfig.vippslogourl} alt="Vipps" />
		</a>
	</div>
<?php else: ?>
	Å nei, ingen hurtigkasse her
<?php endif; ?>