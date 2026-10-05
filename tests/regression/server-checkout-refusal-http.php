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
// An outage (5xx or no response) stays transport: the till retries it.
$response = array( 'response' => array( 'code' => 503 ), 'body' => '{"message":"Service unavailable"}' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 502, 'Service unavailable', 'sumup_http_503' );
$response = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
refusal_expect( $provider->create_reader_action( server_row(), 'reader' ), 502, 'cURL error 28: timed out', 'http_request_failed' );
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
