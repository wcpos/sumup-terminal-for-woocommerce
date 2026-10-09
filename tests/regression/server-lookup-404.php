<?php
// SumUp answers 404 while no transaction exists for a checkout: that is "waiting", never an outage.
require_once __DIR__ . '/stubs/sumup-server.php';
list( $provider, $profile, $readers, $transactions ) = server_fixture();
function wcpos_settle_payment( $id, $patch ) { return true; }
$transactions->result = null;
$fetched = $provider->fetch( 'reader:client:123' );
expect( ! is_wp_error( $fetched ) && 'pending' === $fetched['status'], 'no transaction yet is pending' );
$readers->cancels = array();
expect( 'requested' === $provider->cancel( 'reader:client:123' ) && array( 'reader' ) === $readers->cancels, 'an untouched checkout can be terminated' );
$request = new WP_REST_Request();
$request->set_query_params( array( 'payment' => 'ABCDEF12-1234-1234-ABCD-123456789ABC' ) );
$request->set_body( json_encode( array( 'id' => 'evt_1', 'event_type' => 'solo.transaction.updated', 'payload' => array( 'client_transaction_id' => 'client:123', 'merchant_code' => 'M123', 'status' => 'failed' ) ) ) );
$r = $provider->verify_webhook( $request );
expect( ! is_wp_error( $r ) && array( 'event_id' => 'client:123:failed:none' ) === $r['patch'], 'a failed delivery for an untouched checkout is event-only, keyed on the observation' );
$transactions->result = false;
provider_error_expect( $provider->fetch( 'reader:client:123' ) );
// A checkout SumUp did not answer for (the adapter's row-keyed reference): an idle reader with no
// transaction also describes a PAID checkout SumUp has not listed yet, so the poll never ends the
// leg on that alone, however old the checkout. It ends only after the store's own terminate plus
// the long grace, or on SumUp's own delivery that the row's checkout ended without money.
$held = 'reader:row-ABCDEF12-1234-1234-ABCD-123456789ABC';
$key = md5( 'row-ABCDEF12-1234-1234-ABCD-123456789ABC' );
$transactions->result = null;
$readers->status_result = array( 'data' => array( 'state' => 'IDLE', 'last_activity' => gmdate( 'c' ) ) );
$GLOBALS['transients'][ 'sutwc_checkout_' . $key ] = microtime( true ) - 600;
unset( $GLOBALS['transients'][ 'sutwc_terminated_' . $key ], $GLOBALS['transients'][ 'sutwc_ended_' . $key ] );
expect( 'pending' === $provider->fetch( $held )['status'], 'an old unanswered checkout on an idle reader is still pending without the store\'s terminate' );
$GLOBALS['transients'][ 'sutwc_terminated_' . $key ] = microtime( true ) - 60;
expect( 'pending' === $provider->fetch( $held )['status'], 'within the long grace after the terminate it is still pending' );
$GLOBALS['transients'][ 'sutwc_terminated_' . $key ] = microtime( true ) - 121;
$fetched = $provider->fetch( $held );
expect( 'cancelled' === $fetched['status'] && 'expired' === $fetched['failure_reason'], 'after the terminate and the long grace, idle with nothing listed ends it' );
unset( $GLOBALS['transients'][ 'sutwc_terminated_' . $key ] );
$GLOBALS['transients'][ 'sutwc_ended_' . $key ] = microtime( true );
expect( 'cancelled' === $provider->fetch( $held )['status'], 'SumUp\'s own delivery that the row\'s checkout ended without money ends it at once' );
echo "server-lookup-404 held cases ok\n";
echo "server-lookup-404 ok\n";
