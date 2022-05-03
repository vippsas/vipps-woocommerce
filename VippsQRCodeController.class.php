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
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts')); 
        add_action('admin_footer', array($this, 'admin_footer'));

        add_filter('views_edit-vipps_qr_code', array($this, 'qr_list_views'));

        add_action('in_admin_header', function () {
            $screen = get_current_screen();
            if ($screen && $screen->id == 'vipps_qr_code') {
                  global $post;
                  if (!$post) return;
                  $errors =  get_post_meta($post->ID, '_vipps_qr_errors', true);
                  if ($errors) {
                      foreach ($errors as $err) {
                          add_action('admin_notices', function () use ($err) {
                              printf( '<div class="notice notice-error"><p>%s</p></div>',  esc_html( $err) ); 
                          });
                      }
                  delete_post_meta($post->ID, '_vipps_qr_errors');
                  }
            }
        });
    }
 
    public function admin_enqueue_scripts ($hook) {
      wp_enqueue_script('wc-enhanced-select');
      $screen = get_current_screen();
      if ($screen && $screen->id == 'edit-vipps_qr_code') {
          wp_enqueue_script( 'jquery-ui-dialog' );
          wp_enqueue_style( 'wp-jquery-ui-dialog' );
      }
    }

    // This also does some garbage collection/cleanup by deleting code-objects no longer present at Vipps. It is called on the QR code overview screen.
    public function get_all_qr_codes_at_vipps () {
        delete_transient('_woo_vipps_qr_codes'); // IOK FIXME REMOVE AFTER TESTING
        $stored = get_transient('_woo_vipps_qr_codes');
        if (is_array($stored)) return $stored;
        $api = WC_Gateway_Vipps::instance()->api;
        $all = $api->get_all_merchant_redirect_qr();
        foreach($all as &$entry) {
            $entry['get'] = $api->get_merchant_redirect_qr_entry($entry['id']);
        }
        set_transient('_woo_vipps_qr_codes', $all, HOUR_IN_SECONDS);
        return $all;
    }

    public function admin_footer() {
      $screen = get_current_screen();
      if ($screen && $screen->id == 'edit-vipps_qr_code') {
          $all = $this->get_all_qr_codes_at_vipps();
?>
<!-- The modal / dialog box, hidden somewhere near the footer on the QR screen. -->
<div id="vipps_unsynchronized_qr_codes" class="hidden" style="max-width:1200px;min-width:600px;" title="<?php _e('QR-codes not present on this site', 'woo-vipps'); ?>"
 <div>
  <p><?php _e("There are some QR codes present at Vipps that are not part of this Website. This may be because these are part of some <i>other</i> website using this same account, or they may have gotten 'lost' in a database restore - or maybe you are creating an entire new site. If neccessary, you can import these into your current site and manage them from here", 'woo-vipps'); ?></p>
  <pre><?php print_r($all); ?></pre>

</div>
<?php
      }
    }

    public function qr_list_views ($views) {
        $count = 2;
        $unsynch = "<a class='open_unsynchronized_qr_codes' href=\"javascript:void(0);\" title='" . __("Show QR codes not synchronized to this site", 'woo-vipps') . "'>";
        $unsynch .= __("Unsynchronized QR codes", 'woo-vipps');
        $unsynch .= " <span class='count'>($count)</span>";
        $unsynch .= "</a>";

        $views['unsynchronized'] = $unsynch;
        return $views;

    }

    public function metabox_url () {
        global $post;
        $pid = $post->ID;
        $id = get_post_meta($pid, '_vipps_qr_id', true); // Reference at Vipps
        $url = get_post_meta($pid, '_vipps_qr_url', true); // The URL to add/modify
        $qr = get_post_meta($pid, '_vipps_qr_img', true); // The QR image
        $qrpid  = get_post_meta($pid, '_vipps_qr_pid', true); // If linking to a product or page, this would be it
        $urltype = get_post_meta($pid, '_vipps_qr_urltype', true); // What kind of URL we are working with

        $pngsize = apply_filters('woo_vipps_qr_png_size', 640);  // width/height of PNG qr code
        $imagefilename = sanitize_file_name($post->post_title);  // Filename for downloaded svgs and pngs

        if (!$urltype) {
            // Unknown urltype, either a new QR or some database issue. Try to handle gracefully.
            if ($qrpid) {
               if (is_page($qrpid)) {
                   $urltype = 'pageid';
               } else if (get_post_type($qrpid) == 'product')  {
                   $urltype = 'prodid';
               }  else if ($url) { // Unknown product type, we should have the URL still though
                   $urltype = 'url';
               } else {
                   // This should never happen
                   $qrpid = 0;
                   $urltype = 'url';
               }
            } else if ($url) {
                $urltype = 'url';
            } else {
                // Brand new! Assume we want a page.
                $urltype = 'pageid';
            }
        }
        $pageid = ($urltype == 'pageid') ? $qrpid : 0;
        $prodid = ($urltype == 'productid') ? $qrpid : 0;


       $screen = get_current_screen();
        echo "current screen: " . $screen->id  . "<br>";

        wp_nonce_field("vipps_qr_metabox_save", 'vipps_qr_metabox_nonce' );
        ?>
            <label><?php echo esc_html__( 'QR-id', 'woo-vipps' ); ?>: <?php echo esc_html($id); ?></label><br>

<div class="url-section">
  <label><?php echo esc_html('Select link type', 'woo-vipps'); ?></label>
    <div class="link-selector">
       <label for="pageidtab"><?php _e("Page", 'woo-vipps'); ?>
        <input type=radio class="vipps_urltype" id=pageidtab value="pageid" name="_vipps_qr_urltype" <?php if ($urltype=='pageid') echo " checked "; ?>>
       </label>

       <label for="productidtab"><?php _e("Product", 'woo-vipps'); ?>
         <input type=radio class="vipps_urltype" id=productidtab value="productid" name="_vipps_qr_urltype" <?php if ($urltype=='productid') echo " checked "; ?>>
       </label>

       <label for="urltab"><?php _e("URL", 'woo-vipps'); ?>
         <input type=radio class="vipps_urltype" id=urltab value="url" name="_vipps_qr_urltype" <?php if ($urltype=='url') echo " checked "; ?>>
       </label>

    </div>
  <div class="url-options">
  <div class="url-option pageid <?php if ($urltype=='pageid') echo " active "; ?>">
     <label><?php echo esc_html__('Choose page', 'woo-vipps'); ?>:</label>
<?php wp_dropdown_pages(['selected'=>$pageid, 'echo'=>1, 'id'=>'page_id', 'name'=>'_vipps_qr_pid', 'class'=>'vipps-page-selector wc-enhanced-select', 'show_option_none'=> __('None chosen', 'woo-vipps')]); ?>
   </div>

   <div class="url-option productid <?php if ($urltype=='productid') echo " active "; ?>">
    <label><?php echo esc_html__('Choose product', 'woo-vipps'); ?>:</label>
        <select class="wc-product-search" style="width:100%"  id="product_id" name="_vipps_qr_pid" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products">
<?php if ($prodid): 
      $product = wc_get_product($prodid);
      if (is_a($product, 'WC_Product')):
        echo "<option value='" . intval($prodid) . "' selected>" . wp_kses_post( $product->get_formatted_name() ) . "</option>";
      endif; 
endif; ?> 
        </select>
   </div>


   <div class="url-option url <?php if ($urltype=='url') echo " active "; ?>">
       <label><?php echo esc_html__( 'URL', 'woo-vipps' ); ?></label>
            <input name="_vipps_qr_url" type="url" value="<?php echo esc_attr( sanitize_text_field( $url) ); ?>" style="width:100%;" />
   </div>
 </div>
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
downloadLink.download = <?php echo json_encode($imagefilename . ".svg"); ?>;
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
canvas.width = <?php echo json_encode($pngsize); ?>; 
canvas.height= <?php echo json_encode($pngsize); ?>;
var ctx = canvas.getContext('2d');
var img = new Image();
img.onload = function () {
    ctx.drawImage(img, 0, 0);
    domUrl.revokeObjectURL(svgUrl);
    var imgURI = canvas.toDataURL('image/png').replace('image/png', 'image/octet-stream');

    var downloadLink = document.createElement("a");
    downloadLink.href = imgURI;
    downloadLink.target = "_blank";
    downloadLink.download = <?php echo json_encode($imagefilename . ".png"); ?>;
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
    public function save_post ($qid, $post, $update) {
        if (!isset($_POST['vipps_qr_metabox_nonce']) || !wp_verify_nonce($_POST['vipps_qr_metabox_nonce'], 'vipps_qr_metabox_save')) return;
        if (!isset($_POST['_vipps_qr_urltype'])) return;

        $urltype = sanitize_title($_POST['_vipps_qr_urltype']);
        if (!$urltype) return false;

        // Product or page id
        $pid = intval($_POST['_vipps_qr_pid']);
        // Literal url
        $url = sanitize_url($_POST['_vipps_qr_url']); 

        $newurl = "";
        switch ($urltype) {
            case 'productid':
            case 'pageid':
                $newurl = get_permalink($pid);
                break;
            case 'url':
                $newurl = $url;
                $pid = 0;
                break;
        }
        update_post_meta( $qid, '_vipps_qr_urltype', sanitize_title($urltype));
        update_post_meta( $qid, '_vipps_qr_pid', intval($pid));

        if ($newurl){
            update_post_meta( $qid, '_vipps_qr_url', sanitize_url($newurl));
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
                delete_transient('_woo_vipps_qr_codes');
             } catch (Exception $e) {
                 Vipps::instance()->log(sprintf(__("Error deleting QR code: %s", 'woo-vipps'), $e->getMessage()), 'error');
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
              $stored = $vid ? $api-> get_merchant_redirect_qr_entry($vid) : null;

              // Generate a new Vipps ID if we have none.
              if (!$vid) {
                  $prefix = $api->get_orderprefix();
                  $vid = apply_filters('woo_vipps_qr_id', $prefix . "-qr-" . $pid);
              }
  
              // If object does not exist at Vipps, create it
              if (!$stored) {
                  $ok = $api->create_merchant_redirect_qr ($vid, $url);
                  delete_post_meta($pid, '_vipps_qr_img');
                  delete_transient('_woo_vipps_qr_codes');
                  update_post_meta( $pid, '_vipps_qr_id', $vid);
              } else {
                 if ($url == $prev && $gotimage) {
                    $ok = null;
                 }  else {
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
                    Vipps::instance()->log(sprintf(__("Error downloading QR code image: %s", 'woo-vipps'), $e->getMessage()), 'error');
                    if (is_admin() && ! wp_doing_ajax()) {
                      update_post_meta($pid, '_vipps_qr_errors', array(sprintf(__("Couldn't download QR image: try saving post again! Error was: %s", 'woo-vipps'), $e->getMessage())));
                    }
                    return false; // Means *do not* store value.
                  }
              }
          }  catch (Exception $e) {
              // IOK here *in particular* catch the 409 thing for previously used ID and retry (If not handled in api).
              Vipps::instance()->log(sprintf(__("Error creating or updating QR code: %s", 'woo-vipps'), $e->getMessage()), 'error');
              if (is_admin() && ! wp_doing_ajax()) {
                      $errors = array();
                      $errors[]=sprintf(__("Couldn't create or update QR image: %s", 'woo-vipps'),$e->getMessage());
                      $errors[] = "Code is '" . $e->responsecode . "'";
                      if ($e->responsecode == 409) {
                          $errors[] = __("It seems a QR code with this ID already exists at Vipps.  If you have recently done a database restore, try to instead import the QR code from the Unsynchronized codes, deleting this. If you have several Wordpress instances using the same Vipps account, make sure you use different prefixes (in the WooCommerce Vipps settings) - then delete this entry and create a new code", 'woo-vipps');
                      }
                 
                      update_post_meta($pid, '_vipps_qr_errors', $errors);
              }
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


