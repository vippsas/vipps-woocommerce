<?php
/*
   Plugin Name: Woocommerce Vipps Payment Module
   Description: Offer Vips as a payment method for Woocommerce
   Author: Iver Odin Kvello
   Version: 0.9
 */
require_once(dirname(__FILE__) . "/exceptions.php");

/* This class is for hooks and plugin managent, and is instantiated as a singleton. IOK 2018-02-07*/
class Vipps {
    /* This directory stores the files used to speed up the callbacks checking the order status. IOK 2018-05-04 */
    private $callbackDirname = 'wc-vipps-status';

    function __construct() {
    }

    public function admin_init () {
    }

    public function admin_menu () {
    }

    public function init () {
        // IOK move this to a wp-cron job so it doesn't run like every time 2018-05-03
        $o = new WC_Order(148);
        $k = $this->createCallbackSignal($o, 0);
        $this->cleanupCallbackSignals();
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source','Vipps Woo Gateway');
        $logger->log($type,$what,$context);
    }

    // This function will create a file with an obscure filename in the $callbackDirname directory.
    // When initiating payment, this file will be created with a zero value. When the response is reday,
    // it will be rewritten with the value 1.
    // This function can fail if we can't write to the directory in question, in which case, return null and
    // to the check with admin-ajax instead. IOK 2018-05-04
    public function createCallbackSignal($order,$ok=0) {
        $fname = $this->callbackSignal($order);
        if (!$fname) return null;
        if ($ok) {
            @file_put_contents($fname,"1");
        }else {
            @file_put_contents($fname,"0");
        }
        if (is_file($fname)) return $fname;
        return null;
    }

    //Helper function that produces the signal file name for an order IOK 2018-05-04
    public function callbackSignal($order) {
        $dir = $this->callbackDir();
        if (!$dir) return null;
        $fname = 'vipps-'.md5($order->get_order_key() . $order->get_meta('_vipps_transaction'));
        return $dir . DIRECTORY_SEPARATOR . $fname;
    }
    // URL of the above product thing
    public function callbackSignalURL($signal) {
      if (!$signal) return "";
      $uploaddir = wp_upload_dir();
      return $uploaddir['baseurl'] . '/' . $this->callbackDirname . '/' . basename($signal);
    }

    // Clean up old signals. IOK 2018-05-04. They should contain no useful information, but still. IOK 2018-05-04
    public function cleanupCallbackSignals() {
        $dir = $this->callbackDir();
        if (!is_dir($dir)) return;
        $signals = scandir($dir);
        $now = time();
        foreach($signals as $signal) {
            $path = $dir .  DIRECTORY_SEPARATOR . $signal;
            if (is_dir($path)) continue;
            if (is_file($path)) {
                $age = @filemtime($path);
                $halfhour = 30*60*60;
                if (($age+$halfhour) < $now) {
                    @unlink($path);
                }
            }
        }
    }

    // Returns the name of the callback-directory, or null if it doesn't exist. IOK 2018-05-04
    private function callbackDir() {
        $uploaddir = wp_upload_dir();
        $base = $uploaddir['basedir'];
        $callbackdir = $base . DIRECTORY_SEPARATOR . $this->callbackDirname;
        if (is_dir($callbackdir)) return $callbackdir;
        $ok = mkdir($callbackdir, 0755);
        if ($ok) return $callbackdir; 
        return null;
    }


    // Because the prefix used to create the Vipps order id is editable
    // by the user, we will store that as a meta and use this for callbacks etc.
    public function getOrderIdByVippsOrderId($vippsorderid) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vipps_orderid' AND meta_value = %s", $vippsorderid) );
    }

    // Temporary redirect handler! IOK FIXME REPLACE IOK 2018-04-23
    // This needs to be an actual page instead, which must be created on plugin activate
    // and then selected, and error-handling added and so forth.
    public function template_redirect() {
        // Check if using pretty links, if so, use the pretty link, otherwise use a GET parameter which we will need to add, ala VFlow=orderid
        $isvippscheckout = 0;
        $orderid=0;
        if ( get_option('permalink_structure')) {
            if (preg_match("!^/vipps-betaling/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) { 
                $isvippscheckout = 1;
            }
        } else {
            if (isset($_GET['VippsBetaling'])) {
                $isvippscheckout = 1;
            }
        } 

        if ($isvippscheckout) {
            // Call a method here in the gatway here IOK FIXME
            status_header(200,'OK');
            $orderid = WC()->session->get('_vipps_pending_order');
            $order = null;
            if ($orderid) {
                $order = new WC_Order($orderid); 
            }
            if (!$order) wp_die(__('Unknown order', 'vipps'));

//            wp_enqueue_script('check-vipps',plugins_url('js/check-order-status.js',__FILE__),array('jquery'),'1.0', 'true');
            wp_enqueue_script('check-vipps',plugins_url('js/check-order-status.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/check-order-status.js"), 'true');
            wp_add_inline_script('check-vipps','var vippsajaxurl="'.admin_url('admin-ajax.php').'";', 'before');

            // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
            // and check that the order matches (and is 'pending') (and exists)
            $transid = $order->get_meta('_vipps_transaction');
            $vippsstamp = $order->get_meta('_vipps_init_timestamp');
            $vippsstatus = $order->get_meta('_vipps_init_status');
            $message = __($order->get_meta('_vipps_confirm_message'),'vipps');
            
            $signal = $this->callbackSignal($order);
            $content .= "<div id='waiting'><p>" . __('Confirm your purchase in your Vipps app','vipps');

            if ($signal && !is_file($signal)) $signal = '';
            $signalurl = $this->callbackSignalURL($signal);

            $content .= '<span id=vippsstatus>'.htmlspecialchars("$message\n$vippsstatus\n" . date('Y-m-d H:i:s',$vippsstamp)) .'</span>';
            $content .= "<span id='vippstime'></span>";
            $content .= "</p></div>";

            $content .= "<form id='vippsdata'>";
            $content .= "<input type='hidden' id='fkey' name='fkey' value='".htmlspecialchars($signalurl)."'>";
            $content .= "<input type='hidden' name='key' value='".htmlspecialchars($order->get_order_key())."'>";
            $content .= "<input type='hidden' name='transaction' value='".htmlspecialchars($transid)."'>";
            $content .= "<input type='hidden' name='action' value='check_order_status'>";
            $content .= wp_nonce_field('vippsstatus','sec',1,false); 
            $content .= "</form>";
 
            require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
            $gw = new WC_Gateway_Vipps();
 
            $content .= "<div id='error' style='display:none'><p>".__('Error during order confirmation','vipps'). '</p>';
            $content .= "<p>" . __('An error occured during order confirmation. The error has been logged. Please contact us to determine the status of your order', 'vipps') . "</p>";
            $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','vipps') . '</a></p>';
            $content .= "</div>";
      
            $content .= "<div id=success style='display:none'><p>". __('Order confirmed', 'vipps') . '</p>';
            $content .= "<p><a class='btn button' id='continueToThankYou' href='" . $gw->get_return_url($order)  . "'>".__('Continue','vipps') ."</a></p>";
            $content .= '</div>';

            $content .= "<div id=failure style='display:none'><p>". __('Order cancelled', 'vipps') . '</p>';
            $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','vipps') . '</a></p>';
            $content .= "</div>";


            $this->fakepage(__('Confirm your purchase in your Vipps app','vipps'), $content);
        }
    }

    public function plugins_loaded() {
        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
        add_action( 'woocommerce_api_wc_gateway_vipps', array($this,'vipps_callback'));

        // Special pages and callbacks handled by template_redirect
        add_action('template_redirect', array($this,'template_redirect'));

        // Ajax endpoints for checking the order status while waiting for confirmation
        add_action('wp_ajax_nopriv_check_order_status', array($this, 'ajax_check_order_status'));
        add_action('wp_ajax_check_order_status', array($this, 'ajax_check_order_status'));

    }

    // This is the main callback from Vipps when payments are returned. IOK 2018-04-20
    public function vipps_callback() {
        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);
        if (!$result) {
            $this->log(__("Did not understand callback from Vipps:",'vipps') . " " .  $raw_post);
            return false;
        }
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php"); // Unneccessary, as this will have been initialized by Woo, but anyway . 2018-04-27
        $gw = new WC_Gateway_Vipps();
        return $gw->handle_callback($result);
    }

    /* WooCommerce Hooks */
    public function woocommerce_payment_gateways($methods) {
        $methods[] = 'WC_Gateway_Vipps'; 
        return $methods;
    }
    /* End Woocommerce hoos*/

    public function activate () {

    }
    public function uninstall() {
    }
    public function footer() {
    }

    // Check order status in the database, and if it is pending for a long time, directly at Vipps
    // IOK 2018-05-04
    public function check_order_status($order) {
        if (!$order) return null;
        $order_status = $order->get_status();
        if ($order_status != 'pending') return $order_status;
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            $now = time();
            $then= $order->get_meta('_vipps_init_timestamp');
            if ($then + (1 * 60) < $now) { // more than a minute? Start checking at Vipps
                return $order_status;
            }
        }
        $this->log("Checking order status on Vipps");
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        try {
            $order_status = $gw->callback_check_order_status($order);
            return $order_status;
        } catch (Exception $e) {
            $this->log($e->getMessage());
            return null;
        }
    }

    // We have to empty the cart when the user goes to Vipps, so
    // we store it in the session and restore it if the users cancels. IOK 2018-05-07
    public function save_cart($order) {
        global $woocommerce;
        $cartcontents = $woocommerce->cart->get_cart();
        $carts = $woocommerce->session->get('_vipps_carts');
        if (!$carts) $carts = array();
        $carts[$order->get_id()] = $cartcontents;
        $woocommerce->session->set('_vipps_carts',$carts); 
    }
    public function restore_cart($order) {
        global $woocommerce;
        $carts = $woocommerce->session->get('_vipps_carts');
        if (empty($carts)) return;
        $cart = @$carts[$order->get_id()];
        unset($carts[$order->get_id()]);
        $woocommerce->session->set('_vipps_carts');
        foreach ($cart as $cart_item_key => $values) {
            $id =$values['product_id'];
            $quant=$values['quantity'];
            $woocommerce->cart->add_to_cart($id,$quant);
        }
    }

    // Check the status of the order if it is a part of our session, and return a result to the handler function IOK 2018-05-04
    public function ajax_check_order_status () {
        check_ajax_referer('vippsstatus','sec');

        $orderid= wc_get_order_id_by_order_key($_POST['key']);
        $transaction = wc_get_order_id_by_order_key($_POST['transaction']);

        $sessionorders= WC()->session->get('_vipps_session_orders');
        if (!isset($sessionorders[$orderid])) {
            $this->log(__('The orderid passed is not from this session:','vipps') . $orderid);
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','vipps')));
        }

        $order = new WC_Order($orderid); 
        if (!$order) {
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','vipps')));
        }
        $order_status = $this->check_order_status($order);
        if ($order_status == 'on-hold') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment authorized', 'vipps')));
        }
        if ($order_status == 'processing') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment captured', 'vipps')));
        }
        if ($order_status == 'completed') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Order complete', 'vipps')));
        }

        if ($order_status == 'failed') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        if ($order_status == 'cancelled') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        if ($order_status == 'refunded') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            wp_send_json(array('status'=>'waiting', 'msg'=>__('Waiting on order', 'vipps')));
        }
        wp_send_json(array('status'=>'error', 'msg'=> __('Unknown order status','vipps') . $order_status));
        return false;
    }

    public function fakepage($title,$content) {
        global $wp, $wp_query;
        $post = new stdClass();
        $post->ID = -99;
        $post->post_author = 1;
        $post->post_date = current_time( 'mysql' );
        $post->post_date_gmt = current_time( 'mysql', 1 );
        $post->post_title = $title;
        $post->post_content = $content;
        $post->post_status = 'publish';
        $post->comment_status = 'closed';
        $post->ping_status = 'closed';
        $post->post_name = 'vippsconfirm-fake-page-name';
        $post->post_type = 'page';
        $post->filter = 'raw'; // important
        $wp_post = new WP_Post($post);
        wp_cache_add( -99, $wp_post, 'posts' );
        // Update the main query
        $wp_query->post = $wp_post;
        $wp_query->posts = array( $wp_post );
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $post_id;
        $wp_query->found_posts = 1;
        $wp_query->post_count = 1;
        $wp_query->max_num_pages = 1; 
        $wp_query->is_page = true;
        $wp_query->is_singular = true; 
        $wp_query->is_single = false; 
        $wp_query->is_attachment = false;
        $wp_query->is_archive = false; 
        $wp_query->is_category = false;
        $wp_query->is_tag = false; 
        $wp_query->is_tax = false;
        $wp_query->is_author = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_embed = false;
        $wp_query->is_404 = false; 
        $wp_query->is_paged = false;
        $wp_query->is_admin = false; 
        $wp_query->is_preview = false; 
        $wp_query->is_robots = false; 
        $wp_query->is_posts_page = false;
        $wp_query->is_post_type_archive = false;
        // Update globals
        $GLOBALS['wp_query'] = $wp_query;
        $wp->register_globals();
        return $wp_post;
    }

}

/* Instantiate the singleton, stash it in a global and add hooks. IOK 2018-02-07 */
global $Vipps;
$Vipps = new Vipps();
register_activation_hook(__FILE__,array($Vipps,'activate'));
register_uninstall_hook(__FILE__,array($Vipps,'uninstall'));

if (is_admin()) {
    add_action('admin_init',array($Vipps,'admin_init'));
    add_action('admin_menu',array($Vipps,'admin_menu'));
} else {
    add_action('wp_footer', array($Vipps,'footer'));
}
add_action('init',array($Vipps,'init'));
add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));

?>
