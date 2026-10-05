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
// A terminated checkout with no SumUp transaction is over on the next poll (a physical Solo never
// records one); a transaction that exists wins; nothing is remembered when terminate was refused.
$GLOBALS['transients'] = array();
$transactions->result = array(); $readers->cancel_result = true; $readers->cancels = array();
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'before terminate, 404 is still waiting' );
expect( 'requested' === $provider->cancel( 'reader:client:123' ), 'terminate accepted' );
expect( 15 * MINUTE_IN_SECONDS === end( $GLOBALS['ttls'] ), 'terminate remembered for fifteen minutes' );
expect( 'cancelled' === $provider->fetch( 'reader:client:123' )['status'], 'after terminate, 404 is cancelled' );
expect( 'pending' === $provider->fetch( 'reader:client:999' )['status'], 'another checkout is untouched' );
$transactions->result = server_transaction( 'SUCCESSFUL' );
expect( 'completed' === $provider->fetch( 'reader:client:123' )['status'], 'a transaction that exists wins over the terminate' );
$GLOBALS['transients'] = array();
$transactions->result = array(); $readers->cancel_result = false;
provider_error_expect( $provider->cancel( 'reader:client:123' ) );
expect( array() === $GLOBALS['transients'], 'a refused terminate is not remembered' );
expect( 'pending' === $provider->fetch( 'reader:client:123' )['status'], 'and the poll keeps waiting' );
echo "server-cancel ok\n";
