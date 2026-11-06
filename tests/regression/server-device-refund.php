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
// SumUp's refund has no idempotency key: only a 4xx proves it refused. Every other answer may
// follow a refund SumUp made, so it is indeterminate and Free keeps the reservation.
$unclear = array(
	'timeout' => new WP_Error( 'timeout', 'Timed out' ),
	'http_request_failed' => new WP_Error( 'http_request_failed', 'Connection reset' ),
	'thrown' => new RuntimeException( 'Unavailable' ),
	'thrown Error' => new Error( 'Unavailable' ),
);
foreach ( array( 500, 503, 201, 202, 302 ) as $code ) {
	$unclear[ "HTTP $code" ] = array( 'response' => array( 'code' => $code ), 'body' => '{"message":"Something"}' );
}
foreach ( $unclear as $name => $response ) {
	$requests = array();
	$result = $provider->refund( $row, 45, '2.30' );
	expect( is_wp_error( $result ) && 'sumup_refund_unanswered' === $result->get_error_code(), "$name: unanswered, not a refusal" );
	$data = $result->get_error_data();
	expect( true === ( $data['indeterminate'] ?? null ) && 502 === ( $data['status'] ?? null ), "$name: indeterminate, status 502" );
	expect( RefundClient::UNANSWERED_MESSAGE === $result->get_error_message(), "$name: staff are told to check SumUp" );
	expect( 1 === count( $requests ), "$name: exactly one POST" );
}
foreach ( array( 400, 404, 409, 422, 499 ) as $code ) {
	$response = array( 'response' => array( 'code' => $code ), 'body' => '{"message":"Rejected ' . $code . '"}' );
	$requests = array();
	$result = $provider->refund( $row, 45, '2.30' );
	expect( is_wp_error( $result ) && 'sumup_refund_rejected' === $result->get_error_code(), "HTTP $code: a refusal" );
	$data = $result->get_error_data();
	expect( ! array_key_exists( 'indeterminate', $data ) && $code === $data['status'], "HTTP $code: not indeterminate" );
	expect( "Rejected $code" === $result->get_error_message(), "HTTP $code: SumUp's reason" );
	expect( 1 === count( $requests ), "HTTP $code: exactly one POST" );
}
expect( ! RefundClient::refused( new WP_Error( 'timeout', 'Timed out', array( 'status' => 408 ) ) ) && ! RefundClient::refused( false ), 'only SumUp\'s own 4xx answer is a refusal' );
$silent = new class( 'test' ) extends RefundClient {
	public $calls = 0;
	public function refund( string $transaction_id, ?float $amount ) { ++$this->calls; return false; }
};
$result = ( new SumUp_Device_Provider( $transactions, $silent, new ServerProfile() ) )->refund( $row, 45, '2.30' );
expect( is_wp_error( $result ) && 'sumup_refund_unanswered' === $result->get_error_code() && true === $result->get_error_data()['indeterminate'] && 1 === $silent->calls, 'false from the refund client: indeterminate' );
unset( $row['provider_refs']['transaction_id'] );
$requests = array();
$result = $provider->refund( $row, 45, '12.30' );
expect( is_wp_error( $result ) && 'transaction_not_found' === $result->get_error_code() && array() === $requests, 'missing UUID never falls back to code, and sends nothing' );
expect( ! array_key_exists( 'indeterminate', (array) $result->get_error_data() ), 'missing UUID is a refusal: nothing was sent' );
echo "server-device-refund ok\n";
