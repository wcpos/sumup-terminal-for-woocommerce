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
// A terminated checkout with no SumUp transaction is over once the grace period has passed (a
// physical Solo never records one; a tap that raced the terminate needs time to show up); any
// transaction that exists wins; nothing is remembered when terminate was refused or the write failed.
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
$GLOBALS['transients'] = array();
$transactions->result = array(); $readers->cancel_result = true; $readers->cancels = array();
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'before terminate, 404 is still waiting' );
expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'terminate accepted' );
expect( 15 * MINUTE_IN_SECONDS === end( $GLOBALS['ttls'] ), 'terminate remembered for fifteen minutes' );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'inside the grace period, 404 is still waiting' );
$key = key( $GLOBALS['transients'] );
$GLOBALS['transients'][ $key ] = time() - SumUp_Server_Provider::TERMINATE_GRACE_SECONDS;
expect( 'cancelled' === $provider->fetch( 'reader:client:123' )['status'], 'after the grace period, 404 is cancelled' );
expect( 'pending' === $provider->fetch( 'reader:client:999' )['status'], 'another checkout is untouched' );
foreach ( array( 'SUCCESSFUL' => 'completed', 'PENDING' => 'in_progress', 'SOMETHING_NEW' => 'pending' ) as $status => $expected ) {
	$transactions->result = server_transaction( $status );
	expect( $expected === $provider->fetch( 'reader:client:123' )['status'], "a transaction that exists ($status) wins over the terminate" );
}
$GLOBALS['transients'] = array();
$transactions->result = array(); $readers->cancel_result = false;
provider_error_expect( $provider->cancel( 'reader:client:123' ) );
expect( array() === $GLOBALS['transients'], 'a refused terminate is not remembered' );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'and the poll keeps waiting' );
$readers->cancel_result = true; $GLOBALS['transient_write_fails'] = true;
expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'terminate still accepted when the marker cannot be written' );
expect( array() === $GLOBALS['transients'], 'nothing remembered' );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'the poll waits for the deadline, as before the marker' );
unset( $GLOBALS['transient_write_fails'] );
echo "server-cancel ok\n";
