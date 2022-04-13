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
        add_action('plugins_loaded', array($controller,'plugins_loaded'));
        add_action('woocommerce_loaded', array($controller,'woocommerce_loaded'));
    }

    public function plugins_loaded() {
    }
    public function woocommerce_loaded () {
    }
    public function admin_init() {
        add_meta_box( '_vipps_qr_data', __( 'URL', 'woo-vipps' ), array( $this, 'metabox_url' ), 'vipps_qr_code', 'normal', 'default' );
        add_action('save_post_vipps_qr_code', array($this, 'save_post'), 10, 3);
    }

    public function metabox_url () {
        global $post;
        $pid = $post->ID;
        $id = get_post_meta($pid, '_vipps_qr_id', true); // Reference at Vipps
        $url = get_post_meta($pid, '_vipps_qr_url', true); // The URL to add/modify
        $qr = get_post_meta($pid, '_vipps_qr_img', true); // The URL to add/modify
        $qrgen = get_post_meta($pid, '_vipps_qr_imggen', true);
        $qrpid  = get_post_meta($pid, '_vipps_qr_pid', true); // If linking to a product or page, this would be it

        wp_nonce_field("vipps_qr_metabox_save", 'vipps_qr_metabox_nonce' );
        ?>
            <label><?php echo esc_html__( 'QR-id', 'woo-vipps' ); ?>: <?php echo esc_html($id); ?></label><br>

            <div>
            <label><?php echo esc_html__( 'URL', 'woo-vipps' ); ?></label>

<?
// "link til: url, produkt, side" radioboks, og så dropdowner for produkt og side, lagre "pid" for disse og sørg for å oppdatere tax når lagres.
?>


            <input name="_vipps_qr_url" type="url" value="<?php echo esc_attr( sanitize_text_field( $url) ); ?>" style="width:100%;" />
            </div>

            <div>
            <label for="excerpt"><?php _e( 'Description' ); ?></label><textarea style="margin-top:0px; width:100%" rows="1" cols="40" name="excerpt" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
        </div>

            <?php

echo "<pre>"; echo $qrgen ; echo "<pre>";
echo "<pre>";
          $api = WC_Gateway_Vipps::instance()->api;
          #print_r($api->get_merchant_redirect_qr('foo'));
echo "</pre>";

 

    }


    // This handles "save post" from the admin backend. IOK 2022-04-13  
    public function save_post ($pid, $post, $update) {
        if (!wp_verify_nonce($_POST['vipps_qr_metabox_nonce'], 'vipps_qr_metabox_save')) {
            wp_die(__("Cannot save QR code data with invalid nonce!", 'woo-vipps'));
        }
        if ( isset( $_POST['_vipps_qr_url'] ) ) {
            $newurl = $_POST['_vipps_qr_url'];
            update_post_meta( $pid, '_vipps_qr_url', sanitize_url($newurl));
        }
    }


    public function admin_menu () {

        // Vipps QR-codes are handled as a custom post type, we add them to the Vipps admin menu
        add_submenu_page( 'vipps_admin_menu', __('QR Codes', 'woo-vipps'), __('QR Codes', 'woo-vipps'), 'manage_woocommerce', 'edit.php?post_type=vipps_qr_code', null, 20);
    }

    public function init () {
        $this->register_vipps_qr_code();
        add_filter('update_post_metadata', array($this, 'update_post_metadata'), 10, 5);
    }

    public function update_post_metadata ($nullonsuccess, $pid, $meta_key, $meta_value, $prev_value) {
                if ($meta_key != '_vipps_qr_url') return $nullonsuccess;
                if (get_post_type($pid) != 'vipps_qr_code') return $nullonsuccess;
               
                error_log("In it to win it $meta_value was $prev_value"); 
                return $this->synch_url($pid, $meta_value, $prev_value);
    }
 
    // Called when updateing the URL meta-value of the post type: Synch the object with Vipps
    public function synch_url($pid, $url , $prev) {
          error_log("Synching $url for $pid, prev is $prev");
          $vid = get_post_meta($pid, '_vipps_qr_id', true); // Reference at Vipps
          $create = false;
 
          try {
              $api = WC_Gateway_Vipps::instance()->api;
              if (!$vid) {
                  $prefix = $api->get_orderprefix();
                  $vid = apply_filters('woo_vipps_qr_id', $prefix . "-qr-" . $pid);
                  error_log("No stored id, creating as $vid");
                  $ok = $api->create_merchant_redirect_qr ($url);
                  // Get actual vid from call here, but 
                  update_post_meta( $pid, '_vipps_qr_id', $vid);
              } else {
                 if ($url == get_post_meta($pid, '_vipps_qr_url', true)) {
                    error_log("No change, no sync");
                    $ok = null;
                 }  else {
                    $ok = $api->update_merchant_redirect_qr ($vid, $url) ;
                 }
              }
              error_log(print_r($ok, true));
          }  catch (Exception $e) {
              error_log("BONG");
              error_log(print_r($e, true));
          }

          // IOK FIXME if "is admin" here, we need to add a message about the error. 
          return null;  // null means "ok, store in database". 
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
                'edit_post'             => 'manage_woocommerce',
                'read_post'             => 'manage_woocommerce',
                'delete_post'           => 'manage_woocommerce',
                'edit_posts'            => 'manage_woocommerce',
                'edit_others_posts'     => 'manage_woocommerce',
                'publish_posts'         => 'manage_woocommerce',
                'read_private_posts'    => 'manage_woocommerce',
                );
        $args = array(
                'label'                 => __( 'Vipps QR Code', 'woo-vipps' ),
                'description'           => __( 'Vipps QR Codes', 'woo-vipps' ),
                'labels'                => $labels,
                'supports'              => array( 'title',  'custom-fields' ),
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


