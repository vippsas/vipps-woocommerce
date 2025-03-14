<?php
/*
   This class is for the Vipps QR-code feature, and is implemented as its own singleton instance because this isolates that 'aspect' of the
   system. It is instantiated as a member of the main Vipps class.

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



class VippsQRCodeController {

    private static $instance = null;
    public function __construct() {
    }

    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsQRCodeController();
        return static::$instance;
    }

    public static function register_hooks() {
        // We can only support the QR api if we are connected to Vipps.
        if (!get_option('woo-vipps-configured', false)) return;
        // Some people may want to turn this off
        if (!apply_filters('woo_vipps_support_qr_codes', true)) return;

        $controller = static::instance();
        if (is_admin()) {
            add_action('admin_init',array($controller,'admin_init'));
            add_action('admin_menu',array($controller,'admin_menu'));
        } 
        add_action('init',array($controller,'init'));
        add_action('woocommerce_loaded', array($controller,'woocommerce_loaded'));
    }

    public function woocommerce_loaded () {
    }
    public function admin_init() {
        add_meta_box( '_vipps_qr_data', __( 'URL', 'woo-vipps' ), array( $this, 'metabox_url' ), 'vipps_qr_code', 'normal', 'default' );
        add_action('save_post_vipps_qr_code', array($this, 'save_post'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts')); 
        add_action('admin_footer', array($this, 'admin_footer'));

        add_filter('views_edit-vipps_qr_code', array($this, 'qr_list_views'));

        add_action('admin_post_vipps_qr_handle_unsynched', array($this, 'handle_unsynched'));

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

                  $vid =  get_post_meta($post->ID, '_vipps_qr_id', true);
                  if ($vid) {
                   // This is kept in a transient, so we should not need to call out
                   $all = $this->get_all_qr_codes_at_vipps();
                   if (!isset($all[$vid])) {
                          delete_post_meta($post->ID, '_vipps_qr_img');
                          add_action('admin_notices', function () use ($err) {
                              printf( '<div class="notice notice-error"><p>%s</p></div>',  sprintf(__("Somehow, this QR code has disappeard at %1\$s. Save it to make it work again!", 'woo-vipps'), "Vipps"));
                          });
                   }
                  }

            }
        });
    }

    public function admin_enqueue_scripts ($hook) {
      $screen = get_current_screen();
      if ($screen && $screen->id == 'edit-vipps_qr_code') {
          wp_enqueue_script( 'jquery-ui-dialog' );
          wp_enqueue_style( 'wp-jquery-ui-dialog' );
      }
      if ($screen && $screen->id == 'vipps_qr_code') {
          wp_enqueue_script('wc-enhanced-select');
          wp_enqueue_style('woocommerce_admin_styles');
      }
    }

    // This also does some garbage collection/cleanup by deleting code-objects no longer present at Vipps. It is called on the QR code overview screen.
    public function get_all_qr_codes_at_vipps () {
        $stored = get_transient('_woo_vipps_qr_codes');
        if (is_array($stored)) return $stored;
        $api = WC_Gateway_Vipps::instance()->api;
        $all = [];
        try {
            $all = $api->get_all_merchant_redirect_qr();
        }   catch (Exception $e) {
            Vipps::instance()->log(sprintf(__("Error getting list of QR codes from %1\$s: %2\$s", 'woo-vipps'), "Vipps", $e->getMessage()), 'error');
            delete_transient('_woo_vipps_qr_codes');
            delete_transient('_woo_vipps_unsynched_qr_codes'); 
            return $all;
        }
        $table = array();
        foreach($all as &$entry) {
            $table[$entry['id']] = $entry;
        }
        set_transient('_woo_vipps_qr_codes', $table, DAY_IN_SECONDS);
        return $table;
    }
    // Called only to check if there are unsynchronized codes, which are cached.
    public function get_all_local_qr_codes () {
        global $wpdb;
        $all = $wpdb->get_results("SELECT post_id, meta_key, meta_value from `{$wpdb->postmeta}` WHERE meta_key = '_vipps_qr_id'", ARRAY_A);
        if (!$all) return [];
        $table = [];
        foreach ($all as $entry) {
            $table[$entry['meta_value']] = $entry['post_id'];
        }
        return $table;
    }
    public function get_unsynchronized_qr_codes() {
        $stored = get_transient('_woo_vipps_unsynched_qr_codes');
        if (is_array($stored)) return $stored;
        $all = $this->get_all_qr_codes_at_vipps();
        $local = $this->get_all_local_qr_codes();
        $table = [];
        foreach(array_keys($all) as $key) {
            if (!isset($local[$key])) {
               $table[$key] = $all[$key];
            }
        }
        set_transient('_woo_vipps_unsynched_qr_codes', $table, DAY_IN_SECONDS);
        return $table;
    }

    // POST handler for the import-unsynched feature
    public function handle_unsynched  () {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }
        if (!isset($_POST['vipps_qr_import']) || !wp_verify_nonce($_POST['vipps_qr_import'], 'vipps_qr_import')) {
            wp_die(__('Invalid request', 'woo-vipps'));
        }
        $operation = "";
        if (isset($_POST['operation-delete']) && $_POST['operation-delete']) {
            $operation = "delete";
        } else if (isset($_POST['operation-import']) && $_POST['operation-import']) {
            $operation = "import";
        } else {
            wp_die(__('Unknown operation', 'woo-vipps'));
        }
       
        $qrids = isset($_POST['qrids']) ? $_POST['qrids'] : [];
        if (!is_array($qrids)) $qrids = [];
        if ($operation == 'delete') {
             $api = WC_Gateway_Vipps::instance()->api;
             foreach($qrids as $vid){
                try {
                    $api->delete_merchant_redirect_qr($vid);
                } catch (Exception $e) {
                    Vipps::instance()->log(sprintf(__("Error deleting unsynched QR code with id %1\$s: %2\$s", 'woo-vipps'),$vid, $e->getMessage()), 'error');
                }
             }
        } else if ($operation == 'import')  {
             $all = $this->get_all_qr_codes_at_vipps();
             foreach($qrids as $vid){
                 $entry = $all[$vid];
                 $url = isset($entry['redirectUrl']) ? $entry['redirectUrl'] : home_url("/");
                 if (!$url) {
                    continue;
                 }
                 try {
                     $postargs = array(
                        'post_title' => __("Imported QR Code", 'woo-vipps'),
                        'post_excerpt' => __("Imported QR Code", 'woo-vipps'),
                        'post_status' => 'publish',
                        'post_type' => 'vipps_qr_code',
                        'meta_input' => ['_vipps_qr_id' => $vid, '_vipps_qr_urltype'=>'url', '_vipps_qr_stored' => true]
                     );
                     $ok = wp_insert_post($postargs, 'wp_error', true);
                     if ($ok) {
                         // This will trigger update + image download
                         update_post_meta($ok, '_vipps_qr_url', sanitize_url($url));
                     }
                     if (is_wp_error($ok)) {
                        Vipps::instance()->log(sprintf(__("Error importing unsynched QR code with id %1\$s: %2\$s", 'woo-vipps'),$vid, $e->get_error_message()), 'error');
                     }
                 } catch (Exception $e) {
                     Vipps::instance()->log(sprintf(__("Error importing unsynched QR code with id %1\$s: %2\$s", 'woo-vipps'),$vid, $e->getMessage()), 'error');
                 }
             }

        }
        delete_transient('_woo_vipps_qr_codes');
        delete_transient('_woo_vipps_unsynched_qr_codes'); 
        wp_redirect(admin_url("/edit.php?post_type=vipps_qr_code"));
    }

    public function admin_footer() {
      $screen = get_current_screen();
      if ($screen && $screen->id == 'edit-vipps_qr_code') {
          $all = $this->get_unsynchronized_qr_codes();
?>
<!-- The modal / dialog box, hidden somewhere near the footer on the QR screen. -->
<div id="vipps_unsynchronized_qr_codes" class="hidden" title="<?php _e('Vipps QR-codes not present on this site', 'woo-vipps'); ?>"
 <div>
  <p><?php _e("There are some QR codes present at Vipps that are not part of this Website. This may be because these are part of some <i>other</i> website using this same account, or they may have gotten 'lost' in a database restore - or maybe you are creating an entire new site. If neccessary, you can import these into your current site and manage them from here", 'woo-vipps'); ?></p>
  <div class="importsection">
    <form action="<?php echo admin_url('/admin-post.php'); ?>" method="POST" onsubmit="return confirm('<?php _e("Are you sure?", 'woo-vipps'); ?>');">
    <?php wp_nonce_field("vipps_qr_import", 'vipps_qr_import' ); ?>
    <input type="hidden" name="action" value="vipps_qr_handle_unsynched">
    <div class="buttonsection">
        <button class="button btn primary" name="operation-import" value="vipps_qr_unsynch_import"><?php _e("Import", 'woo-vipps'); ?></button>
        <button class="button btn secondary" name="operation-delete" value="vipps_qr_unsynch_delete"><?php _e("delete", 'woo-vipps'); ?></button>
    </div>
    <table class="table importtable">
      <thead>
        <tr><th class='checkboxcell'><th class="idcell"><?php _e("Id", 'woo-vipps'); ?></th><th class='urlcell'><?php _e('URL', 'woo-vipps'); ?></th></tr>
      </thead>
      <tbody>
      <?php foreach ($all as $id=>$entry): ?>
        <tr>
         <td class='checkboxcell'><input type="checkbox" name='qrids[]' value="<?php echo esc_attr($id); ?>"></td>
         <td class='idcell'><?php echo esc_html($id); ?></td>
         <?php $urlvalue = (isset($entry['redirectUrl'])) ? $entry['redirectUrl'] : home_url("/"); ?>
         <td class='urlcell'><input type="url" readonly value="<?php echo esc_url($urlvalue); ?>"></td>
        </tr>
      

      <?php endforeach; ?>
      </tbody>
    </table>
    
    </form>
  </div>

</div>
<?php
      }
    }

    public function qr_list_views ($views) {
        $all = $this->get_unsynchronized_qr_codes();
        $count = count(array_keys($all));
        if ($count>0) {
            $unsynch = "<a class='open_unsynchronized_qr_codes' href=\"javascript:void(0);\" title='" . __("Show QR codes not synchronized to this site", 'woo-vipps') . "'>";
            $unsynch .= __("Unsynchronized QR codes", 'woo-vipps');
            $unsynch .= " <span class='count'>($count)</span>";
            $unsynch .= "</a>";

            $views['unsynchronized'] = $unsynch;
        }
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

        wp_nonce_field("vipps_qr_metabox_save", 'vipps_qr_metabox_nonce' );
        ?>
            <label><?php echo esc_html__( 'QR-id', 'woo-vipps' ); ?>: <?php echo esc_html($id); ?></label><br>

<div class="url-section">
  <label><?php echo esc_html__('Select link type', 'woo-vipps'); ?></label>
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
       <a class="button downloadsvg" style="margin-top: .7rem"><?php echo esc_html__("Download as SVG", 'woo-vipps'); ?></a>
       <a class="button downloadpng" style="margin-top: .7rem"><?php echo esc_html__("Download as PNG", 'woo-vipps'); ?></a>
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

        // MUST BE LAST!
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
             } catch (Exception $e) {
                 Vipps::instance()->log(sprintf(__("Error deleting QR code: %1\$s", 'woo-vipps'), $e->getMessage()), 'error');
             }
             // Delete data from Vipps, including the ID which should now be free again
             delete_post_meta($pid, '_vipps_qr_img');
             delete_post_meta($pid, '_vipps_qr_id');
             delete_post_meta($pid, '_vipps_qr_stored');
        }
        delete_transient('_woo_vipps_qr_codes');
        delete_transient('_woo_vipps_unsynched_qr_codes'); 
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
          $notnew = get_post_meta($pid, '_vipps_qr_stored', true); // Only false if we have never stored this entry.
          $api = WC_Gateway_Vipps::instance()->api;

          $stored = null;
          try {
              $stored = $notnew ? $api-> get_merchant_redirect_qr_entry($vid) : null;
          } catch (Exception $e) {
              Vipps::instance()->log(sprintf(__("QR image with id %2\$s that was supposed to be stored at %1\$s, isnt. Recreating it. Error was:. : %3\$s", 'woo-vipps'), "Vipps", $vid, $e->getMessage()), 'debug');
          }

          // Assume these have or will change. This way these can be updated by saving any QR code. 
          delete_transient('_woo_vipps_qr_codes');
          delete_transient('_woo_vipps_unsynched_qr_codes'); 

          try {
              // Generate a new Vipps ID if we have none.
              if (!$vid) {
                  $prefix = $api->get_orderprefix();
                  $vid = apply_filters('woo_vipps_qr_id', $prefix . "-qr-" . get_post($pid)->post_name, $pid);
              }
  
              // If object does not exist at Vipps, create it
              if (!$stored) {
                  $ok = $api->create_merchant_redirect_qr ($vid, $url);
                  update_post_meta( $pid, '_vipps_qr_stored', true);
                  update_post_meta( $pid, '_vipps_qr_id', $vid);
                  delete_post_meta($pid, '_vipps_qr_img');
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
                    Vipps::instance()->log(sprintf(__("Error downloading QR code image: %1\$s", 'woo-vipps'), $e->getMessage()), 'error');
                    if (is_admin() && ! wp_doing_ajax()) {
                      update_post_meta($pid, '_vipps_qr_errors', array(sprintf(__("Couldn't download QR image: try saving post again! Error was: %1\$s", 'woo-vipps'), $e->getMessage())));
                    }
                    return false; // Means *do not* store value.
                  }
              }
          }  catch (Exception $e) {
              // IOK here *in particular* catch the 409 thing for previously used ID and retry (If not handled in api).
              Vipps::instance()->log(sprintf(__("Error creating or updating QR code: %1\$s", 'woo-vipps'), $e->getMessage()), 'error');
              if (is_admin() && ! wp_doing_ajax()) {
                      $errors = array();
                      $errors[]=sprintf(__("Couldn't create or update QR image: %1\$s", 'woo-vipps'),$e->getMessage());
                      if ($e->responsecode == 409) {
                          delete_post_meta($pid, '_vipps_qr_id');
                          delete_post_meta($pid, '_vipps_qr_stored');
                          delete_post_meta($pid, '_vipps_qr_img');
                          $errors[] = sprintf(__("It seems a QR code with this ID (%1\$s) already exists at Vipps.  If you have recently done a database restore, try to instead import the QR code from the Unsynchronized codes, deleting this. If you have several Wordpress instances using the same Vipps account, make sure you use different prefixes (in the WooCommerce Vipps settings). You can try to change the slug of this entry (you may need to enable this in the page settings) and save again if you don't care about the duplicate. ", 'woo-vipps'), sanitize_title($vid));
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
                'name_admin_bar'        => sprintf(__( '%1$s QR Codes', 'woo-vipps' ), "Vipps"),
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
                'label'                 => sprintf(__( '%1$s QR Code', 'woo-vipps' ), "Vipps"),
                'description'           => sprintf(__( '%1$s QR Codes', 'woo-vipps' ), "Vipps"),
                'labels'                => $labels,
                'supports'              => array( 'title',  'custom-fields' ),
                'taxonomies'            => array( 'post', 'tag' ),
                'hierarchical'          => false,
                'public'                => false,
                'show_ui'               => true,
                'show_in_menu'          => false,
                'menu_position'         => 10,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => false,
                'can_export'            => true,
                'has_archive'           => false,
                'exclude_from_search'   => true,
                'publicly_queryable'    => false,
                'capabilities'          => $capabilities,
                'show_in_rest'          => true,
                );
        register_post_type( 'vipps_qr_code', $args );

    }

}


