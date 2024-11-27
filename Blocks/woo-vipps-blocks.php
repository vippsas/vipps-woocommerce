<?php

// This is the new script to register blocks built into ./dist. At this time only the vipps-badge block. LP 19.11.2024

add_action('init', function () {
        register_block_type(__DIR__ . '/dist/vipps-badge');
});

// Inject block config variables to vipps-badge editor script. LP 15.11.2024
add_action('enqueue_block_editor_assets', function () {
        $vipps = Vipps::instance();
        $variants = $variants = [
        ['label' => __('White', 'woo-vipps'), 'value' => 'white'],
        ['label' => __('Grey', 'woo-vipps'), 'value' => 'grey'],
        ['label' => __('Filled', 'woo-vipps'), 'value' => 'filled'],
        ['label' => __('Light', 'woo-vipps'), 'value' => 'light'],
        ['label' => __('Purple', 'woo-vipps'), 'value' => 'purple']];

        // Set a default language for the vipps-badge. LP 21.11.2024
        $store_language = substr(get_bloginfo('language'), 0, 2);
        if ($store_language == 'nb' || $store_language == 'nn') {
        $store_language = 'no';
        }
        if ($store_language == 'da') {
        $store_language = 'dk';
        }
        if (!in_array($store_language, ['en', 'no', 'dk', 'fi'])) {
        $store_language = 'en';
        } // english default fallback

        $block_config = [
            'title' => sprintf(__('%1$s On-Site Messaging Badge', 'woo-vipps'), Vipps::CompanyName()),
            'variants' => $variants,
            'defaultVariant' => 'white',
            'defaultLanguage' => $store_language,
            'iconSrc' => plugins_url('../img/vipps-mobilepay-logo-only.png', __FILE__),
            'brand' => strtolower($vipps->get_payment_method_name()),
            'languages' => [
                ['label' => __('Default', 'woo-vipps'), 'value' => $store_language],
                ['label' => __('English', 'woo-vipps'), 'value' => 'en'],
                ['label' => __('Norwegian', 'woo-vipps'), 'value' => 'no'],
                ['label' => __('Finnish', 'woo-vipps'), 'value' => 'fi'],
                ['label' => __('Danish', 'woo-vipps'), 'value' => 'dk'],
            ],
        ];

            wp_add_inline_script('woo-vipps-vipps-badge-editor-script',
                    'const injectedVippsBadgeBlockConfig = ' . json_encode($block_config),
                    'before');
});


