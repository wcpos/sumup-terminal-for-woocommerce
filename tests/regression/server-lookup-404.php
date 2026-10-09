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
// Held markers are durable options (a cache may evict a transient at any time).
$GLOBALS['options'][ 'sutwc_checkout_' . $key ] = (string) ( microtime( true ) - 600 );
unset( $GLOBALS['options'][ 'sutwc_terminated_' . $key ], $GLOBALS['options'][ 'sutwc_ended_' . $key ] );
expect( 'pending' === $provider->fetch( $held )['status'], 'an old unanswered checkout on an idle reader is still pending without the store\'s terminate' );
$GLOBALS['options'][ 'sutwc_terminated_' . $key ] = (string) ( microtime( true ) - 60 );
expect( 'pending' === $provider->fetch( $held )['status'], 'within the long grace after the terminate it is still pending' );
$GLOBALS['options'][ 'sutwc_terminated_' . $key ] = (string) ( microtime( true ) - 121 );
$fetched = $provider->fetch( $held );
expect( 'cancelled' === $fetched['status'] && 'expired' === $fetched['failure_reason'], 'after the terminate and the long grace, nothing listed ends it' );
expect( array() === $readers->status_calls, 'the held poll never reads the reader' );
unset( $GLOBALS['options'][ 'sutwc_terminated_' . $key ] );
$GLOBALS['options'][ 'sutwc_ended_' . $key ] = (string) microtime( true );
expect( 'pending' === $provider->fetch( $held )['status'], 'SumUp\'s unsigned delivery alone never ends a leg, not even one that says the checkout ended' );
$GLOBALS['options'][ 'sutwc_terminated_' . $key ] = (string) ( microtime( true ) - 5 );
expect( 'cancelled' === $provider->fetch( $held )['status'], 'after the store\'s own cancel, SumUp\'s later delivery that the checkout ended waives the grace' );
$GLOBALS['options'][ 'sutwc_ended_' . $key ] = (string) ( microtime( true ) - 30 );
expect( 'pending' === $provider->fetch( $held )['status'], 'a delivery older than the store\'s cancel does not waive the grace' );
// With affiliate keys the checkout carried the row id as foreign_transaction_id: a paid one is
// found on the poll, a cancel sends nothing, and a lookup SumUp cannot answer concludes nothing.
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test', 'affiliate_app_id' => 'app', 'affiliate_key' => 'key' );
$transactions->result = array( 'items' => array( server_transaction() + array( 'foreign_transaction_id' => 'ABCDEF12-1234-1234-ABCD-123456789ABC' ) ) );
$fetched = $provider->fetch( $held );
expect( 'completed' === $fetched['status'] && 'client:123' === $fetched['provider_refs']['sumup_client_transaction'], 'a paid unanswered checkout is found by its foreign id' );
$readers->cancels = array();
expect( 'requested' === $provider->cancel( $held ) && array() === $readers->cancels, 'a cancel of a paid unanswered checkout sends nothing: the poll captures it' );
$transactions->result = false;
$GLOBALS['options'][ 'sutwc_terminated_' . $key ] = (string) ( microtime( true ) - 121 );
expect( 'pending' === $provider->fetch( $held )['status'], 'a foreign-id lookup SumUp could not answer concludes nothing, even past the grace' );
$transactions->result = null;
expect( 'cancelled' === $provider->fetch( $held )['status'], 'nothing listed by foreign id past the grace ends it' );
$GLOBALS['options']['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'test' );
echo "server-lookup-404 held cases ok\n";
echo "server-lookup-404 ok\n";
