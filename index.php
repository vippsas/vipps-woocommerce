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

    function __construct() {
    }

    public function admin_init () {
    }

    public function admin_menu () {
    }

    public function init () {
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source','Vipps Woo Gateway');
        $logger->log($type,$what,$context);
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
            // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
            // and check that the order matches (and is 'pending') (and exists)
            $transid = $order->get_meta('_vipps_transaction');
            $vippsstamp = $order->get_meta('_vipps_init_timestamp');
            $vippsstatus = $order->get_meta('_vipps_init_status');
            $message = $order->get_meta('_vipps_confirm_message');

            $content = "<pre>This is where we await user confirmation:\n";
            $content .= htmlspecialchars("$message\n$vippsstatus\n" . date('Y-m-d H:i:s',$vippsstamp));

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
        add_action('wp_ajax_nopriv_check_order_status', array($this, 'check_order_status'));
        add_action('wp_ajax_check_order_status', array($this, 'check_order_status'));

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

    public function check_order_status () {
        check_ajax_referer('vippsstatus','sec');

        $orderid= wc_get_order_id_by_order_key($_POST['key']);
        $transaction = wc_get_order_id_by_order_key($_POST['transaction']);

        $order = new WC_Order($orderid); 
        // IOK FIXME check that the transactionid for vipps is correct and that it exist. Then check the status, 
        // either cancelled or processing and the stamps passed above

        // If still on-hold, check the INITIATE timestamp. If that's old, then call Vipps directly
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        // Call Vipps here to determine status on-line, handling errors
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
