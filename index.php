<?php
/*
Plugin Name: Woocommerce Vipps Payment Module
Description: Offer Vips as a payment method for Woocommerce
Author: Iver Odin Kvello
Version: 0.9
*/

/* This class is for hooks and plugin managent, and is instantiated as a singleton. IOK 2018-02-07*/
class Vipps {
  var $options;

  function __construct() {
   $this->options = get_option('vipps_options'); 
  }

  /* WooCommerce Hooks */
  public function woocommerce_payment_gateways($methods) {
    $methods[] = 'WC_Gateway_Vipps'; 
    return $methods;
  }


  public function admin_init () {
   register_setting('vipps_options','vipps_options', array($this,'validate'));
  }
 
  public function admin_menu () {
  }

  public function init () {
  }

  public function plugins_loaded() {
   require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");// IOK FIXME 2018-02-07 instantiate the Gateway object here
   add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
  }


  // Validate or format form input for options
  public function validate ($input) {
   return $input;
  }

  public function activate () {
   $default = array();
    add_option('vipps_options',$default,false);
  }
  public function uninstall() {
    delete_option('vipps_options');
  }
  public function toolpage () {
    if (!is_admin() || !current_user_can('manage_options')) {
      die("Insufficient privileges");
    }
    $options = get_option('vipps_options'); 
?>
<div class='wrap'>
 <h2>Vipps settings</h2>
<form action='options.php' method='post'>
<?php settings_fields('vipps_options'); ?>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>

</form>
</div>
<?php
  }
  public function footer() {
  }

}

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
// Always runs
add_action('init',array($Vipps,'init'));
add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));

?>
