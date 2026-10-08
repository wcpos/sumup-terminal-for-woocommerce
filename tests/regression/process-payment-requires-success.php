<?php
// phpcs:ignoreFile
// The order-pay form submit is answered by Pro: a paid order short-circuits to the thank-you
// page, anything else is Pro's reading of the ledger.

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function wc_get_order( $order_id ) {
	expect( 123 === $order_id, 'Expected the requested order ID.' );
	return $GLOBALS['order'];
}

function wcpos_pro_order_pay_process( $order ) {
	$GLOBALS['pro_calls'][] = $order;
	return array( 'result' => 'failure' );
}

class WC_Payment_Gateway {
	public function get_return_url( $order ) {
		return '/order-received/123';
	}
}

require_once __DIR__ . '/../../includes/Abstracts/SumUpErrorHandler.php';
require_once __DIR__ . '/../../includes/Gateway.php';

$gateway = ( new ReflectionClass( WCPOS\WooCommercePOS\SumUpTerminal\Gateway::class ) )->newInstanceWithoutConstructor();

$GLOBALS['pro_calls'] = array();
$GLOBALS['order']     = new class() {
	public function is_paid() { return true; }
};
expect( array( 'result' => 'success', 'redirect' => '/order-received/123' ) === $gateway->process_payment( 123 ), 'a paid order goes straight to the thank-you page' );
expect( array() === $GLOBALS['pro_calls'], 'a paid order never reaches Pro' );

$GLOBALS['order'] = new class() {
	public function is_paid() { return false; }
};
expect( array( 'result' => 'failure' ) === $gateway->process_payment( 123 ), 'an unpaid order is answered by Pro' );
expect( array( $GLOBALS['order'] ) === $GLOBALS['pro_calls'], 'Pro is asked once with the order' );

echo "PASS: the order-pay form submit is Pro's to answer.\n";
