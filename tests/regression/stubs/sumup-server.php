<?php
// phpcs:ignoreFile
require_once __DIR__ . '/wcpos-pro-server.php';
function expect( $condition, $message = 'expectation failed' ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}
spl_autoload_register( function ( $class ) {
	$prefix = 'WCPOS\\WooCommercePOS\\SumUpTerminal\\';
	if ( 0 === strpos( $class, $prefix ) ) {
		$file = __DIR__ . '/../../../includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( file_exists( $file ) ) { require_once $file; }
	}
} );
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\WordPressHttpReaderApiClient;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
class ServerProfile extends ProfileService {
	public $result = 'M123';
	public function get_merchant_code( $force_refresh = false ) { return server_result( $this->result ); }
}
class ServerTransactions extends TransactionService {
	public $result = array();
	public $ids = array();
	public function get_by_client_transaction_id( $id ) { $this->ids[] = $id; return server_result( $this->result ); }
	public function find_by_client_transaction_id( string $id ) { $this->ids[] = $id; $r = server_result( $this->result ); return false === $r ? new WP_Error( 'sumup_api_error', 'SumUp API request failed.' ) : $r; }
}
class ServerReaders extends WordPressHttpReaderApiClient {
	public $result = array();
	public $checkout_result = array( 'data' => array( 'client_transaction_id' => 'client:123' ) );
	public $cancel_result = true;
	public $checkouts = array();
	public $cancels = array();
	public function get_all() { return server_result( $this->result ); }
	public function checkout( $id, $payload ) { $this->checkouts[] = array( $id, $payload ); return server_result( $this->checkout_result ); }
	public function cancel_checkout( $id ) { $this->cancels[] = $id; return server_result( $this->cancel_result ); }
}
function server_result( $value ) { if ( $value instanceof Throwable ) { throw $value; } return $value; }
function server_fixture() {
	expect( class_exists( SumUp_Server_Provider::class ), 'server adapter is missing' );
	$profile = new ServerProfile();
	$readers = new ServerReaders();
	$transactions = new ServerTransactions();
	return array( new SumUp_Server_Provider( $profile, new ReaderService( 'test', $readers ), $transactions ), $profile, $readers, $transactions );
}
function server_transaction( $status = 'SUCCESSFUL' ) {
	return array( 'id' => 'txn-123', 'transaction_code' => 'CODE', 'client_transaction_id' => 'client:123', 'status' => $status, 'amount' => 12.3, 'currency' => 'eur', 'card' => array( 'last_4_digits' => '0123', 'type' => 'VISA' ), 'entry_mode' => 'contactless', 'payment_type' => 'POS' );
}
function server_row() { return array( 'id' => 'ABCDEF12-1234-1234-1234-ABCDEF123456', 'order_id' => 12, 'amount' => '12.30', 'currency' => 'eur', 'provider_refs' => array( 'action' => 'reader:client:123', 'reader' => 'reader' ) ); }
function provider_error_expect( $value, $code = 'sumup_api_error' ) {
	expect( is_wp_error( $value ), 'expected WP_Error' );
	expect( 'wcpos_provider_error' === $value->get_error_code(), 'provider envelope' );
	expect( 502 === $value->get_error_data()['status'], 'transport status' );
	expect( $code === $value->get_error_data()['detail']['code'], 'provider detail code' );
	expect( $value->get_error_message() === $value->get_error_data()['detail']['message'], 'provider detail message' );
}
