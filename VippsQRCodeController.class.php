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
        $qr = get_post_meta($pid, '_vipps_qr_img', true); // The QR image
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

<div class="qrimageholder" style="padding-top:1rem; padding-bottom: 1rem; display:flex; justify-content: space-between">
   <?php if ($qr): ?> 
     <div class="qrimagedownloadbuttons" style="display:flex;flex-direction:column;">
       <a class="button downloadsvg" style="margin-top: .7rem">Download as SVG</a>
       <a class="button downloadpng" style="margin-top: .7rem">Download as PNG</a>
     </div>
     <div id='qrimage' class='qrimage' style='width:25%'><?php echo $qr ?></div> 
   <?php endif; ?> 
</div>

<script>
jQuery('.button.downloadsvg').click(function () {
var svgData = jQuery("#qrimage")[0].innerHTML;
var svgBlob = new Blob([svgData], {type:"image/svg+xml;charset=utf-8"});
var domUrl = window.URL || window.webkitURL || window;
var svgUrl = domUrl.createObjectURL(svgBlob);
var downloadLink = document.createElement("a");
downloadLink.href = svgUrl;
downloadLink.target = "_blank";
downloadLink.download = "mysvgfile.svg"; // FIXME ADD NICE TITLE
document.body.appendChild(downloadLink);
downloadLink.click();
document.body.removeChild(downloadLink);
});

jQuery('.button.downloadpng').click(function () {
var svgData = jQuery("#qrimage")[0].innerHTML;
var svgBlob = new Blob([svgData], {type:"image/svg+xml;charset=utf-8"});
var domUrl = window.URL || window.webkitURL || window;
var svgUrl = domUrl.createObjectURL(svgBlob);
var canvas = document.createElement("canvas");
canvas.width = 640; // FIXME ADD FILTER MAYBE
canvas.height= 640;
var ctx = canvas.getContext('2d');
var img = new Image();
img.onload = function () {
    ctx.drawImage(img, 0, 0);
    domUrl.revokeObjectURL(svgUrl);
    var imgURI = canvas.toDataURL('image/png').replace('image/png', 'image/octet-stream');

    var downloadLink = document.createElement("a");
    downloadLink.href = imgURI;
    downloadLink.target = "_blank";
    downloadLink.download = "mypngfile.png"; // FIXME ADD NICE TITLE
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);

  };

  img.src = svgUrl;
});


</script>


    <?php
    }


    // This handles "save post" from the admin backend. IOK 2022-04-13  
    public function save_post ($pid, $post, $update) {
        if (!isset($_POST['vipps_qr_metabox_nonce']) || !wp_verify_nonce($_POST['vipps_qr_metabox_nonce'], 'vipps_qr_metabox_save')) return;
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
        // Ensure we synch with backend when updating
        add_filter('update_post_metadata', array($this, 'update_post_metadata'), 10, 5);

        // On trash or delete, remove the QR code at vipps
        add_action('wp_trash_post', array($this, 'delete_qr_code')); // Before status goes to "trash"
        add_action('before_delete_post',array($this, 'delete_qr_code'));  // Before deletion, metadata still available

        // Support resurection - the post id should then be available again
        add_action('untrashed_post', array($this, 'undelete_qr_code'));
    }

    // Called when deleting or trashing a post
    public function delete_qr_code($pid) {
        $vid = get_post_meta($pid, '_vipps_qr_id', true); // Reference at Vipps
        if ($vid) {
            $api = WC_Gateway_Vipps::instance()->api;
            try {
                $api->delete_merchant_redirect_qr($vid);
             } catch (Exception $e) {
                 $Vipps::instance()->log(sprintf(__("Error deleting QR code: %s", 'woo-vipps'), $e->getMessage()), 'error');
                 // IOK FIXME ADD ADMIN MESSAGE IF ADMIN
             }
             // Delete data from Vipps, including the ID which should now be free again
             delete_post_meta($pid, '_vipps_qr_img');
             delete_post_meta($pid, '_vipps_qr_id');
        }
    }
    // Called when undeleting a post. We just need to recreate the QR code, so we do the same as when creating it.
    public function undelete_qr_code($pid) {
        $url = get_post_meta($pid, '_vipps_qr_url', true);
        if ($url) {
            $this->synch_url($pid, $url, 'NEW');
        }
    }


    public function update_post_metadata ($nullonsuccess, $pid, $meta_key, $meta_value, $prev_value) {
                if ($meta_key != '_vipps_qr_url') return $nullonsuccess;
                if (get_post_type($pid) != 'vipps_qr_code') return $nullonsuccess;
         
                // Prev is only passed when setting multi-values,
                if (!$prev_value) $prev_value = get_post_meta($pid, '_vipps_qr_url', true);
               
                return $this->synch_url($pid, $meta_value, $prev_value);
    }
 
    // Called when updateing the URL meta-value of the post type: Synch the object with Vipps
    public function synch_url($pid, $url , $prev) {
          $vid = get_post_meta($pid, '_vipps_qr_id', true); // Reference at Vipps
          $gotimage = get_post_meta($pid, '_vipps_qr_img', true); // We only fetch this if necessary
 
          try {
              $api = WC_Gateway_Vipps::instance()->api;
              if (!$vid) {
                  $prefix = $api->get_orderprefix();
                  $vid = apply_filters('woo_vipps_qr_id', $prefix . "-qr-" . $pid);
                  error_log("No stored id, creating as $vid");
                  delete_post_meta($pid, '_vipps_qr_img');
                  $ok = $api->create_merchant_redirect_qr ($vid, $url);
                  // Get actual vid from call here, but 
                  update_post_meta( $pid, '_vipps_qr_id', $vid);
              } else {
                 if ($url == $prev && $gotimage) {
                    error_log("No change, no sync");
                    $ok = null;
                 }  else {
                    error_log("Updating URL");
                    $ok = $api->update_merchant_redirect_qr ($vid, $url) ;
                 }
              }
              // This is a time-limited URL which can be used for one hour to download the QR code
              if ($ok && !$gotimage && isset($ok['url'])) {
                  try {
                      // We don't have to do this via the API, it's fine to use the normal get-content api. 
                      $data = $api->get_merchant_redirect_qr($ok['url']);
                      $img = $data['message']; // Could also be png if we allow that, currenlty svg
                      update_post_meta($pid, '_vipps_qr_img', $img);
                  } catch (Exception $e) {
                    $Vipps::instance()->log(sprintf(__("Error downloading QR code image: %s", 'woo-vipps'), $e->getMessage()), 'error');
                    // IOK FIXME ADD ADMIN MESSAGE IF ADMIN
                    // We couldn't get an image. Maybe link is to old, or something. Best thing to do would be to just 
                    // ask the user to save the link again.
                    // MUST THEN ENSURE THE url == prev ABOVE DOES NOT KICK IN. SO ADD CHECK FOR EXISTING IMG!
                    return false; // Means *do not* store value.
                  }
              }
          }  catch (Exception $e) {
              // IOK here *in particular* catch the 409 thing for previously used ID and retry (If not handled in api).
              // IOK FIXME ADD ADMIN MESSAGE
              // IOK FIXME ADD ADMIN MESSAGE IF ADMIN
              $Vipps::instance()->log(sprintf(__("Error creating or updating QR code: %s", 'woo-vipps'), $e->getMessage()), 'error');
              error_log(print_r($e, true));
              return false; // Means *do not* store value.
          }

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


