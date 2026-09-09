<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider as Provider;
function wcpos_settle_payment( $id, $patch ) { $GLOBALS['settled'][] = array( $id, $patch ); return true; }
list( $provider, $profile, $readers, $transactions ) = server_fixture();
$request = new WP_REST_Request();
$request->set_query_params( array( 'payment' => server_row()['id'] ) );
$event = array( 'id' => 'event-123', 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'client:123', 'merchant_code' => 'M123', 'status' => 'successful' ) );
$request->set_body( json_encode( $event + array( 'payment' => 'body-must-not-win' ) ) );
$transactions->result = array( 'items' => array( server_transaction() ) );
$verified = $provider->verify_webhook( $request );
expect( ! is_wp_error( $verified ), 'successful verification' );
wcpos_settle_payment( $verified['payment_id'], $verified['patch'] );
expect( strtolower( server_row()['id'] ) === $settled[0][0], 'query id is canonicalized' );
$patch = $settled[0][1];
expect( 'captured' === $patch['status'] && '12.30' === $patch['amount'] && 'EUR' === $patch['currency'], 'authoritative settlement money' );
expect( '0123' === $patch['receipt']['card_last4'] && 'event-123' === $patch['event_id'] && ! isset( $patch['provider_refs'] ), 'receipt, dedupe and preserved Pro refs' );
foreach ( array( server_transaction( 'PENDING' ), array(), array_merge( server_transaction(), array( 'client_transaction_id' => 'wrong' ) ) ) as $transactions->result ) {
	expect( array( 'event_id' => 'event-123' ) === $provider->verify_webhook( $request )['patch'], 'unconfirmed webhook is event-only' );
}
$transactions->result = server_transaction();
$event['payload']['status'] = 'failed'; $request->set_body( json_encode( $event ) );
expect( array( 'event_id' => 'event-123' ) === $provider->verify_webhook( $request )['patch'], 'poll owns non-money outcomes' );
unset( $event['id'] );
expect( array( 'event_id' => 'client:123:failed' ) === Provider::webhook_patch( $event, server_transaction() ), 'fallback dedupe key' );
$event['payload']['merchant_code'] = 'other'; $request->set_body( json_encode( $event ) );
$error = $provider->verify_webhook( $request );
expect( 'sumup_webhook_merchant_mismatch' === $error->get_error_code() && 403 === $error->get_error_data()['status'], 'merchant mismatch' );
$request->set_query_params( array( 'payment' => 'not-uuid' ) );
$error = $provider->verify_webhook( $request );
expect( 'sumup_webhook_unknown_payment' === $error->get_error_code() && 200 === $error->get_error_data()['status'], 'unrelated deliveries acknowledged' );
$request->set_query_params( array( 'payment' => server_row()['id'] ) );
$event['event_type'] = 'other'; $request->set_body( json_encode( $event ) );
$error = $provider->verify_webhook( $request );
expect( 'sumup_webhook_ignored' === $error->get_error_code() && 200 === $error->get_error_data()['status'], 'irrelevant event' );
$event['event_type'] = 'solo.transaction.updated'; $event['payload']['merchant_code'] = 'M123';
$event['payload']['client_transaction_id'] = ''; $request->set_body( json_encode( $event ) );
expect( 400 === $provider->verify_webhook( $request )->get_error_data()['status'], 'client id required' );
$event['payload']['client_transaction_id'] = 'client:123'; $request->set_body( json_encode( $event ) );
foreach ( array( false, new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'bad' ) ) as $transactions->result ) {
	provider_error_expect( $provider->verify_webhook( $request ), is_wp_error( $transactions->result ) ? 'timeout' : 'sumup_api_error' );
}
$profile->result = false;
provider_error_expect( $provider->verify_webhook( $request ) );
echo "server-webhook-roundtrip ok\n";
