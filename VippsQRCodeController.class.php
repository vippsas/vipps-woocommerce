<?php
/*
   This class is for the Vipps QR-code feature, and is implemented as its own singleton instance because this isolates that 'aspect' of the
   system. It is instantiated as a member of the main Vipps class.

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



class VippsQRCodeController {

    private static $instance = null;
    public function __construct() {
    }

    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsQRCodeController();
        return static::$instance;
    }

    public static function register_hooks() {
        $controller = static::instance();
        if (is_admin()) {
            add_action('admin_init',array($controller,'admin_init'));
            add_action('admin_menu',array($controller,'admin_menu'));
        } 
        add_action('init',array($controller,'init'));
        add_action( 'plugins_loaded', array($controller,'plugins_loaded'));
        add_action( 'woocommerce_loaded', array($controller,'woocommerce_loaded'));
    }

    public function plugins_loaded() {
    }
    public function woocommerce_loaded () {
    }
    public function admin_init() {
    }


    public function admin_menu () {
            // Vipps QR-codes are handled as a custom post type, we add them to the Vipps admin menu
            add_submenu_page( 'vipps_admin_menu', __('QR Codes', 'woo-vipps'), __('QR Codes', 'woo-vipps'), 'manage_woocommerce', 'edit.php?post_type=vipps_qr_code', null, 20);
    }

    public function init () {
        $this->register_vipps_qr_code();
    }
    // Register Custom Post Type
    public function register_vipps_qr_code() {

        $labels = array(
                'name'                  => _x( 'Vipps QR Codes', 'Post Type General Name', 'woo-vipps' ),
                'singular_name'         => _x( 'Vipps QR Code', 'Post Type Singular Name', 'woo-vipps' ),
                'menu_name'             => __( 'QR Codes', 'woo-vipps' ),
                'name_admin_bar'        => __( 'Vipps QR Codes', 'woo-vipps' ),
                'archives'              => __( 'Item Archives', 'woo-vipps' ),
                'attributes'            => __( 'Item Attributes', 'woo-vipps' ),
                'parent_item_colon'     => __( 'Parent Item:', 'woo-vipps' ),
                'all_items'             => __( 'QR Codes', 'woo-vipps' ),
                'add_new_item'          => __( 'Add New QR Code', 'woo-vipps' ),
                'add_new'               => __( 'Add New', 'woo-vipps' ),
                'new_item'              => __( 'New QR Code', 'woo-vipps' ),
                'edit_item'             => __( 'Edit QR Code', 'woo-vipps' ),
                'update_item'           => __( 'Update QR Code', 'woo-vipps' ),
                'view_item'             => __( 'View QR Code', 'woo-vipps' ),
                'view_items'            => __( 'View QR Codes', 'woo-vipps' ),
                'search_items'          => __( 'Search Item', 'woo-vipps' ),
                'not_found'             => __( 'Not found', 'woo-vipps' ),
                'not_found_in_trash'    => __( 'Not found in Trash', 'woo-vipps' ),
                'featured_image'        => __( 'Featured Image', 'woo-vipps' ),
                'set_featured_image'    => __( 'Set featured image', 'woo-vipps' ),
                'remove_featured_image' => __( 'Remove featured image', 'woo-vipps' ),
                'use_featured_image'    => __( 'Use as featured image', 'woo-vipps' ),
                'insert_into_item'      => __( 'Insert into item', 'woo-vipps' ),
                'uploaded_to_this_item' => __( 'Uploaded to this item', 'woo-vipps' ),
                'items_list'            => __( 'Items list', 'woo-vipps' ),
                'items_list_navigation' => __( 'Items list navigation', 'woo-vipps' ),
                'filter_items_list'     => __( 'Filter items list', 'woo-vipps' ),
                );
        $capabilities = array(
                'edit_post'             => 'edit_post',
                'read_post'             => 'read_post',
                'delete_post'           => 'delete_post',
                'edit_posts'            => 'edit_posts',
                'edit_others_posts'     => 'edit_others_posts',
                'publish_posts'         => 'publish_posts',
                'read_private_posts'    => 'read_private_posts',
                );
        $args = array(
                'label'                 => __( 'Vipps QR Code', 'woo-vipps' ),
                'description'           => __( 'Vipps QR Codes', 'woo-vipps' ),
                'labels'                => $labels,
                'supports'              => array( 'title', 'custom-fields' ),
                'taxonomies'            => array( 'post', 'tag' ),
                'hierarchical'          => false,
                'public'                => true,
                'show_ui'               => true,
                'show_in_menu'          => false,
                'menu_position'         => 10,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => false,
                'can_export'            => true,
                'has_archive'           => false,
                'exclude_from_search'   => true,
                'publicly_queryable'    => true,
                'capabilities'          => $capabilities,
                'show_in_rest'          => true,
                );
        register_post_type( 'vipps_qr_code', $args );

    }

}


