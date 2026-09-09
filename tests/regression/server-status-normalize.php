<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider as Provider;
list( $provider, $profile, $readers, $transactions ) = server_fixture();
expect( 'sumup' === $provider->provider(), 'provider family' );
$description = $provider->describe( new WC_Payment_Gateway() );
expect( array( 'tips' => 'none', 'refunds' => array( 'via' => 'provider', 'partial' => true ), 'void' => true ) === $description['capabilities'], 'capabilities' );
expect( 'M123' === $description['provider_data']['merchant_code'], 'merchant metadata' );
foreach ( array( false, new WP_Error( 'profile', 'bad' ), new RuntimeException( 'bad' ) ) as $profile->result ) {
	expect( null === $provider->describe( new WC_Payment_Gateway() )['provider_data']['merchant_code'], 'describe remains array on unavailable profile' );
}
foreach ( array( 'PENDING' => 'in_progress', 'SUCCESSFUL' => 'completed', 'FAILED' => 'failed', 'CANCELLED' => 'cancelled', 'other' => 'pending' ) as $status => $expected ) {
	$transactions->result = server_transaction( $status );
	$result = $provider->fetch( 'reader:client:123' );
	expect( $expected === $result['status'], $status );
	if ( 'FAILED' === $status ) { expect( 'provider_error' === $result['failure_reason'], 'failure reason' ); }
}
foreach ( array( false, array(), array( 'items' => array() ) ) as $missing ) { expect( 'pending' === Provider::normalize( $missing )['status'], 'missing observation' ); }
$transactions->result = server_transaction();
$transactions->result['client_transaction_id'] = 'wrong';
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'mismatched transaction cannot capture' );
$transactions->result = array( 'items' => array( $transactions->result, server_transaction() ) );
$result = $provider->fetch( 'reader:client:123' );
expect( 'completed' === $result['status'] && '12.30' === $result['amount'] && 'EUR' === $result['currency'], 'matching list member and decimal money' );
expect( array( 'sumup_transaction' => 'txn-123', 'sumup_transaction_code' => 'CODE', 'sumup_client_transaction' => 'client:123' ) === $result['provider_refs'], 'refs never overwrite action or reader' );
expect( array( 'card_last4' => '0123', 'card_type' => 'VISA', 'entry_mode' => 'contactless', 'payment_type' => 'POS', 'transaction_code' => 'CODE' ) === $result['receipt'], 'flat receipt mapping' );
$txn = server_transaction(); $txn['currency'] = 'JPY'; $txn['amount'] = 123;
expect( '123' === Provider::normalize( $txn )['amount'], 'zero-decimal money' );
$txn['currency'] = 'kwd'; $txn['amount'] = 1.234;
expect( '1.234' === Provider::normalize( $txn )['amount'], 'three-decimal money' );
$txn = array( 'status' => 'SUCCESSFUL', 'card' => array( 'type' => 3 ), 'entry_mode' => '', 'payment_type' => array() );
$result = Provider::normalize( $txn );
expect( null === $result['amount'] && null === $result['currency'] && array() === $result['receipt'], 'optional fields' );
foreach ( array( false, new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'bad' ) ) as $transactions->result ) { provider_error_expect( $provider->fetch( 'reader:client:123' ), is_wp_error( $transactions->result ) ? 'timeout' : 'sumup_api_error' ); }
$transactions->result = new WP_Error( '', 'Unknown upstream failure' );
provider_error_expect( $provider->fetch( 'reader:client:123' ) );
expect( 501 === $provider->capture( 'reader:client:123' )->get_error_data()['status'], 'capture stays unsupported' );
echo "server-status-normalize ok\n";
