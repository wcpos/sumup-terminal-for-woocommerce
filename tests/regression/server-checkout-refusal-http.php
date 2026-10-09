<?php
// phpcs:ignoreFile
// The refusal path through the REAL HTTP reader client: SumUp's 4xx body and status reach the
// till (the live run's 422 for a GBP row on an EUR merchant), an outage stays a 502, and a false
// returned before any request is made never reports an earlier request's refusal.
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\WordPressHttpReaderApiClient;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_remote_request( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return server_result( $GLOBALS['response'] ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
$GLOBALS['orders'][12] = new class {
	public function get_total() { return '12.30'; }
	public function get_order_number() { return 'WEB-12'; }
	public function __call( $method, $args ) { throw new RuntimeException( 'Unexpected order mutation: ' . $method ); }
};
$client = new WordPressHttpReaderApiClient( 'test-key' );
$client->set_merchant_id( 'M123' );
$profile = new ServerProfile();
$provider = new SumUp_Server_Provider( $profile, new ReaderService( 'test-key', $client ), new ServerTransactions() );
function refusal_expect( $result, $status, $message, $code ) {
	expect( is_wp_error( $result ) && 'wcpos_provider_error' === $result->get_error_code(), 'provider envelope' );
	expect( $status === $result->get_error_data()['status'], "status $status, got " . $result->get_error_data()['status'] );
	expect( $message === $result->get_error_message(), "message '$message', got '" . $result->get_error_message() . "'" );
	expect( $code === $result->get_error_data()['detail']['code'], "detail code $code, got " . $result->get_error_data()['detail']['code'] );
}
// SumUp's Readers API refusal shape: an `errors` map, no `message`.
$response = array( 'response' => array( 'code' => 422 ), 'body' => '{"errors":{"total_amount":["this merchant can only accept \'EUR\'"]}}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 422, "this merchant can only accept 'EUR'", 'sumup_http_422' );
expect( 'https://api.sumup.com/v0.1/merchants/M123/readers/reader/checkout' === end( $requests )[0], 'checkout endpoint' );
// The v0.1 shape: `message`.
$response = array( 'response' => array( 'code' => 400 ), 'body' => '{"message":"Rejected"}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 400, 'Rejected', 'sumup_http_400' );
// An empty error body still names what happened.
$response = array( 'response' => array( 'code' => 404 ), 'body' => '' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 404, 'HTTP 404', 'sumup_http_404' );
// An outage (5xx or no response) after the checkout was sent is indeterminate: the checkout may
// be on the reader, so the row stays pending and Pro replays it (never a dropped leg).
function unanswered_expect( $result, $code, $message ) {
	expect( is_wp_error( $result ) && $code === $result->get_error_code(), "code $code, got " . ( is_wp_error( $result ) ? $result->get_error_code() : 'no error' ) );
	expect( true === ( $result->get_error_data()['indeterminate'] ?? false ), 'indeterminate' );
	expect( $message === $result->get_error_message(), "message '$message', got '" . $result->get_error_message() . "'" );
}
$response = array( 'response' => array( 'code' => 503 ), 'body' => '{"message":"Service unavailable"}' );
unanswered_expect( $provider->create_reader_action( server_row(), 'reader' ), 'sumup_checkout_unanswered', 'Service unavailable' );
$response = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
unanswered_expect( $provider->create_reader_action( server_row(), 'reader' ), 'sumup_checkout_unanswered', 'cURL error 28: timed out' );
// A busy reader on a first attempt is final: another sale holds the reader, nothing of ours is on it.
$response = array( 'response' => array( 'code' => 409 ), 'body' => '{"message":"The reader is busy with another checkout."}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 409, 'The reader is busy with another checkout.', 'sumup_http_409' );
// A REPLAY (Free appended "Provider did not answer" to the row) sends nothing: the first checkout
// may be on the reader and may be paid, and SumUp cannot say which. The adapter hands Pro a
// row-keyed reference by which that checkout is polled and cancelled, from the moment the
// unanswered create was sent.
$replayed = server_row(); $replayed['events'] = array( array( 't' => gmdate( 'c' ), 'level' => 'warning', 'message' => 'Provider did not answer: sumup_checkout_unanswered' ) );
$sent = count( $requests );
$result = $provider->create_reader_action( $replayed, 'reader' );
$row_key = md5( 'row-' . strtolower( server_row()['id'] ) );
expect( array( 'ref' => 'reader:row-' . strtolower( server_row()['id'] ), 'expires_at' => null ) === $result, 'a replay hands back the row-keyed reference (the row id lower-cased, as the webhook keys it)' );
expect( $sent === count( $requests ), 'a replay sends no checkout' );
expect( isset( $GLOBALS['options'][ 'sutwc_checkout_' . $row_key ] ), 'the unanswered checkout is timed from its own start, in a durable option' );
// With the start marker gone, a replay treats the checkout as older than the grace, never as new.
unset( $GLOBALS['options'][ 'sutwc_checkout_' . $row_key ] );
$provider->create_reader_action( $replayed, 'reader' );
expect( microtime( true ) - (float) $GLOBALS['options'][ 'sutwc_checkout_' . $row_key ] > 120, 'a missing start marker is rebuilt as old' );
$GLOBALS['options'][ 'sutwc_checkout_' . $row_key ] = (string) microtime( true );
// Cancelling through the row-keyed reference: within SumUp's checkout window the reader is
// terminated (the first checkout is likely still on it); past the window it is not, since the
// reader may be on another sale by now, and the store's cancel is only recorded.
$response = array( 'response' => array( 'code' => 204 ), 'body' => '' );
$held = 'reader:row-' . strtolower( server_row()['id'] );
$sent = count( $requests );
expect( 'requested' === $provider->cancel( $held ), 'a held reference cancels as requested' );
expect( $sent + 1 === count( $requests ) && false !== strpos( end( $requests )[0], '/readers/reader/terminate' ), 'within the window the reader is terminated' );
$GLOBALS['options'][ 'sutwc_checkout_' . $row_key ] = (string) ( microtime( true ) - 121 );
unset( $GLOBALS['options'][ 'sutwc_terminated_' . $row_key ] );
$sent = count( $requests );
expect( 'requested' === $provider->cancel( $held ), 'an aged held reference still cancels as requested' );
expect( $sent === count( $requests ), 'past the window nothing is sent: the reader may be on another sale' );
expect( isset( $GLOBALS['options'][ 'sutwc_terminated_' . $row_key ] ), 'the store\'s cancel is recorded for the poll' );
// A terminate the reader refuses (not waiting for a card: the checkout already ended) is not an
// error for the cashier: the store's cancel is recorded and the poll decides.
$GLOBALS['transients'][ 'sutwc_checkout_' . md5( 'client:123' ) ] = microtime( true );
unset( $GLOBALS['transients'][ 'sutwc_terminated_' . md5( 'client:123' ) ] );
$response = array( 'response' => array( 'code' => 422 ), 'body' => '{"message":"The reader is not waiting for a card."}' );
expect( 'requested' === $provider->cancel( 'reader:client:123' ) && isset( $GLOBALS['transients'][ 'sutwc_terminated_' . md5( 'client:123' ) ] ), 'a refused terminate means nothing to terminate: requested, and recorded for the poll' );
$response = array( 'response' => array( 'code' => 503 ), 'body' => '{"message":"Service unavailable"}' );
expect( is_wp_error( $provider->cancel( 'reader:client:123' ) ), 'an outage on terminate is an error the till retries' );
$response = array( 'response' => array( 'code' => 422 ), 'body' => '{"errors":{"total_amount":["this merchant can only accept \'EUR\'"]}}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 422, "this merchant can only accept 'EUR'", 'sumup_http_422' );
// A success after a refusal clears the record.
$response = array( 'response' => array( 'code' => 201 ), 'body' => '{"data":{"client_transaction_id":"client:123"}}' );
expect( array( 'ref' => 'reader:client:123', 'expires_at' => null ) === $provider->create_reader_action( server_row(), 'reader' ), 'success after refusal' );
expect( null === $client->last_error(), 'success clears last_error' );
// A false before any request (no merchant id, and the profile cannot supply one) must not
// report the previous request's refusal.
$response = array( 'response' => array( 'code' => 422 ), 'body' => '{"errors":{"total_amount":["this merchant can only accept \'EUR\'"]}}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 422, "this merchant can only accept 'EUR'", 'sumup_http_422' );
$client->set_merchant_id( '' );
$profile->result = false;
$requests = array();
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 502, 'SumUp API request failed.', 'sumup_api_error' );
expect( array() === $requests, 'no request without a merchant id' );
echo "server-checkout-refusal-http ok\n";
