<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Device_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\RefundClient;
expect( class_exists( SumUp_Device_Provider::class ), 'device adapter is missing' );
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_remote_request( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return server_result( $GLOBALS['response'] ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
$transactions = new ServerTransactions();
$provider = new SumUp_Device_Provider( $transactions, new RefundClient( 'test' ), new ServerProfile() );
$row = server_row();
$row['provider_refs'] = array( 'transaction_id' => 'transaction-uuid', 'transaction_code' => 'NOT-THE-UUID', 'foreign_transaction_id' => $row['id'] );
foreach ( array( 200, 204 ) as $code ) {
	$response = array( 'response' => array( 'code' => $code ), 'body' => '' );
	foreach ( array( array( '12.30', '{"amount":12.3}' ), array( '2.30', '{"amount":2.3}' ) ) as $case ) {
		$requests = array();
		expect( array( 'status' => 'succeeded', 'provider_ref' => 'transaction-uuid' ) === $provider->refund( $row, 45, $case[0] ), 'refund confirms UUID' );
		expect( 1 === count( $requests ) && 'https://api.sumup.com/v0.1/me/refund/transaction-uuid' === $requests[0][0] && 'POST' === $requests[0][1]['method'], 'exactly one refund goes to UUID, never transaction code' );
		expect( $case[1] === $requests[0][1]['body'], 'refund sends only the requested amount, including full ledger amount' );
	}
}
expect( array() === $transactions->ids, 'device refunds do not use cloud lookup' );
foreach ( array( new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'Unavailable' ), array( 'response' => array( 'code' => 400 ), 'body' => '{"message":"Rejected"}' ) ) as $response ) {
	expect( is_wp_error( $provider->refund( $row, 45, '2.30' ) ), 'refund failures never claim success' );
}
unset( $row['provider_refs']['transaction_id'] );
$requests = array();
expect( is_wp_error( $provider->refund( $row, 45, '12.30' ) ) && array() === $requests, 'missing UUID never falls back to code' );
echo "server-device-refund ok\n";
