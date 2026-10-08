<?php
// phpcs:ignoreFile
// The POS order-pay page is Pro's shared panel: the gateway prints its description and hands
// the order over; the old panel's script is gone and nothing is enqueued on the page.

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function esc_html( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function wc_get_order( $order_id ) { return 42 === $order_id ? $GLOBALS['order'] : false; }
function wcpos_pro_order_pay_panel( $gateway, $order ) { $GLOBALS['panel'][] = array( $gateway, $order ); echo '<div id="wcpos-pro-order-pay"></div>'; }

class WC_Order {}
class WC_Payment_Gateway {
	public $settings = array( 'description' => 'Pay on the SumUp reader.' );
	public function get_option( $key ) { return $this->settings[ $key ] ?? ''; }
}

require_once __DIR__ . '/../../includes/Abstracts/SumUpErrorHandler.php';
require_once __DIR__ . '/../../includes/Gateway.php';

$gateway         = ( new ReflectionClass( WCPOS\WooCommercePOS\SumUpTerminal\Gateway::class ) )->newInstanceWithoutConstructor();
$GLOBALS['order'] = new WC_Order();
$GLOBALS['panel'] = array();
$GLOBALS['wp']    = (object) array( 'query_vars' => array( 'order-pay' => 42 ) );

ob_start();
$gateway->payment_fields();
$html = ob_get_clean();

expect( '<p>Pay on the SumUp reader.</p><div id="wcpos-pro-order-pay"></div>' === $html, 'the description precedes Pro\'s panel and nothing else is printed' );
expect( array( array( $gateway, $GLOBALS['order'] ) ) === $GLOBALS['panel'], 'Pro receives the gateway and the order' );
expect( ! method_exists( $gateway, 'enqueue_payment_scripts' ), 'the old panel script is not enqueued' );
expect( ! file_exists( __DIR__ . '/../../assets/js/payment.js' ), 'the old panel script is gone' );

$GLOBALS['panel'] = array();
$GLOBALS['wp']    = (object) array( 'query_vars' => array() );
ob_start();
$gateway->payment_fields();
ob_get_clean();
expect( array() === $GLOBALS['panel'], 'without an order on the page nothing is handed to Pro' );

echo "PASS: the order-pay page is Pro's panel.\n";
