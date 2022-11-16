<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Vipps on-site messaging badge Gutenberg block.
 *
 * @package woo-vipps

 This file is part of the plugin Checkout with Vipps for WooCommerce
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
        $applications = array(array('label'=> __("Test Thing", 'woo-vipps'), 'value'=>'wordpress'));

        $applications[] = array('label'=> __("Other Thing", 'woo-vipps'), 'value'=>'other');
        $localizations['applications'] = $applications;

        $localizations['BlockTitle'] = __('Vipps On-site Messaging badge', 'woo-vipps'); 

        $localizations['Application'] = __('Application', 'woo-vipps');
        $localizations['ApplicationsText'] = __('The continue with Vipps-button can perform different actions depending on what is defined in your system. Per default it will log you in to WordPress or WooCommerce if installed, but plugins and themes can define more', 'woo-vipps');
        $localizations['Title'] = __('Title', 'woo-vipps');
        $localizations['TitleText'] = __('This will be used as the title/popup of the button', 'woo-vipps');
 
        $localizations['DefaultTextPrelogo'] = __('Whatever', 'woo-vipps'); 
        $localizations['DefaultTextPostlogo'] = __('!', 'woo-vipps'); 
        $localizations['DefaultTitle'] = __('Whatever!', 'woo-vipps'); 


        $localizations['logosrc'] = plugins_url('../../img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $localizations['vippssmileurl'] = plugins_url('../../img/vipps-smile-orange.png',__FILE__);

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

	$style_css = 'vipps-badge/style.css';
	wp_register_style(
		'vipps-badge-block',
		plugins_url( $style_css, __FILE__ ),
		array(),
		filemtime( "$dir/$style_css" )
	);

	register_block_type( 'woo-vipps/vipps-badge', array(
		'editor_script' => 'vipps-badge-block-editor',
		'editor_style'  => 'vipps-badge-block-editor',
		'style'         => 'vipps-badge-block',
	) );
}
add_action( 'init', 'vipps_badge_init' );
