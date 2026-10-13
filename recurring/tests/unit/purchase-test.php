<?php
/**
 * Focused purchase-service regression checks with WordPress/WooCommerce doubles.
 * Run: php recurring/tests/unit/purchase-test.php
 * No database, browser session, or Vipps credentials are used.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'ABSPATH', __DIR__ );
define( 'WC_VERSION', '11.0.0' );

set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

$orders = $subscriptions = $users = $events = [];
$creation_error = null;
$customer_creations = 0;

function expect( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function is_admin() { return false; }
function add_action( ...$args ) {}
function apply_filters( $name, $value, ...$args ) { return $value; }
function get_option( $name, $default = false ) {
	return $name === '_woo_vipps_recurring_anonymous_system_customer_id' ? 99 : $default;
}
function plugins_url( ...$args ) { return ''; }
function plugin_dir_path( $path ) { return dirname( $path ) . '/'; }
function untrailingslashit( $path ) { return rtrim( $path, '/' ); }
function sanitize_email( $email ) { return trim( $email ); }
function wp_generate_password() { return 'test-password'; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( $field === 'ID' ? $user->ID : $user->email ) === $value ) { return $user; }
	}
	return false;
}
class WP_Error {
	public function get_error_message() { return 'Customer creation failed'; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wc_create_new_customer( $email, ...$args ) {
	$GLOBALS['customer_creations']++;
	if ( $GLOBALS['creation_error'] ) { return ( $GLOBALS['creation_error'] )( $email ); }
	$id = 100 + $GLOBALS['customer_creations'];
	$GLOBALS['users'][ $id ] = (object) [ 'ID' => $id, 'email' => $email ];
	return $id;
}
function do_action( $name, ...$args ) { $GLOBALS['events'][] = $name; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ]; }
function wcs_get_subscriptions_for_order( $order ) { return $GLOBALS['subscriptions'][ $order->get_id() ] ?? []; }
function wcs_order_contains_subscription( $order, $type ) { return ! empty( wcs_get_subscriptions_for_order( $order ) ); }
function wcs_copy_order_address( $order, $subscription ) { $subscription->fields = $order->fields; }

class WC_Order {
	public int $id;
	public int $customer_id = 0;
	public array $fields = [];
	public array $meta = [];
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function get_customer_id( $context = '' ) { return $this->customer_id; }
	public function set_customer_id( $id ) { $this->customer_id = (int) $id; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save() {}
	public function __call( $method, $args ) {
		if ( strpos( $method, 'get_' ) === 0 ) { return $this->fields[ substr( $method, 4 ) ] ?? ''; }
		if ( strpos( $method, 'set_' ) === 0 ) { $this->fields[ substr( $method, 4 ) ] = $args[0]; return; }
		throw new RuntimeException( 'Unexpected order method ' . $method );
	}
}
class WC_Subscription extends WC_Order {}
class WC_Customer extends WC_Order {}
class WC_Gateway_Vipps_Recurring {
	public object $api;
	public int $preparations = 0;
	public int $checks = 0;
	public bool $fail_preparation = false;
	public function __construct() {
		$this->api = new class {
			public array $charges = [];
			public int $reads = 0;
			public function get_charges_for( $id ) { $this->reads++; return $this->charges; }
		};
	}
	public function create_or_get_anonymous_system_customer() { return new WC_Customer( 99 ); }
	public function create_partial_subscriptions_from_order( $order ) {
		$this->preparations++;
		expect( $order->get_customer_id() > 0, 'Subscription creation requires a customer' );
		if ( $this->fail_preparation ) { throw new RuntimeException( 'Preparation failed' ); }
		$subscription = new WC_Subscription( 1000 + $order->get_id() );
		$subscription->customer_id = $order->get_customer_id();
		$GLOBALS['subscriptions'][ $order->get_id() ] = [ $subscription ];
		return [ $subscription ];
	}
	public function check_charge_status( $order_id, $skip_lock ) {
		$order = wc_get_order( $order_id );
		expect( $order->customer_id > 0 && $order->customer_id !== 99, 'Status check ran before real customer assignment' );
		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
			expect( $subscription->customer_id === $order->customer_id, 'Status check ran before subscription assignment' );
			expect( $subscription->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID ) !== '', 'Missing subscription agreement before status check' );
		}
		$this->checks++;
	}
}

// Load the real core bootstrap with Checkout disabled, not merely the new file.
require_once dirname( __DIR__, 2 ) . '/recurring.php';
expect( class_exists( 'WC_Vipps_Recurring_Purchase', false ), 'Purchase service not loaded by core bootstrap' );
expect( ! class_exists( 'WC_Vipps_Recurring_Checkout', false ), 'Core bootstrap loaded disabled Checkout' );

$gateway = new WC_Gateway_Vipps_Recurring();
$purchase = new WC_Vipps_Recurring_Purchase( $gateway );
$order = $orders[1] = new WC_Order( 1 );
$pending = $purchase->prepare_subscriptions( $order );
expect( $order->customer_id === 0 && $pending[0]->customer_id === 99, 'Guest preparation did not restore the order' );
$purchase->prepare_subscriptions( $order );
expect( $gateway->preparations === 1, 'Resuming preparation created another subscription' );

$gateway->fail_preparation = true;
$failed = $orders[2] = new WC_Order( 2 );
try { $purchase->prepare_subscriptions( $failed ); throw new LogicException( 'Expected preparation failure' ); }
catch ( RuntimeException $error ) { expect( $error->getMessage() === 'Preparation failed', 'Unexpected preparation failure' ); }
expect( $failed->customer_id === 0, 'Preparation failure left the anonymous customer on the order' );
$gateway->fail_preparation = false;

$users[7] = (object) [ 'ID' => 7, 'email' => 'existing@example.test' ];
$billing = [ 'email' => 'existing@example.test', 'first_name' => 'Test', 'last_name' => 'Customer', 'phone' => '+4712345678', 'address_1' => 'Billing road', 'city' => 'Oslo', 'postcode' => '0001', 'country' => 'NO' ];
$shipping = [ 'first_name' => 'Recipient', 'address_1' => 'Shipping road', 'city' => 'Bergen', 'postcode' => '5000', 'country' => 'NO' ];
$purchase->record_agreement( $order, 'agr-test' );
expect( $order->get_meta( WC_Vipps_Recurring_Helper::META_CHARGE_ID ) === '', 'No-charge agreement produced invalid charge metadata' );
expect( $purchase->complete( $order, 'agr-test', $billing, $shipping ), 'Existing-customer completion failed' );
expect( $order->customer_id === 7 && $customer_creations === 0, 'Existing email did not reuse the customer' );
expect( $order->fields['shipping_address_1'] === 'Shipping road', 'Separate shipping address lost' );
$purchase->complete( $order, 'agr-test', $billing, $shipping );
expect( $customer_creations === 0 && count( $subscriptions[1] ) === 1, 'Repeated completion created duplicate records' );

$guest = $orders[3] = new WC_Order( 3 );
$purchase->prepare_subscriptions( $guest );
$billing['email'] = 'new@example.test';
expect( $purchase->complete( $guest, 'agr-new', $billing ), 'New-customer completion failed' );
$purchase->complete( $guest, 'agr-new', $billing );
expect( $customer_creations === 1 && count( array_filter( $events, static fn( $event ) => $event === 'woo_vipps_express_checkout_new_customer' ) ) === 1, 'Repeated callback created a second customer/event' );
expect( $guest->fields['shipping_address_1'] === $billing['address_1'], 'Billing fallback for shipping changed' );

$missing = $orders[4] = new WC_Order( 4 );
$checks = $gateway->checks;
expect( ! $purchase->complete( $missing, 'agr-missing', [] ), 'Missing email completed a guest purchase' );
expect( $gateway->checks === $checks, 'Incomplete purchase reached payment completion' );

$collision = $orders[5] = new WC_Order( 5 );
$creation_error = static function ( $email ) use ( &$users ) {
	$users[8] = (object) [ 'ID' => 8, 'email' => $email ];
	return new WP_Error();
};
$billing['email'] = 'concurrent@example.test';
expect( $purchase->complete( $collision, 'agr-collision', $billing ), 'Concurrent customer creation was not recovered' );
expect( $collision->customer_id === 8, 'Concurrent account was not assigned' );
$creation_error = static fn() => new WP_Error();
$billing['email'] = 'failed@example.test';
$checks = $gateway->checks;
expect( ! $purchase->complete( $orders[6] = new WC_Order( 6 ), 'agr-failed', $billing ), 'Customer error completed purchase' );
expect( $gateway->checks === $checks, 'Customer error reached payment completion' );
$creation_error = null;

$assigned = $orders[7] = new WC_Order( 7 );
$assigned->customer_id = 7;
$billing['email'] = 'different@example.test';
$purchase->complete( $assigned, 'agr-assigned', $billing );
expect( $assigned->customer_id === 7, 'Profile email replaced the starting customer' );
$purchase->complete( $assigned, 'agr-assigned', [], $shipping );
expect( $assigned->fields['billing_address_1'] === 'Shipping road', 'Assigned customer lost contact fallback without billing details' );
$subscriptions[7][] = new WC_Subscription( 2007 );
$purchase->assign_customer( $assigned, 8 );
foreach ( $subscriptions[7] as $subscription ) { expect( $subscription->customer_id === 8, 'Assignment missed a related subscription' ); }

$gateway->api->charges = [ (object) [ 'id' => 'charge-test' ] ];
$purchase->record_agreement( $guest, 'agr-new' );
$reads = $gateway->api->reads;
$purchase->record_agreement( $guest, 'agr-new' );
expect( $gateway->api->reads === $reads, 'Stored charge was fetched again' );
expect( ! class_exists( 'WC_Vipps_Recurring_Checkout', false ), 'Purchase operations depend on Checkout' );

// Exercise the real Checkout adapter after independent service checks.
require_once dirname( __DIR__, 2 ) . '/includes/wc-vipps-recurring-checkout.php';
$checkout = new WC_Vipps_Recurring_Checkout();
$property = new ReflectionProperty( $checkout, 'gateway' );
$property->setValue( $checkout, $gateway );
$session = [ 'subscriptionDetails' => [ 'agreementId' => 'agr-adapter' ], 'sessionState' => 'PaymentInitiated', 'billingDetails' => [ 'email' => 'existing@example.test', 'firstName' => 'Existing', 'lastName' => 'Customer', 'phoneNumber' => '4712345678', 'streetAddress' => 'Checkout road', 'city' => 'Oslo', 'postalCode' => '0002', 'country' => 'NO' ] ];
$adapter_order = $orders[9] = new WC_Order( 9 );
$checks = $gateway->checks;
$checkout->handle_payment( $adapter_order, $session );
expect( $gateway->checks === $checks && $adapter_order->customer_id === 0, 'Pending Checkout session completed early' );
$session['sessionState'] = 'PaymentSuccessful';
$checkout->handle_payment( $adapter_order, $session );
expect( $adapter_order->customer_id === 7 && $adapter_order->fields['billing_phone'] === '+4712345678', 'Checkout adapter did not normalize/delegate completion' );
$checkout->maybe_update_billing_and_shipping( $adapter_order, [ 'shippingDetails' => $session['billingDetails'] ] );
expect( $adapter_order->fields['billing_address_1'] === 'Checkout road', 'Public address adapter lost shipping fallback' );

echo "Purchase service and Checkout adapter checks passed.\n";
