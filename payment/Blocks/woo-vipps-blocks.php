<?php

// This is the script to register blocks built into ./dist. LP 19.11.2024

// Register blocks
add_action('init', function () {
    // vipps-badge block. LP 29.11.2025
    register_block_type(__DIR__ . '/dist/vipps-badge');

    // Buy-now product block uses the JS event 'wc-blocks_product_list_rendered', introduced in woocommerce 9.4. LP 29.11.2024
    // See vipp.js - this event is used to initialize the buy-now buttons IOK 2026-01-14
    if (version_compare(WC_VERSION, '9.4', '>=')) {
        register_block_type(__DIR__ . '/dist/buy-now');

    }
});

// Inject block config variables to block editor assets
add_action('enqueue_block_editor_assets', function () {
    // vipps-badge config
    $vipps = Vipps::instance();
    $badge_variants = [
        ['label' => __('White', 'woo-vipps'), 'value' => 'white'],
        ['label' => __('Grey', 'woo-vipps'), 'value' => 'grey'],
        ['label' => __('Filled', 'woo-vipps'), 'value' => 'filled'],
        ['label' => __('Light', 'woo-vipps'), 'value' => 'light'],
        ['label' => __('Purple', 'woo-vipps'), 'value' => 'purple']];

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

    $languages = [
                ['label' => __('Default', 'woo-vipps'), 'value' => $store_language],
                ['label' => __('English', 'woo-vipps'), 'value' => 'en'],
                ['label' => __('Norwegian', 'woo-vipps'), 'value' => 'no'],
                ['label' => __('Finnish', 'woo-vipps'), 'value' => 'fi'],
                ['label' => __('Danish', 'woo-vipps'), 'value' => 'dk'],
            ];

    $badge_config = [
        'title' => sprintf(__('%1$s On-Site Messaging Badge', 'woo-vipps'), Vipps::CompanyName()),
        'variants' => $badge_variants,
        'iconSrc' => plugins_url('../img/vipps-mobilepay-logo-only.png', __FILE__),
        'brand' => strtolower($vipps->get_payment_method_name()),
        'languages' => $languages,
    ];

    // Inject block config to vipps-badge editor script. LP 15.11.2024
    wp_add_inline_script(
        'woo-vipps-vipps-badge-editor-script',
        'const injectedVippsBadgeBlockConfig = ' . json_encode($badge_config),
        'before'
    );

    // Inject config from Vipps.class.php to buy-now editor script. LP 29.11.2024
    // Buy-now product block uses the JS event 'wc-blocks_product_list_rendered', introduced in woocommerce 9.4. LP 29.11.2024
    if (version_compare(WC_VERSION, '9.4', '>=')) {
        $payment_method = Vipps::instance()->get_payment_method_name();
        $store_language = Vipps::instance()->get_customer_language();

        switch ($payment_method) {
            case 'Vipps':
                $buy_now_languages = [
                    ['label' => __('Store language', 'woo-vipps'), 'value' => "store"],
                    ['label' => __('English', 'woo-vipps'), 'value' => 'en'],
                    ['label' => __('Norwegian', 'woo-vipps'), 'value' => 'no'],
                    ['label' => __('Swedish', 'woo-vipps'), 'value' => 'se'],
                ];
                break;
            case 'MobilePay':
                $buy_now_languages = [
                    ['label' => __('Store language', 'woo-vipps'), 'value' => "store"],
                    ['label' => __('English', 'woo-vipps'), 'value' => 'en'],
                    ['label' => __('Finnish', 'woo-vipps'), 'value' => 'fi'],
                    ['label' => __('Danish', 'woo-vipps'), 'value' => 'dk'],
                ];
                break;
        }

        // Array of associative arrays with keys 'label' and 'value'. LP 2026-01-16
        $buy_now_variants = [
            ['label' => __('Default', 'woo-vipps'), 'value' => 'default-mini'],
        ];
        foreach (Vipps::instance()->get_express_logo_variants() as $value => $label) {
            $buy_now_variants[] = ['label' => $label, 'value' => $value];
        }

        // Create array of language => variant => logo_url. LP 2026-01-16
        $logos = [];
        foreach ($buy_now_languages as $lang_arr) {
            $lang_key = $lang_arr['value'];
            $lang = $lang_key === 'store' ? $store_language : $lang_key;
            foreach ($buy_now_variants as $variant_arr) {
                $variant = $variant_arr['value'];
                $logos[$lang_key][$variant] = Vipps::instance()->get_express_logo($payment_method, $lang, $variant);
            }
        }

        $buy_now_config = [
            'BuyNowWithVipps' => Vipps::instance()->vippsJSConfig['vippssmileurl'],
            'logos' => $logos,
            'vippssmileurl' => Vipps::instance()->vippsJSConfig['vippssmileurl'],
            'vippsbuynowbutton' => Vipps::instance()->vippsJSConfig['vippsbuynowbutton'],
            'vippsbuynowdescription' => Vipps::instance()->vippsJSConfig['vippsbuynowdescription'],
            'languages' => $buy_now_languages,
            'variants' => $buy_now_variants,
            'vippsresturl' => '/woo-vipps/v1',
        ];
        wp_add_inline_script('woo-vipps-buy-now-editor-script', 'const vippsBuyNowBlockConfig = ' . json_encode($buy_now_config), 'before');
    }
});
