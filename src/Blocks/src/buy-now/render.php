<?php
$vipps = Vipps::instance();
$supports = false;
$pid = $block->context['postId'] ?? 0;
$product = $pid ? wc_get_product($pid) : 0;
if ($product && is_a($product, 'WC_Product')) {
	$supports = $vipps->loop_single_product_is_express_checkout_purchasable($product);

}
?>
<?php if ($supports): ?>
	<div 
		<?php echo get_block_wrapper_attributes([
			'class' => 'wp-block-button wc-block-components-product-button wc-block-button-vipps'
		]); ?>
		data-wp-interactive='woo-vipps'
		<?php echo wp_interactivity_data_wp_context([]); ?>
    	data-wp-watch="callbacks.watch"
		data-wp-init="callbacks.init"
	>
		<a 
			class="single-product button vipps-buy-now wp-block-button__link"
			href="javascript: void(0);" 
			title=<?php echo sprintf(__('Buy now with %1$s', 'woo-vipps'), $vipps->get_payment_method_name()); ?>
		>
			<span class="vippsbuynow">
				<?php _e('Buy now with', 'woo-vipps'); ?>
			</span>
			<img 
				class="inline vipps-logo-negative" 
				<?php /* FIXME: plugins_url path. LP 27.11.2024 */ ?>
				src=<?php echo plugins_url('../../../img/vipps_logo_negativ_rgb_transparent.png',__FILE__);?> 
				alt="Vipps" 
			/>
		</a>
	</div>
<?php endif; ?>
