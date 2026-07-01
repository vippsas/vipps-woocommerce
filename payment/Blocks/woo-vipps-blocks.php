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
        register_block_type(__DIR__ . '/dist/buy-now-cart');
    }
});

// Add scripts for web components to the block editor. You would expect this to work with block.json or enqueue_block_editor_assets, but no, 
// that doesn't work at all. THIS works though.  IOK 2026-02-25
// https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/
add_action('enqueue_block_assets', function () {
    // CSS common for several blocks etc. Enqued both in admin and frontend. IOK 2025-02-25
    wp_enqueue_style('vipps-block-editor-css', plugins_url('../css/blocks.css', __FILE__), [], filemtime(dirname(dirname(__FILE__)) . "/css/blocks.css"));

    if (is_admin()) {
        // Add the on-site-messaging web component if we are in the admin area. 
        wp_enqueue_script('vipps-onsite-messageing');
        wp_enqueue_script('vipps-button');
    }
});

// Inject block config variables to block editor assets
add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script('vipps-button');

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
                    ['label' => __('Swedish', 'woo-vipps'), 'value' => 'sv'],
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

        $buy_now_variants = [
            ['label' => __('Primary', 'woo-vipps'), 'value' => 'primary'],
            ['label' => __('Dark', 'woo-vipps'), 'value' => 'dark'],
            ['label' => __('Light', 'woo-vipps'), 'value' => 'light'],
        ];

        $buy_now_verbs = [
            ['label' => __('Buy', 'woo-vipps'), 'value' => 'buy'],
            ['label' => __('Pay', 'woo-vipps'), 'value' => 'pay'],
            ['label' => __('Continue', 'woo-vipps'), 'value' => 'continue'],
            ['label' => __('Confirm', 'woo-vipps'), 'value' => 'confirm'],
            ['label' => __('Donate', 'woo-vipps'), 'value' => 'donate'],
            ['label' => __('Express', 'woo-vipps'), 'value' => 'express'],
        ];

        // Migrate from old variants to new config array by sending a map. LP 2026-07-01
        $variant_migration_map = [];
        foreach(array_keys(Vipps::instance()->get_express_logo_variants()) as $old_variant) {
            $new_config = Vipps::instance()->migrate_button_variant_to_config($old_variant);
            unset($new_config['brand']); // brand needs to be dynamic from payment method! LP 2026-07-01
            unset($new_config['language']);
            unset($new_config['stretched']);
            $variant_migration_map[$old_variant] = $new_config;
        }

        $buy_now_config = [
            'BuyNowWithVipps' => Vipps::instance()->vippsJSConfig['vippssmileurl'],
            'vippssmileurl' => Vipps::instance()->vippsJSConfig['vippssmileurl'],
            'vippsbuynowbutton' => Vipps::instance()->vippsJSConfig['vippsbuynowbutton'],
            'vippsbuynowdescription' => Vipps::instance()->vippsJSConfig['vippsbuynowdescription'],
            'languages' => $buy_now_languages,
            'variants' => $buy_now_variants,
            'verbs' => $buy_now_verbs,
            'vippsresturl' => '/woo-vipps/v1',
            'paymentMethod' => Vipps::instance()->get_payment_method_name(),
            'storeLanguage' => VIpps::instance()->get_customer_language(),
            'variantMigrationMap' => $variant_migration_map,
        ];
        wp_add_inline_script('woo-vipps-buy-now-editor-script', 'const vippsBuyNowBlockConfig = ' . json_encode($buy_now_config), 'before');

        // Buy now block for the minicart only. LP 2026-02-09
        $buy_now_cart_config = [
            'vippsbuynowdescription' => sprintf(__( 'Add a %1$s Buy Now-button to the mini-cart', 'woo-vipps'), $vipps->get_payment_method_name()),
            'vippsbuynowbutton' => $buy_now_config['vippsbuynowbutton'],
            'vippssmileurl' => $buy_now_config['vippssmileurl'],
            'BuyNowWithVipps' => $buy_now_config['BuyNowWithVipps'],
            'minicartLogo' => $vipps->get_payment_logo('minicart'),
        ];
        wp_add_inline_script('woo-vipps-buy-now-cart-editor-script', 'const vippsBuyNowCartBlockConfig = ' . json_encode($buy_now_cart_config), 'before');
    }
});
