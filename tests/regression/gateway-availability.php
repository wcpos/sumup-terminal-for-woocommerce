<?php
// phpcs:ignoreFile
// The gateway is offered on POS requests and on the order-pay page a POS user opens, never on
// the shop checkout, and never because a site once saved the old web-checkout setting.

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

class WC_Payment_Gateway {
	public $enabled = 'yes'; // The old web-checkout setting, saved before the upgrade.
	public function is_available() { return 'yes' === $this->enabled; }
}

function woocommerce_pos_request() { return $GLOBALS['pos_request']; }
function is_checkout_pay_page() { return $GLOBALS['order_pay']; }
function current_user_can( $capability ) { return 'access_woocommerce_pos' === $capability && $GLOBALS['pos_user']; }

require_once __DIR__ . '/../../includes/Abstracts/SumUpErrorHandler.php';
require_once __DIR__ . '/../../includes/Gateway.php';

$gateway  = ( new ReflectionClass( WCPOS\WooCommercePOS\SumUpTerminal\Gateway::class ) )->newInstanceWithoutConstructor();
$api_key  = new ReflectionProperty( WCPOS\WooCommercePOS\SumUpTerminal\Gateway::class, 'api_key' );
if ( PHP_VERSION_ID < 80100 ) {
	$api_key->setAccessible( true );
}

// key, pos request, order-pay page, pos user => available
$cases = array(
	array( 'test', true, false, false, true, 'a POS request with a key is offered the gateway' ),
	array( '', true, false, false, false, 'no API key, nothing is offered' ),
	array( 'test', false, true, true, true, 'the order-pay page opened by a POS user is offered the gateway' ),
	array( 'test', false, true, false, false, 'the order-pay page without the POS capability is not' ),
	array( 'test', false, false, true, false, 'the shop checkout is never offered the gateway, whatever the saved enabled option' ),
);
foreach ( $cases as $case ) {
	list( $key, $GLOBALS['pos_request'], $GLOBALS['order_pay'], $GLOBALS['pos_user'], $expected, $message ) = $case;
	$api_key->setValue( $gateway, $key );
	expect( $expected === $gateway->is_available(), $message );
}

echo "PASS: availability follows the POS, not the saved web-checkout setting.\n";
