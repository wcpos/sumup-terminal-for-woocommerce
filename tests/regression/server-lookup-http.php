<?php
// The production lookup classifies SumUp's HTTP answers: 404 = nothing yet (null), 2xx = transaction, else error.
require_once __DIR__ . '/stubs/sumup-server.php';
if ( ! function_exists( 'wp_remote_request' ) ) { function wp_remote_request( $url, $args ) { $GLOBALS['lookup_url'] = $url; return $GLOBALS['lookup_response']; } }
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) { function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; } }
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) { function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; } }
if ( ! class_exists( 'LoggerSpy' ) ) { class LoggerSpy { public static $lines = array(); } }
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
$service = new TransactionService( 'sup_sk_test' );
$profile = new ServerProfile( 'sup_sk_test' ); $profile->result = 'M123';
$service->set_profile_service( $profile );
$GLOBALS['lookup_response'] = array( 'response' => array( 'code' => 404 ), 'body' => '{"message":"Resource not found","error_code":"NOT_FOUND"}' );
expect( null === $service->find_by_client_transaction_id( 'client:123' ), '404 is null (nothing yet)' );
expect( false !== strpos( $GLOBALS['lookup_url'], '/v2.1/merchants/M123/transactions?client_transaction_id=client%3A123' ), 'merchant-scoped lookup URL' );
$GLOBALS['lookup_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"id":"txn-1","client_transaction_id":"client:123","status":"SUCCESSFUL"}' );
expect( 'txn-1' === $service->find_by_client_transaction_id( 'client:123' )['id'], '2xx returns the transaction' );
$GLOBALS['lookup_response'] = array( 'response' => array( 'code' => 500 ), 'body' => 'oops' );
$r = $service->find_by_client_transaction_id( 'client:123' );
expect( is_wp_error( $r ) && 500 === $r->get_error_data()['status'], '5xx is an error with the status' );
$GLOBALS['lookup_response'] = new WP_Error( 'http_request_failed', 'timed out' );
expect( is_wp_error( $service->find_by_client_transaction_id( 'client:123' ) ), 'transport error passes through' );
expect( is_wp_error( $service->find_by_client_transaction_id( '' ) ), 'empty id refused' );
echo "server-lookup-http ok\n";
