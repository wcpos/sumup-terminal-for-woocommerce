<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Device_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
expect( class_exists( SumUp_Device_Provider::class ), 'device adapter is missing' );
function wp_remote_request( $url, $args ) { return server_result( $GLOBALS['response'] ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
$transactions = new TransactionService( 'test' );
$provider = new SumUp_Device_Provider( $transactions, null, new ServerProfile() );
foreach ( array( 'SUCCESSFUL' => 'completed', 'PENDING' => 'in_progress', 'FAILED' => 'cancelled', 'CANCELLED' => 'cancelled' ) as $status => $expected ) {
	$transaction = server_transaction( $status ) + array( 'foreign_transaction_id' => 'payment:123' );
	foreach ( array( $transaction, array( 'items' => array( array( 'foreign_transaction_id' => 'other', 'status' => 'SUCCESSFUL' ), $transaction ) ) ) as $body ) {
		$response = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $body ) );
		$result = $provider->fetch( 'payment:123' );
		expect( $expected === $result['status'] && 'payment:123' === $result['payment_id'], 'SumUp truth and payment correlation' );
		expect( '12.30' === $result['amount'] && 'EUR' === $result['currency'], 'normalized money' );
		expect( 'txn-123' === $result['provider_refs']['transaction_id'] && 'CODE' === $result['provider_refs']['transaction_code'] && 'payment:123' === $result['provider_refs']['foreign_transaction_id'], 'UUID, code and foreign ID retained' );
		expect( '0123' === $result['receipt']['card_last4'] && 'VISA' === $result['receipt']['card_type'], 'card receipt retained' );
		expect( null === $result['failure_reason'] && ! isset( $result['handoff'] ), 'no invented failure reason or persisted handoff' );
	}
}
$response['body'] = json_encode( server_transaction() );
expect( 'payment:123' === $provider->fetch( 'payment:123' )['payment_id'] && 'completed' === $provider->fetch( 'payment:123' )['status'], 'singleton missing foreign ID uses requested ref' );
foreach ( array( array( 'items' => array() ), array( 'foreign_transaction_id' => 'other', 'status' => 'SUCCESSFUL' ), array( 'items' => array( server_transaction() ) ), array( 'foreign_transaction_id' => 'payment:123', 'status' => 'UNKNOWN' ) ) as $body ) {
	$response['body'] = json_encode( $body );
	expect( 'pending' === $provider->fetch( 'payment:123' )['status'], 'unmatched or unknown observations never complete payment' );
}
foreach ( array( new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'Unavailable' ), array( 'response' => array( 'code' => 503 ), 'body' => 'Unavailable' ), array( 'response' => array( 'code' => 200 ), 'body' => 'invalid JSON' ) ) as $response ) {
	expect( is_wp_error( $provider->fetch( 'payment:123' ) ), 'lookup failures remain errors, not money states' );
}
expect( is_wp_error( $transactions->find_by_foreign_transaction_id( '' ) ), 'empty lookup ref rejected' );
echo "server-device-fetch ok\n";
