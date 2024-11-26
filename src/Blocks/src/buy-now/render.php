<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */


$supports = false;
$pid = $block->context['postId'] ?? 0;
$product = $pid ? wc_get_product($pid) : 0;
if ($product && is_a($product, 'WC_Product')) {
    $supports = WC_Gateway_Vipps::instance()->product_supports_express_checkout($product);
}

?>

<p  <?php echo get_block_wrapper_attributes(); ?>
    data-wp-interactive='woo-vipps'
    <?php echo wp_interactivity_data_wp_context(array( 'pid' => $pid)); ?>
    data-wp-watch="callbacks.watch" data-wp-init="callbacks.init"
>


	<?php esc_html_e( 'Vipps MobilePay Buy Now – hello from a dynamic block!!1!', 'buy-now' ); ?>
<?php if ($supports): ?>
 Dette produktet støtter vipps hurtigkasse!
<?php else: ?>
 Å nei, ingen hurtigkasse her
<?php endif; ?>

</p>
