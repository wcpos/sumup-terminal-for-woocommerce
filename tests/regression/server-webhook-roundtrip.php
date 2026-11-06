<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider as Provider;
function wcpos_settle_payment( $id, $patch ) { $GLOBALS['settled'][] = array( $id, $patch ); return true; }
function wcpos_pro_payment_id_for_action( $provider, $ref ) { return $GLOBALS['adopted_map'][ $ref ] ?? null; }
// The query SumUp calls back with: the result URL the checkout was created with.
function url_query( $id, $reader = '' ) { parse_str( parse_url( Provider::webhook_url( $id, $reader ), PHP_URL_QUERY ), $q ); return $q; }
list( $provider, $profile, $readers, $transactions ) = server_fixture();
$request = new WP_REST_Request();
$request->set_query_params( url_query( server_row()['id'] ) );
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
$request->set_query_params( url_query( server_row()['id'] ) );
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
// A delivery captures only for the row whose result URL it was sent to (the token), and never for
// another row its client id is bound to; asking SumUp nothing when it is refused.
list( $provider, $profile, $readers, $transactions ) = server_fixture();
$GLOBALS['options'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['adopted_map'] = array();
$a = server_row()['id']; $b = 'BBBBBBBB-1234-1234-1234-ABCDEF123456';
$event['payload']['status'] = 'successful'; $request->set_body( json_encode( $event ) );
$transactions->result = server_transaction();
$deliver = function ( $query ) use ( $provider, $request, $transactions ) { $request->set_query_params( $query ); $asked = count( $transactions->ids ); $r = $provider->verify_webhook( $request ); return array( $r, count( $transactions->ids ) - $asked ); };
$refused = function ( $d ) { return is_wp_error( $d[0] ) && 'sumup_webhook_unknown_payment' === $d[0]->get_error_code() && 200 === $d[0]->get_error_data()['status'] && 0 === $d[1]; };
$captured = function ( $d, $id ) { return ! is_wp_error( $d[0] ) && 'captured' === ( $d[0]['patch']['status'] ?? '' ) && strtolower( $id ) === $d[0]['payment_id']; };
expect( substr( wp_hash( 'sutwc_webhook|' . $a . '|reader' ), 0, 20 ) === url_query( $a, 'reader' )['token'], 'the result URL carries the token of its row and reader' );
expect( $refused( $deliver( array( 'payment' => $a, 'reader' => 'reader', 'token' => 'wrong' ) ) ), 'a wrong token is acknowledged and ignored, SumUp not asked' );
expect( $refused( $deliver( array( 'payment' => $a, 'reader' => 'reader' ) ) ), 'a missing token (a checkout from before the token) is acknowledged and ignored' );
expect( $refused( $deliver( array( 'payment' => $a, 'reader' => 'reader', 'token' => array( 'x' ) ) ) ), 'a token sent as an array is acknowledged and ignored, with no PHP error' );
expect( $refused( $deliver( array( 'payment' => $b ) + url_query( $a, 'reader' ) ) ), 'another row\'s token does not open this row' );
expect( $captured( $deliver( url_query( $a, 'reader' ) ), $a ), 'the right token captures' );
$GLOBALS['orders'][12] = new class { public function get_order_number() { return '12'; } };
$provider->create_reader_action( server_row(), 'reader' );
expect( $refused( $deliver( url_query( $b, 'reader' ) ) ), 'a client id bound to row A, delivered to row B\'s valid URL, is refused' );
expect( $captured( $deliver( url_query( $a, 'reader' ) ), $a ), 'its own row still captures' );
unset( $GLOBALS['transients'][ 'sutwc_row_' . md5( 'client:123' ) ] );
expect( $captured( $deliver( url_query( $b, 'reader' ) ), $b ), 'an evicted binding refuses nothing: the token is the guard' );
// A row whose create SumUp did not answer, with affiliate keys: SumUp's listing for the row's foreign
// id, not the client-id lookup, proves the money, and it must name this delivery's client id.
$held = 'row-' . strtolower( $a ); $row = strtolower( $a ); $settled = 'sutwc_settled_' . md5( $row );
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test', 'affiliate_app_id' => 'app', 'affiliate_key' => 'key' );
$GLOBALS['options'][ 'sutwc_checkout_' . md5( $held ) ] = (string) microtime( true );
$transactions->result = server_transaction();
$asked_foreign = function () use ( $transactions, $row ) { return in_array( $row, $transactions->ids, true ); };
$other = array_merge( server_transaction(), array( 'client_transaction_id' => 'client:other' ) );
foreach ( array( 'another client id' => $other, 'no client id' => array_diff_key( server_transaction(), array( 'client_transaction_id' => 1 ) ), 'nothing listed' => null ) as $case => $transactions->foreign ) {
	$transactions->ids = array();
	$d = $deliver( url_query( $a, 'reader' ) );
	expect( is_wp_error( $d[0] ) && 'sumup_webhook_unknown_payment' === $d[0]->get_error_code() && 200 === $d[0]->get_error_data()['status'] && $asked_foreign() && ! isset( $GLOBALS['options'][ $settled ] ), "a foreign listing with $case is refused at 200, nothing settled" );
}
foreach ( array( new RuntimeException( 'down' ), false, 'not json' ) as $transactions->foreign ) {
	$d = $deliver( url_query( $a, 'reader' ) );
	provider_error_expect( $d[0] ); // As a failed lookup answers: no patch, and SumUp retries.
	expect( ! isset( $GLOBALS['options'][ $settled ] ), 'a foreign listing SumUp could not answer settles nothing' );
}
$transactions->foreign = array( 'items' => array( array_merge( server_transaction(), array( 'client_transaction_id' => 'CLIENT:123', 'foreign_transaction_id' => strtoupper( $a ) ) ) ) );
$transactions->ids = array();
expect( $captured( $deliver( url_query( $a, 'reader' ) ), $a ) && $asked_foreign() && 'client:123' === $GLOBALS['options'][ $settled ], 'the row\'s own transaction, its client id in another case, captures and is recorded' );
$transactions->foreign = $other; $transactions->result = null; $event['payload']['status'] = 'failed'; $request->set_body( json_encode( $event ) );
$transactions->ids = array();
$deliver( url_query( $a, 'reader' ) );
expect( isset( $GLOBALS['options'][ 'sutwc_ended_' . md5( $held ) ] ) && ! $asked_foreign(), 'an empty lookup refuses nothing: a failed delivery still marks the row ended, SumUp\'s foreign listing not asked' );
$transactions->result = server_transaction(); $event['payload']['status'] = 'successful'; $request->set_body( json_encode( $event ) );
unset( $GLOBALS['options'][ 'sutwc_checkout_' . md5( $held ) ], $GLOBALS['options'][ 'sutwc_ended_' . md5( $held ) ], $GLOBALS['options'][ $settled ] );
$transactions->ids = array();
expect( $captured( $deliver( url_query( $a, 'reader' ) ), $a ) && ! $asked_foreign(), 'an answered row (no start marker) captures with keys set, its foreign listing not asked' );
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test' );
$GLOBALS['options'][ 'sutwc_checkout_' . md5( $held ) ] = (string) microtime( true );
$transactions->ids = array();
expect( $captured( $deliver( url_query( $a, 'reader' ) ), $a ) && ! $asked_foreign(), 'without affiliate keys the token alone guards the row: it captures, no foreign listing asked' );
$transactions->foreign = 'same';
// The adopted path is unchanged: the action reference resolves the row, no token needed.
$GLOBALS['options'] = array(); $transactions->result = server_transaction(); $event['payload']['status'] = 'successful'; $request->set_body( json_encode( $event ) );
$GLOBALS['adopted_map'] = array( 'reader:client:123' => 'cccccccc-1234-1234-1234-abcdef123456' );
expect( $captured( $deliver( array( 'reader' => 'reader' ) ), 'cccccccc-1234-1234-1234-abcdef123456' ), 'an adopted attempt captures without a token' );
echo "server-webhook-roundtrip ok\n";
