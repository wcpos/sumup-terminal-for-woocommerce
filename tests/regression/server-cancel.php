<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
list( $provider, $profile, $readers, $transactions ) = server_fixture();
foreach ( array( 'SUCCESSFUL' => 'requested', 'FAILED' => 'final', 'CANCELLED' => 'final' ) as $status => $expected ) {
	$transactions->result = server_transaction( $status );
	expect( $expected === $provider->cancel( 'reader:client:123' ), $status );
	expect( array() === $readers->cancels, 'do not terminate final transactions' );
}
foreach ( array( server_transaction( 'PENDING' ), array(), array( 'items' => array() ) ) as $transactions->result ) {
	$readers->cancels = array();
	expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'asynchronous terminate' );
	expect( array( 'reader' ) === $readers->cancels && 'client:123' === end( $transactions->ids ), 'split only first colon' );
}
foreach ( array( false, new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'bad' ) ) as $readers->cancel_result ) {
	provider_error_expect( $provider->cancel( 'reader:client:123' ), is_wp_error( $readers->cancel_result ) ? 'timeout' : 'sumup_api_error' );
}
$transactions->result = false; $readers->cancels = array();
provider_error_expect( $provider->cancel( 'reader:client:123' ) );
expect( array() === $readers->cancels, 'lookup failure does not terminate blindly' );
// SumUp records no transaction for a checkout nobody paid, so "it is over" is the authenticated
// reader status (IDLE, active since the checkout or our terminate), only after a grace period, only
// on an empty lookup; any transaction that exists wins; nothing is remembered when terminate was
// refused or the marker write failed.
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
$grace = SumUp_Server_Provider::TERMINATE_GRACE_SECONDS;
$idle = function ( $ago ) { return array( 'data' => array( 'state' => 'IDLE', 'last_activity' => gmdate( 'Y-m-d\TH:i:s\Z', time() - $ago ) ) ); };
$GLOBALS['transients'] = array();
$transactions->result = array(); $readers->cancel_result = true; $readers->cancels = array(); $readers->status_result = $idle( 0 );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array() === $readers->status_calls, 'no marker: still waiting, reader not asked' );
expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'terminate accepted' );
$key = 'sutwc_terminated_' . md5( 'client:123' );
expect( isset( $GLOBALS['transients'][ $key ] ) && 15 * MINUTE_IN_SECONDS === $GLOBALS['ttls'][ $key ], 'terminate remembered for fifteen minutes' );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array() === $readers->status_calls, 'inside the grace period: still waiting, reader not asked' );
// SumUp's delivery for this checkout, arriving after our terminate, waives the grace: the reader has
// processed the terminate, so the authenticated status is asked at once — and still decides alone.
$seen = 'sutwc_webhook_' . md5( 'client:123' );
$GLOBALS['transients'][ $seen ] = $GLOBALS['transients'][ $key ] - 1;
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array() === $readers->status_calls, 'a delivery from before the terminate waives nothing' );
$GLOBALS['transients'][ $seen ] = time();
$readers->status_result = array( 'data' => array( 'state' => 'PROCESSING', 'last_activity' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array( 'reader' ) === $readers->status_calls, 'delivery after the terminate, reader still busy: asked at once, still waiting' );
$readers->status_result = $idle( 0 );
expect( 'cancelled' === $provider->fetch( 'reader:client:123' )['status'], 'delivery after the terminate, reader idle: cancelled inside the grace period' );
unset( $GLOBALS['transients'][ $seen ] );
$readers->status_calls = array();
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array() === $readers->status_calls, 'without the delivery the grace period holds' );
// A walk-away (no terminate) never shortens on a delivery alone.
$GLOBALS['transients'] = array( 'sutwc_checkout_' . md5( 'client:123' ) => time(), $seen => time() );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array() === $readers->status_calls, 'a delivery without our terminate waives nothing' );
$GLOBALS['transients'] = array( $key => time() );
$GLOBALS['transients'][ $key ] = time() - $grace;
$readers->status_result = array( 'data' => array( 'state' => 'PROCESSING', 'last_activity' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'] && array( 'reader' ) === $readers->status_calls, 'reader still busy: waiting' );
$readers->status_result = $idle( $grace + 60 );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'idle but inactive since before the terminate: waiting' );
$readers->status_result = false;
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'status call failed: waiting' );
$readers->status_result = $idle( 0 );
expect( 'cancelled' === $provider->fetch( 'reader:client:123' )['status'], 'idle since the terminate, no transaction: cancelled' );
// A terminate of a checkout the reader had already dropped: no new activity after the terminate,
// but activity after the checkout began, which is what counts.
$GLOBALS['transients'][ 'sutwc_checkout_' . md5( 'client:123' ) ] = time() - 400;
$readers->status_result = $idle( 300 );
expect( 'cancelled' === $provider->fetch( 'reader:client:123' )['status'], 'reader idle since the checkout began, terminated later: cancelled' );
unset( $GLOBALS['transients'][ 'sutwc_checkout_' . md5( 'client:123' ) ] );
$readers->status_result = $idle( 0 );
expect( 'pending' === $provider->fetch( 'reader:client:999' )['status'], 'another checkout is untouched' );
foreach ( array( 'SUCCESSFUL' => 'completed', 'PENDING' => 'in_progress', 'SOMETHING_NEW' => 'pending' ) as $status => $expected ) {
	$transactions->result = server_transaction( $status );
	expect( $expected === $provider->fetch( 'reader:client:123' )['status'], "a transaction that exists ($status) wins" );
}
// The reader gave up on its own (about a minute, no terminate from the till): the checkout marker
// written at creation is the "since", so the walk-away leg ends when the reader is idle again.
$GLOBALS['transients'] = array( 'sutwc_checkout_' . md5( 'client:123' ) => time() - 90 );
$transactions->result = array(); $readers->status_result = $idle( 30 );
$walked = $provider->fetch( 'reader:client:123' );
expect( 'cancelled' === $walked['status'] && 'expired' === $walked['failure_reason'], 'reader idle after the checkout began: cancelled without a terminate, as a timeout' );
$readers->status_result = $idle( 120 );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'idle since before the checkout: the push may not have arrived yet' );
$GLOBALS['transients'] = array();
$readers->cancel_result = false;
provider_error_expect( $provider->cancel( 'reader:client:123' ) );
expect( array() === $GLOBALS['transients'], 'a refused terminate is not remembered' );
$readers->cancel_result = true; $GLOBALS['transient_write_fails'] = true; $readers->status_result = $idle( 0 );
expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'terminate still accepted when the marker cannot be written' );
expect( array() === $GLOBALS['transients'] && 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'nothing remembered: the poll waits for the deadline' );
unset( $GLOBALS['transient_write_fails'] );
echo "server-cancel ok\n";
