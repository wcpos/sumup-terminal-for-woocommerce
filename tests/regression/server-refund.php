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
// SumUp's refund has no idempotency key: an answer that does not prove a refusal may follow a refund
// SumUp made, so it keeps Free's reservation (indeterminate) instead of freeing the amount for a retry.
function refund_unanswered_expect( $result, $label ) {
	expect( is_wp_error( $result ) && 'sumup_refund_unanswered' === $result->get_error_code(), "$label: unanswered code" );
	expect( true === ( $result->get_error_data()['indeterminate'] ?? null ), "$label: indeterminate" );
}
$http = function ( $code, $body = '' ) { return array( 'response' => array( 'code' => $code ), 'body' => $body ); };
$unclear = array(
	'timeout' => new WP_Error( 'timeout', 'Timed out' ),
	'http_request_failed' => new WP_Error( 'http_request_failed', 'cURL error 28' ),
	'throw' => new RuntimeException( 'bad' ),
	'thrown Error' => new Error( 'bad' ),
	'transport error carrying a 4xx status' => new WP_Error( 'http_request_failed', 'Proxy refused', array( 'status' => 403 ) ),
);
foreach ( array( 500, 502, 503, 599, 201, 302 ) as $code ) { $unclear[ "HTTP $code" ] = $http( $code, '{"message":"Upstream"}' ); }
foreach ( $unclear as $label => $response ) {
	$requests = array();
	refund_unanswered_expect( $provider->refund( server_row(), 45, '12.30' ), $label );
	expect( 1 === count( $requests ) && 'https://api.sumup.com/v0.1/me/refund/txn-123' === $requests[0][0], "$label: the refund POST was made" );
}
foreach ( array( 400, 404, 409, 422, 499 ) as $code ) {
	$requests = array();
	$response = $http( $code, '{"message":"Rejected ' . $code . '"}' );
	$result = $provider->refund( server_row(), 45, '12.30' );
	provider_error_expect( $result, 'sumup_refund_rejected' );
	expect( ! array_key_exists( 'indeterminate', $result->get_error_data() ), "HTTP $code: a refusal is not indeterminate" );
	expect( "Rejected $code" === $result->get_error_message(), "HTTP $code: SumUp's reason is surfaced" );
	expect( 1 === count( $requests ), "HTTP $code: the refund POST was made" );
}
// A refund client answering false (no answer it could name) is unclear too.
class FalseRefunds extends WCPOS\WooCommercePOS\SumUpTerminal\Services\RefundClient {
	public $calls = 0;
	public function refund( string $transaction_id, ?float $amount ) { $this->calls++; return false; }
}
$false_refunds = new FalseRefunds( 'test' );
$false_provider = new WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider( $profile, new WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService( 'test', $readers ), $transactions, $false_refunds );
refund_unanswered_expect( $false_provider->refund( server_row(), 45, '12.30' ), 'false' );
expect( 1 === $false_refunds->calls, 'false: the refund client was called' );
// Before the POST nothing was sent: a lookup that fails or throws is a refusal, and no refund is placed.
foreach ( array( false, new RuntimeException( 'lookup down' ) ) as $transactions->result ) {
	$requests = array();
	$result = $provider->refund( server_row(), 45, '12.30' );
	provider_error_expect( $result, 'sumup_api_error' );
	expect( ! array_key_exists( 'indeterminate', $result->get_error_data() ), 'a failed lookup is not indeterminate' );
	expect( array() === $requests, 'a failed lookup places no refund' );
}
echo "server-refund ok\n";
