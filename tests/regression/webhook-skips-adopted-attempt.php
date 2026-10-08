<?php
// phpcs:ignoreFile
// The legacy webhook leaves an attempt Pro adopted on upgrade to Pro; any other delivery is
// processed as before.

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
function wcpos_pro_payment_id_for_action( $provider, $ref ) { return $GLOBALS['adopted_map'][ $ref ] ?? null; }
function wc_get_order( $id ) { return $GLOBALS['current']; }

class WC_Order {
	public $touched = false;
	public function get_id() { return 42; }
	public $adopted_ref = '';
	public function get_meta( $key ) { return '_sumup_reader_id' === $key ? 'rdr_a' : ( '_sutwc_adopted_ref' === $key ? $this->adopted_ref : '' ); }
	public function get_transaction_id() { return 'ctx_1'; }
	public function update_meta_data( $key, $value ) { $this->touched = true; }
	public function is_paid() { return false; }
	public function needs_payment() { return true; }
	public function save() {}
}

$handler = ( new ReflectionClass( WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::class ) )->newInstanceWithoutConstructor();
$method  = new ReflectionMethod( WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::class, 'process_webhook' );
if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
$event = array( 'event_type' => 'unknown.event', 'payload' => array(), 'timestamp' => gmdate( 'c' ) );

$GLOBALS['adopted_map'] = array( 'rdr_a:ctx_1' => 'row-1' );
$order = new WC_Order(); $GLOBALS['current'] = $order;
$method->invoke( $handler, $order, $event );
expect( false === $order->touched, 'an adopted attempt is left to Pro: the webhook writes nothing' );

$GLOBALS['adopted_map'] = array();
$order = new WC_Order(); $GLOBALS['current'] = $order;
$method->invoke( $handler, $order, $event );
expect( true === $order->touched, 'an unadopted attempt is processed as before' );

// After Pro captures the row Free rewrites the transaction id; the reference kept at adoption still identifies it.
$GLOBALS['adopted_map'] = array( 'rdr_a:ctx_original' => 'row-1' );
$order = new WC_Order(); $order->adopted_ref = 'rdr_a:ctx_original'; $GLOBALS['current'] = $order;
$method->invoke( $handler, $order, $event );
expect( false === $order->touched, 'an adopted attempt is recognised by the reference kept on the order' );

echo "PASS: the legacy webhook skips adopted attempts.\n";
