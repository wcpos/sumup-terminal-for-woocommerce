<?php
// phpcs:ignoreFile
// On upgrade, attempts the old panel left mid-flight (PENDING, with the reader and SumUp's
// client transaction id) are folded into Pro's ledger once, bounded to orders that existed
// when the pass began, under Free's order lock with a fresh read; everything else is skipped.

namespace WCPOS\WooCommercePOS\SumUpTerminal {
	class AjaxHandler {
		public static $completed = array();
		public function __construct() {}
		public function complete_recorded_attempt( $order ) { self::$completed[] = $order->get_id(); return true; }
	}
}

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server {
	class SumUp_Server_Provider {
		public static $marked = array();
		public static function mark_adopted_checkout( string $client_id, int $started ): void { self::$marked[] = array( $client_id, $started ); }
	}
}

namespace WCPOS\WooCommercePOS\Payments\Contract {
class Order_Lock {
	public static $locked = array();
	public static $refuse = array();
	public static function instance() { return new self(); }
	public function with_lock( $order_id, $callback ) {
		if ( in_array( $order_id, self::$refuse, true ) ) { return new \WP_Error( 'wcpos_payment_locked', 'busy' ); }
		self::$locked[] = $order_id;
		return $callback();
	}
}
}

namespace {
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

use WCPOS\WooCommercePOS\SumUpTerminal\Legacy_Adoption;

if ( ! function_exists( 'wc_get_logger' ) ) { function wc_get_logger() { return new class() { public function error( $m, $c = array() ) {} public function info( $m, $c = array() ) {} public function debug( $m, $c = array() ) {} }; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; } }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function wc_get_orders( $args ) { $GLOBALS['queries'][] = $args; return 'PENDING' === ( $args['meta_value'] ?? '' ) ? $GLOBALS['page'] : ( $GLOBALS['recorded'][ $args['meta_value'] ] ?? array() ); }
function wc_get_order( $id ) { return $GLOBALS['fresh'][ $id ] ?? ( $GLOBALS['by_id'][ $id ] ?? false ); }
function wcpos_pro_payment_id_for_action( $provider, $ref ) { return $GLOBALS['adopted_map'][ $ref ] ?? null; }
function wcpos_pro_adopt_legacy_attempt( $order, $gateway_id, $ref, $amount, $currency ) { $GLOBALS['adopted'][] = array( $order->get_id(), $gateway_id, $ref, $amount, $currency ); return array( 'id' => 'row' ); }

class WC_Order {
	public $id; public $reader; public $txn; public $status; public $paid; public $modified; public $started = 1700000000;
	public function __construct( $id, $reader, $txn, $status = 'PENDING', $paid = false, $modified = null ) { $this->id = $id; $this->reader = $reader; $this->txn = $txn; $this->status = $status; $this->paid = $paid; $this->modified = $modified; }
	public function get_id() { return $this->id; }
	public $meta = array();
	public function get_meta( $key ) { return '_sumup_reader_id' === $key ? $this->reader : ( '_sumup_checkout_status' === $key ? $this->status : ( '_sumup_attempt_started' === $key ? $this->started : ( $this->meta[ $key ] ?? '' ) ) ); }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save() {}
	public function get_transaction_id() { return $this->txn; }
	public function is_paid() { return $this->paid; }
	public function needs_payment() { return ! $this->paid; }
	public function get_total() { return '12.50'; }
	public function get_currency() { return 'EUR'; }
	public function get_date_modified() { return $this->modified; }
}

function reset_state( array $page, int $newest, array $fresh = array() ) {
	$GLOBALS['options'] = array();
	$GLOBALS['page']    = $page;
	$GLOBALS['newest']  = $newest;
	$GLOBALS['by_id']   = array();
	foreach ( $page as $o ) { $GLOBALS['by_id'][ $o->get_id() ] = $o; }
	$GLOBALS['fresh']       = $fresh;
	$GLOBALS['adopted']     = array();
	$GLOBALS['adopted_map'] = array();
	$GLOBALS['queries']     = array();
	$GLOBALS['recorded']    = array();
	\WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::$completed = array();
	\WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider::$marked = array();
	\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$locked = array();
	\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$refuse = array();
}

// 1. The candidates are snapshotted once: PENDING, with reader and client transaction id, on an
//    order still waiting for payment. Only those are adopted, by reader:client id; an already-adopted
//    one is skipped inside the lock.
reset_state( array(
	new WC_Order( 1, 'rdr_a', 'ctx_live' ),
	new WC_Order( 2, 'rdr_a', 'ctx_paid', 'PAID', true ),
	new WC_Order( 3, 'rdr_a', '' ),                 // CREATING: no transaction id yet
	new WC_Order( 4, '', 'ctx_noreader' ),
	new WC_Order( 5, 'rdr_a', 'ctx_done', 'PENDING', true ), // paid meanwhile by cash
	new WC_Order( 6, 'rdr_b', 'ctx_adopted' ),
), 6 );
$GLOBALS['adopted_map'] = array( 'rdr_b:ctx_adopted' => 'row-6' );
Legacy_Adoption::upgrade();
expect( array( array( 1, \WCPOS\WooCommercePOS\SumUpTerminal\Settings::GATEWAY_ID, 'rdr_a:ctx_live', '12.50', 'EUR' ) ) === $GLOBALS['adopted'], 'only the attempt in flight is adopted, by reader:client id' );
expect( array( 1, 6 ) === \WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$locked, 'the lock is taken before the adopted check is repeated' );
expect( array( array( 'ctx_live', 1700000000 ) ) === \WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider::$marked, 'the provider learns when the adopted checkout began' );
expect( 'rdr_a:ctx_live' === $GLOBALS['by_id'][1]->meta['_sutwc_adopted_ref'], 'the adopted reference is kept on the order' );
$q = $GLOBALS['queries'][0];
expect( -1 === $q['limit'] && '_sumup_checkout_status' === $q['meta_key'] && 'PENDING' === $q['meta_value'] && 'ID' === $q['orderby'], 'the snapshot query selects every PENDING attempt in id order' );
expect( 3 === count( $GLOBALS['queries'] ), 'the snapshot is taken once: the PENDING attempts and the two recorded-success queries' );

expect( Legacy_Adoption::VERSION === $GLOBALS['options']['sutwc_adoption_version'], 'a short queue finishes the pass' );
expect( ! isset( $GLOBALS['options']['sutwc_adoption_queue'] ), 'the queue is cleared' );

// 1b. An attempt without a start time predates the provider's marker and is not adopted.
reset_state( array( new WC_Order( 20, 'rdr_a', 'ctx_ancient' ) ), 20 );
$GLOBALS['page'][0]->started = 0;
Legacy_Adoption::upgrade();
expect( array() === $GLOBALS['adopted'], 'an attempt with no start time is not adopted' );

// 1c. A success the old panel recorded but never completed is completed on SumUp's word, under
//     the lock, from the pass itself; an adopted order is not touched; a held lock keeps it queued.
reset_state( array(), 0 );
$paid_pending = new WC_Order( 30, '', 'ctx_recorded', 'PAID' );
$succ         = new WC_Order( 31, '', 'ctx_succ', '' );
$already      = new WC_Order( 32, 'rdr_a', 'ctx_adopted2', 'PAID' );
$GLOBALS['recorded'] = array( 'PAID' => array( $paid_pending, $already ), 'SUCCESSFUL' => array( $succ ) );
$GLOBALS['by_id']    = array( 30 => $paid_pending, 31 => $succ, 32 => $already );
$GLOBALS['adopted_map'] = array( 'rdr_a:ctx_adopted2' => 'row-32' );
Legacy_Adoption::upgrade();
$completed = \WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::$completed; sort( $completed );
$locked    = \WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$locked; sort( $locked );
expect( array( 30, 31 ) === $completed, 'recorded successes are completed from the pass, the adopted one is not' );
expect( array( 30, 31, 32 ) === $locked, 'each completion runs under the order lock' );
expect( Legacy_Adoption::VERSION === $GLOBALS['options']['sutwc_adoption_version'] && ! isset( $GLOBALS['options']['sutwc_completion_queue'] ), 'the pass finishes once both queues are empty' );
reset_state( array(), 0 );
$GLOBALS['recorded'] = array( 'PAID' => array( new WC_Order( 33, '', 'ctx_busy2', 'PAID' ) ) );
$GLOBALS['by_id']    = array( 33 => $GLOBALS['recorded']['PAID'][0] );
\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$refuse = array( 33 );
Legacy_Adoption::upgrade();
expect( array() === \WCPOS\WooCommercePOS\SumUpTerminal\AjaxHandler::$completed && array( 33 ) === $GLOBALS['options']['sutwc_completion_queue'] && ! isset( $GLOBALS['options']['sutwc_adoption_version'] ), 'a held lock keeps the recorded success queued and the pass open' );

// 2. The queue is a snapshot: an attempt the old panel starts after the pass began is never a
//    candidate, and a candidate whose reference changed (a retry) is skipped inside the lock.
reset_state( array( new WC_Order( 7, 'rdr_a', 'ctx_old' ) ), 7, array( 7 => new WC_Order( 7, 'rdr_a', 'ctx_retried' ) ) );
Legacy_Adoption::upgrade();
expect( array() === $GLOBALS['adopted'], 'a retried attempt (new client id) is not adopted under the old reference' );
reset_state( array( new WC_Order( 8, 'rdr_a', 'ctx_snap' ) ), 8 );
$GLOBALS['options']['sutwc_adoption_queue'] = array();
Legacy_Adoption::upgrade();
expect( array() === $GLOBALS['adopted'] && Legacy_Adoption::VERSION === $GLOBALS['options']['sutwc_adoption_version'], 'a pass whose snapshot is empty finishes without reading live orders' );

// 3. The order is re-read under the lock; a till payment that landed meanwhile stops the adoption.
reset_state( array( new WC_Order( 11, 'rdr_a', 'ctx_meanwhile' ) ), 11, array( 11 => new WC_Order( 11, 'rdr_a', 'ctx_meanwhile', 'PENDING', true ) ) );
Legacy_Adoption::upgrade();
expect( array() === $GLOBALS['adopted'], 'the fresh copy decides' );

// 4. A full page leaves the rest of the queue for the next request; a held lock keeps its order queued.
$page = array(); for ( $i = 1; $i <= Legacy_Adoption::PAGE_SIZE + 2; $i++ ) { $page[] = new WC_Order( $i, 'rdr_a', 'ctx_' . $i ); }
reset_state( $page, 100 );
Legacy_Adoption::upgrade();
expect( Legacy_Adoption::PAGE_SIZE === count( $GLOBALS['adopted'] ) && 2 === count( $GLOBALS['options']['sutwc_adoption_queue'] ) && ! isset( $GLOBALS['options']['sutwc_adoption_version'] ), 'a full page leaves the remainder queued' );
Legacy_Adoption::upgrade();
expect( Legacy_Adoption::PAGE_SIZE + 2 === count( $GLOBALS['adopted'] ) && Legacy_Adoption::VERSION === $GLOBALS['options']['sutwc_adoption_version'], 'the next request drains the queue' );
reset_state( array( new WC_Order( 12, 'rdr_a', 'ctx_busy' ), new WC_Order( 13, 'rdr_a', 'ctx_free' ) ), 13 );
\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::$refuse = array( 12 );
Legacy_Adoption::upgrade();
expect( array( 'rdr_a:ctx_free' ) === array_column( $GLOBALS['adopted'], 2 ) && array( 12 => 'rdr_a:ctx_busy' ) === $GLOBALS['options']['sutwc_adoption_queue'] && ! isset( $GLOBALS['options']['sutwc_adoption_version'] ), 'a held lock keeps its order queued and the pass open' );

// 5. Once the version is recorded, nothing runs.
reset_state( array( new WC_Order( 14, 'rdr_a', 'ctx_late' ) ), 14 );
$GLOBALS['options']['sutwc_adoption_version'] = Legacy_Adoption::VERSION;
Legacy_Adoption::upgrade();
expect( array() === $GLOBALS['adopted'], 'a finished pass does not run again' );

echo "PASS: legacy adoption takes only attempts in flight, once, under the lock.\n";
}
