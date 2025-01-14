<?php

// This is the script to register blocks built into ./dist. LP 19.11.2024

// Register blocks
add_action('init', function () {
    // vipps-badge block. LP 29.11.2025
    register_block_type(__DIR__ . '/dist/vipps-badge');

    // Buy-now product block uses JS event introduced in woocommerce 9.4. LP 29.11.2024
    if (version_compare(WC_VERSION, '9.4', '>=')) {
        register_block_type(__DIR__ . '/dist/buy-now');

    }
});

// Inject block config variables to block editor assets
add_action('enqueue_block_editor_assets', function () {
    // vipps-badge config
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
        $store_language = 'en'; // english default fallback
    }

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
            ['label' => __('Swedish', 'woo-vipps'), 'value' => 'se'],
            ['label' => __('Finnish', 'woo-vipps'), 'value' => 'fi'],
            ['label' => __('Danish', 'woo-vipps'), 'value' => 'dk'],
        ],
    ];
    // vipps-badge config stop

    // Inject block config to vipps-badge editor script. LP 15.11.2024
    wp_add_inline_script('woo-vipps-vipps-badge-editor-script',
        'const injectedVippsBadgeBlockConfig = ' . json_encode($block_config),
        'before');

    // Inject config from Vipps.class.php to buy-now editor script. LP 29.11.2024
    if (version_compare(WC_VERSION, '9.4', '>=')) {
        wp_add_inline_script('woo-vipps-buy-now-editor-script', 'const VippsConfig = ' . json_encode(Vipps::instance()->vippsJSConfig), 'before');
    }
});
