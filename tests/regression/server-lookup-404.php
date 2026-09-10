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
expect( ! is_wp_error( $r ) && array( 'event_id' => 'evt_1' ) === $r['patch'], 'a failed delivery for an untouched checkout is event-only' );
$transactions->result = false;
provider_error_expect( $provider->fetch( 'reader:client:123' ) );
echo "server-lookup-404 ok\n";
