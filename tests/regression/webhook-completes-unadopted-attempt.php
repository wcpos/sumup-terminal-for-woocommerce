<?php
// phpcs:ignoreFile
// An attempt Pro did not adopt is completed by the legacy webhook once SumUp's own
// transaction lookup confirms it: the unsigned delivery alone never completes an order.

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/../../includes/Settings.php';
require_once __DIR__ . '/../../includes/Logger.php';
require_once __DIR__ . '/../../includes/Legacy_Adoption.php';
require_once __DIR__ . '/../../includes/AjaxHandler.php';
require_once __DIR__ . '/stubs/order-lock.php';

if ( ! function_exists( 'wp_doing_ajax' ) ) { function wp_doing_ajax() { return false; } }
if ( ! function_exists( 'wc_get_logger' ) ) { function wc_get_logger() { return new class() { public function error( $m, $c = array() ) {} public function info( $m, $c = array() ) {} public function debug( $m, $c = array() ) {} }; } }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = '' ) { return $text; } }
function wcpos_pro_payment_id_for_action( $provider, $ref ) { return $GLOBALS['adopted_map'][ $ref ] ?? null; }
function wc_get_order( $id ) { return $GLOBALS['fresh']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
$GLOBALS['options'] = array();
$GLOBALS['adopted_map'] = array();

class WC_Order {
	public $meta = array( '_sumup_reader_id' => 'rdr_a', '_sumup_checkout_status' => 'PENDING' );
	public $txn = 'ctx_1';
	public $paid = false; public $completed = array(); public $notes = array();
	public function get_id() { return 42; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_transaction_id() { return $this->txn; }
	public function is_paid() { return $this->paid; }
	public function needs_payment() { return ! $this->paid; }
	public function payment_complete( $txn = '' ) { $this->completed[] = $txn; $this->paid = true; return true; }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function save() {}
}

$method = new ReflectionMethod( WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::class, 'process_webhook' );
if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }

$handler = new class() extends WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler {
	public $lookup;
	public function __construct() {}
	protected function lookup_transaction( string $client_id ) { return $this->lookup; }
};

// A successful delivery, confirmed by the lookup: the order is completed with the client id.
$handler->lookup = array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' );
$order = new WC_Order(); $GLOBALS['fresh'] = $order;
\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$locked = array();
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array( 'ctx_1' ) === $order->completed, 'a confirmed success completes the order with the client transaction id' );
expect( array( 42 ) === \WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$locked, 'the completion runs under the order lock' );

// The fresh copy read under the lock decides: paid meanwhile, or adopted meanwhile, nothing is completed.
$order = new WC_Order(); $paid = new WC_Order(); $paid->paid = true; $GLOBALS['fresh'] = $paid;
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $order->completed && array() === $paid->completed, 'an order paid between the check and the lock is not completed again' );
$order = new WC_Order(); $adopted = new WC_Order(); $adopted->meta['_sutwc_adopted_ref'] = 'rdr_a:ctx_1'; $GLOBALS['fresh'] = $adopted; $GLOBALS['adopted_map'] = array( 'rdr_a:ctx_1' => 'row-1' );
// The stale copy passes the early adopted check (no adopted ref on it); the fresh copy is adopted.
$order->meta['_sumup_reader_id'] = ''; $order->txn = '';
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $adopted->completed, 'an order adopted between the check and the lock is left to Pro' );
$GLOBALS['adopted_map'] = array();

// A successful delivery the lookup does not confirm: nothing is completed.
foreach ( array( array( 'client_transaction_id' => 'ctx_1', 'status' => 'PENDING' ), array( 'client_transaction_id' => 'ctx_other', 'status' => 'SUCCESSFUL' ), null ) as $lookup ) {
	$handler->lookup = $lookup;
	$order = new WC_Order(); $GLOBALS['fresh'] = $order;
	$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
	expect( array() === $order->completed, 'the unsigned delivery alone never completes an order' );
	expect( ! isset( $GLOBALS['options']['sutwc_completion_queue'] ), 'a definite answer from SumUp is final: nothing is queued' );
}

// SumUp could not be asked (transport error, no merchant code): nothing is completed, and the
// order is handed to the pass to be asked about again.
foreach ( array( false, new WP_Error( 'sumup_api_error', 'down' ) ) as $lookup ) {
	$GLOBALS['options'] = array();
	$handler->lookup = $lookup;
	$order = new WC_Order(); $GLOBALS['fresh'] = $order;
	$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
	expect( array() === $order->completed && 0 === $GLOBALS['options']['sutwc_completion_queue'][42]['tries'] && $GLOBALS['options']['sutwc_completion_queue'][42]['next_at'] >= time() + 60, 'no answer completes nothing and queues the order for the sweep, after the first backoff' );
}
$GLOBALS['options'] = array();

// A failed delivery: nothing is completed, whatever the lookup says.
$handler->lookup = array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' );
$order = new WC_Order(); $GLOBALS['fresh'] = $order;
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'FAILED' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $order->completed, 'a failed delivery completes nothing' );

// An order already paid is not completed again.
$handler->lookup = array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' );
$order = new WC_Order(); $order->paid = true; $GLOBALS['fresh'] = $order;
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $order->completed, 'a paid order is not completed twice' );

echo "PASS: the legacy webhook completes an unadopted attempt only on SumUp's word.\n";
