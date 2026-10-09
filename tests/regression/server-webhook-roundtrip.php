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
$GLOBALS['transients'] = array();
$verified = $provider->verify_webhook( $request );
expect( ! is_wp_error( $verified ), 'successful verification' );
$ended = 'sutwc_ended_' . md5( 'client:123' );
expect( ! isset( $GLOBALS['transients'][ $ended ] ), 'a successful delivery never shortens the cancel grace: SumUp may not list the money yet' );
wcpos_settle_payment( $verified['payment_id'], $verified['patch'] );
expect( strtolower( server_row()['id'] ) === $settled[0][0], 'query id is canonicalized' );
$patch = $settled[0][1];
expect( 'captured' === $patch['status'] && '12.30' === $patch['amount'] && 'EUR' === $patch['currency'], 'authoritative settlement money' );
expect( '0123' === $patch['receipt']['card_last4'] && 'client:123:successful:SUCCESSFUL' === $patch['event_id'] && ! isset( $patch['provider_refs'] ), 'receipt, an observation-derived dedupe key, and preserved Pro refs' );
expect( ! isset( $GLOBALS['options'][ 'sutwc_settled_' . md5( strtolower( server_row()['id'] ) ) ] ), 'an ordinary row carries its client id in the action: nothing is recorded' );
$GLOBALS['options'][ 'sutwc_checkout_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ] = (string) microtime( true );
$provider->verify_webhook( $request );
expect( 'client:123' === $GLOBALS['options'][ 'sutwc_settled_' . md5( strtolower( server_row()['id'] ) ) ], 'a row whose create SumUp did not answer gets its settling client id recorded for refunds' );
// A late "failed" delivery for that row, while the lookup lists the checkout as paid, must not
// mark the row ended: the held poll would end a paid leg on it.
$late = $event; $late['payload']['status'] = 'failed'; $request->set_body( json_encode( $late ) );
$provider->verify_webhook( $request );
expect( ! isset( $GLOBALS['options'][ 'sutwc_ended_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ] ), 'a failed delivery contradicted by a paid lookup does not mark the row ended' );
$transactions->result = null; $request->set_body( json_encode( $late ) );
$provider->verify_webhook( $request );
expect( isset( $GLOBALS['options'][ 'sutwc_ended_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ] ), 'a failed delivery with nothing listed marks the row ended' );
unset( $GLOBALS['options'][ 'sutwc_checkout_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ], $GLOBALS['options'][ 'sutwc_ended_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ] );
$provider->verify_webhook( $request );
expect( ! isset( $GLOBALS['options'][ 'sutwc_ended_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ] ), 'an ordinary row (no unanswered start) gets no ended option: every decline would otherwise leave one' );
$transactions->result = array( 'items' => array( server_transaction() ) ); $request->set_body( json_encode( $event ) );
unset( $GLOBALS['options'][ 'sutwc_checkout_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ], $GLOBALS['options'][ 'sutwc_ended_' . md5( 'row-' . strtolower( server_row()['id'] ) ) ], $GLOBALS['transients'][ $ended ] );
foreach ( array( server_transaction( 'PENDING' ), array(), array_merge( server_transaction(), array( 'client_transaction_id' => 'wrong' ) ) ) as $transactions->result ) {
	$patch = $provider->verify_webhook( $request )['patch'];
	expect( array( 'event_id' ) === array_keys( $patch ) && 'client:123:successful:SUCCESSFUL' !== $patch['event_id'], 'unconfirmed webhook is event-only, under an id the confirmed delivery will not reuse' );
}
$transactions->result = server_transaction();
foreach ( array( 'pending', 'something_new', '' ) as $status ) {
	$event['payload']['status'] = $status; $request->set_body( json_encode( $event ) );
	$provider->verify_webhook( $request );
	expect( ! isset( $GLOBALS['transients'][ $ended ] ), "a '$status' delivery is not the end of the checkout" );
}
$event['payload']['status'] = 'failed'; $request->set_body( json_encode( $event ) );
expect( array( 'event_id' => 'client:123:failed:SUCCESSFUL' ) === $provider->verify_webhook( $request )['patch'], 'poll owns non-money outcomes' );
expect( isset( $GLOBALS['transients'][ $ended ] ) && abs( time() - $GLOBALS['transients'][ $ended ] ) <= 1 && 15 * MINUTE_IN_SECONDS === $GLOBALS['ttls'][ $ended ], 'a failed delivery is remembered for the cancel poll' );
unset( $GLOBALS['transients'][ $ended ] );
$event['payload']['status'] = 'CANCELLED'; $request->set_body( json_encode( $event ) );
$provider->verify_webhook( $request );
expect( isset( $GLOBALS['transients'][ $ended ] ), 'a cancelled delivery, any case, is remembered too' );
$event['payload']['status'] = 'failed';
unset( $event['id'] );
expect( array( 'event_id' => 'client:123:failed:SUCCESSFUL' ) === Provider::webhook_patch( $event, server_transaction() ), 'the dedupe key derives from the observation, never from the delivery id' );
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
