<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_remote_request( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return server_result( $GLOBALS['response'] ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
list( $provider, $profile, $readers, $transactions ) = server_fixture();
$transactions->result = server_transaction();
$response = array( 'response' => array( 'code' => 204 ), 'body' => '' );
foreach ( array( array( '12.30', '' ), array( '2.30', array( 'amount' => 2.3 ) ) ) as $case ) {
	$result = $provider->refund( server_row(), 45, $case[0] );
	expect( array( 'status' => 'succeeded', 'provider_ref' => 'txn-123' ) === $result, 'refund confirmed by 204' );
	$request = end( $requests );
	expect( 'https://api.sumup.com/v0.1/me/refund/txn-123' === $request[0] && 'POST' === $request[1]['method'], 'refund endpoint' );
	expect( $case[1] === ( '' === $case[1] ? $request[1]['body'] : json_decode( $request[1]['body'], true ) ), 'full empty body vs partial amount' );
}
$row = server_row(); $row['provider_refs'] = array( 'sumup_client_transaction' => 'client:123' );
$response['response']['code'] = 200;
expect( 'succeeded' === $provider->refund( $row, 46, '1.00' )['status'] && 'client:123' === end( $transactions->ids ), 'client reference fallback and 200 success' );
foreach ( array( server_transaction( 'PENDING' ), array(), array_merge( server_transaction(), array( 'id' => '' ) ) ) as $transactions->result ) {
	$requests = array(); provider_error_expect( $provider->refund( server_row(), 45, '12.30' ), 'transaction_not_found' );
	expect( array() === $requests, 'unconfirmed transaction not refunded' );
}
$transactions->result = server_transaction();
foreach ( array( new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'bad' ), array( 'response' => array( 'code' => 400 ), 'body' => '{"message":"Rejected"}' ) ) as $response ) {
	provider_error_expect( $provider->refund( server_row(), 45, '12.30' ), is_wp_error( $response ) ? 'timeout' : 'sumup_refund_rejected' );
}
echo "server-refund ok\n";
