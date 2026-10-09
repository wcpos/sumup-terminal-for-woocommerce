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
// A row whose create SumUp did not answer, settled by its delivery: with affiliate keys the
// refund finds the transaction by the row's foreign id; without them, through the id the webhook
// recorded against the row; with neither, SumUp is not asked for a refund it cannot place.
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test', 'affiliate_app_id' => 'app', 'affiliate_key' => 'key' );
$response = array( 'response' => array( 'code' => 204 ), 'body' => '' );
$held_row = server_row(); $held_row['provider_refs'] = array( 'action' => 'reader:row-' . strtolower( server_row()['id'] ), 'reader' => 'reader' );
$transactions->result = array( 'items' => array( server_transaction() + array( 'foreign_transaction_id' => server_row()['id'] ) ) );
expect( array( 'status' => 'succeeded', 'provider_ref' => 'txn-123' ) === $provider->refund( $held_row, 46, '12.30' ), 'a held row refunds the transaction found by its foreign id' );
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test' );
$GLOBALS['options'][ 'sutwc_settled_' . md5( strtolower( server_row()['id'] ) ) ] = 'client:123';
$transactions->result = server_transaction();
expect( array( 'status' => 'succeeded', 'provider_ref' => 'txn-123' ) === $provider->refund( $held_row, 47, '12.30' ), 'a held row refunds the transaction its delivery recorded' );
unset( $GLOBALS['options'][ 'sutwc_settled_' . md5( strtolower( server_row()['id'] ) ) ] );
$requests = array();
$result = $provider->refund( $held_row, 48, '12.30' );
expect( is_wp_error( $result ) && 'transaction_not_found' === $result->get_error_data()['detail']['code'] && array() === $requests, 'with nothing recorded, no refund is placed' );
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
foreach ( array( array( new WP_Error( 'timeout', 'Timed out' ), 'timeout' ), array( new RuntimeException( 'bad' ), 'sumup_api_error' ), array( array( 'response' => array( 'code' => 400 ), 'body' => '{"message":"Rejected"}' ), 'sumup_refund_rejected' ) ) as $case ) {
	list( $response, $expected ) = $case;
	$result = $provider->refund( server_row(), 45, '12.30' );
	provider_error_expect( $result, $expected );
	if ( 'sumup_refund_rejected' === $expected ) { expect( 'Rejected' === $result->get_error_message(), "SumUp's reason is surfaced" ); }
}
echo "server-refund ok\n";
