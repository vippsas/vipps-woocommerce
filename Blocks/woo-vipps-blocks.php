<?php

/**
 * Init hooks and inline script for the block vipps-badge. LP 14.11.2024
 * @return void
 */
function vipps_badge_block_hooks() {
    add_action('init', function () {
        register_block_type(__DIR__ . '/dist/vipps-badge');
    });

    // Inject block config variables to vipps-badge editor script. LP 15.11.2024
    add_action('enqueue_block_editor_assets', function () {
        $variants = $variants = [
            ['label' => __("White", 'woo-vipps'), 'value' => 'white'],
            ['label' => __("Grey", 'woo-vipps'), 'value' => 'grey'],
            ['label' => __("Orange", 'woo-vipps'), 'value' => 'orange'],
            ['label' => __("Light Orange", 'woo-vipps'), 'value' => 'light-orange'],
            ['label' => __("Purple", 'woo-vipps'), 'value' => 'purple']];
    
        $block_config = [
            'title' => sprintf(__('%1$s On-Site Messaging Badge', 'woo-vipps'), Vipps::CompanyName()),
            'variants' => $variants,
            'defaultVariant' => 'white',
            'iconSrc' => plugins_url('../img/vipps-mobilepay-logo-only.png', __FILE__)
        ];

        wp_add_inline_script('woo-vipps-vipps-badge-editor-script',
            'const injectedBlockConfig = ' . json_encode($block_config),
            'before');
    });
}

vipps_badge_block_hooks();
