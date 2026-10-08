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

if ( ! function_exists( 'wp_doing_ajax' ) ) { function wp_doing_ajax() { return false; } }
if ( ! function_exists( 'wc_get_logger' ) ) { function wc_get_logger() { return new class() { public function error( $m, $c = array() ) {} public function info( $m, $c = array() ) {} public function debug( $m, $c = array() ) {} }; } }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = '' ) { return $text; } }
function wcpos_pro_payment_id_for_action( $provider, $ref ) { return null; }

class WC_Order {
	public $meta = array( '_sumup_reader_id' => 'rdr_a', '_sumup_checkout_status' => 'PENDING' );
	public $paid = false; public $completed = array(); public $notes = array();
	public function get_id() { return 42; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_transaction_id() { return 'ctx_1'; }
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
$order = new WC_Order();
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array( 'ctx_1' ) === $order->completed, 'a confirmed success completes the order with the client transaction id' );

// A successful delivery the lookup does not confirm: nothing is completed.
foreach ( array( array( 'client_transaction_id' => 'ctx_1', 'status' => 'PENDING' ), array( 'client_transaction_id' => 'ctx_other', 'status' => 'SUCCESSFUL' ), null ) as $lookup ) {
	$handler->lookup = $lookup;
	$order = new WC_Order();
	$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
	expect( array() === $order->completed, 'the unsigned delivery alone never completes an order' );
}

// A failed delivery: nothing is completed, whatever the lookup says.
$handler->lookup = array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' );
$order = new WC_Order();
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'FAILED' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $order->completed, 'a failed delivery completes nothing' );

// An order already paid is not completed again.
$handler->lookup = array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' );
$order = new WC_Order(); $order->paid = true;
$method->invoke( $handler, $order, array( 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'ctx_1', 'status' => 'SUCCESSFUL' ), 'timestamp' => '2026-10-08T22:00:00+00:00' ) );
expect( array() === $order->completed, 'a paid order is not completed twice' );

echo "PASS: the legacy webhook completes an unadopted attempt only on SumUp's word.\n";
