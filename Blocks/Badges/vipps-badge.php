<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Vipps on-site messaging badge Gutenberg block.
 *
 * @package woo-vipps

 This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
   Copyright (c) 2022 WP-Hosting AS


   MIT License

   Copyright (c) 2022 WP-Hosting AS

   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:

   The above copyright notice and this permission notice shall be included in all
   copies or substantial portions of the Software.

   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
   SOFTWARE.
*/

/**
 * Registers all block assets so that they can be enqueued through Gutenberg in
 * the corresponding context.
 *
 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
function vipps_badge_init() {
	// Skip block registration if Gutenberg is not enabled/merged.
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$dir = dirname( __FILE__ );


     
        $localizations = array();
        $variants = array (
           array('label'=> __("White", 'woo-vipps'), 'value'=>'white'),
           array('label'=> __("Grey", 'woo-vipps'), 'value'=>'grey'),
           array('label'=> __("Orange", 'woo-vipps'), 'value'=>'orange'),
           array('label'=> __("Light Orange", 'woo-vipps'), 'value'=>'light-orange'),
           array('label'=> __("Purple", 'woo-vipps'), 'value'=>'purple'));

        $localizations['variants'] = $variants;
        $localizations['defaultvariant'] = 'white';

        $localizations['BlockTitle'] = sprintf(__('%1$s On-Site Messaging Badge', 'woo-vipps'), "Vipps"); 

        $localizations['Variant'] = __('Variant', 'woo-vipps');
        $localizations['VariantText'] = __('Choose the badge variant with the perfect colors for your site', 'woo-vipps');

        $localizations['VippsLater'] = sprintf(__('%1$s senere', 'woo-vipps'), "Vipps");
        $localizations['VippsLaterText'] = sprintf(__('Add support for %1$s Senere, if your store provides it', 'woo-vipps'), "Vipps");

        $localizations['Language'] = __('Language', 'woo-vipps');
        $localizations['LanguageText'] = __('Choose language, or use the default', 'woo-vipps');
        $localizations['languages'] = array(
            ['label'=>__('Default', 'woo-vipps'), 'value'=>'default'],
            ['label'=>__('English', 'woo-vipps'), 'value'=>'en'],
            ['label'=>__('Norwegian', 'woo-vipps'), 'value'=>'no']);

        $localizations['Amount'] = __('Amount in minor units', 'woo-vipps');
        $localizations['AmountText'] = __('You can add an amount for the badge here, in the minor units of the currency (e.g. for NOK, in øre)', 'woo-vipps');
 

        $localizations['vippssmileurl'] = plugins_url('../../img/vipps-mobilepay-logo-only.png',__FILE__);

	$index_js = 'vipps-badge/index.js';
	wp_register_script(
		'vipps-badge-block-editor',
		plugins_url( $index_js, __FILE__ ),
		array(
			'wp-blocks',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-i18n',
			'wp-element',
                       'vipps-onsite-messageing'
		),
		filemtime( "$dir/$index_js" )
	);
        wp_localize_script('vipps-badge-block-editor', 'VippsBadgeBlockConfig', $localizations);

	$editor_css = 'vipps-badge/editor.css';
	wp_register_style(
		'vipps-badge-block-editor',
		plugins_url( $editor_css, __FILE__ ),
		array(),
		filemtime( "$dir/$editor_css" )
	);


	register_block_type( 'woo-vipps/vipps-badge', array(
                'textdomain' => 'woo-vipps',
		'editor_script' => 'vipps-badge-block-editor',
		'editor_style'  => 'vipps-badge-block-editor'
	) );
}
add_action( 'init', 'vipps_badge_init' );
