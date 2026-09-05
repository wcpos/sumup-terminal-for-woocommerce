<?php
// phpcs:ignoreFile

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function __( $text, $domain ) {
	return $text;
}

function wc_get_order( $order_id ) {
	expect( 123 === $order_id, 'Expected the requested order ID.' );
	return $GLOBALS['order'];
}

function wc_add_notice( $message, $type ) {
	$GLOBALS['notices'][] = array( $message, $type );
}

class WC_Payment_Gateway {
	public function get_return_url( $order ) {
		return '/order-received/123';
	}
}

require_once __DIR__ . '/../../includes/Abstracts/SumUpErrorHandler.php';
require_once __DIR__ . '/../../includes/Gateway.php';

$gateway = ( new ReflectionClass( WCPOS\WooCommercePOS\SumUpTerminal\Gateway::class ) )->newInstanceWithoutConstructor();
$cases   = array(
	// Checkout status, transaction status, already paid, transaction ID, result, completion calls.
	array( '', '', false, 'txn-123', 'failure', 0 ),
	array( 'PENDING', 'PENDING', false, 'txn-123', 'failure', 0 ),
	array( 'FAILED', 'FAILED', false, 'txn-123', 'failure', 0 ),
	array( 'SUCCESSFUL', 'PAID', false, 'txn-123', 'failure', 0 ),
	array( 'PAID', '', false, 'txn-123', 'success', 1 ),
	array( '', 'SUCCESSFUL', false, 'txn-123', 'success', 1 ),
	array( 'paid', '', false, 'txn-123', 'success', 1 ),
	array( '', 'successful', false, 'txn-123', 'success', 1 ),
	array( '', '', true, 'txn-123', 'success', 0 ),
	array( 'PAID', 'SUCCESSFUL', true, 'txn-123', 'success', 0 ),
	array( '', '', false, '', null, 0 ),
);

foreach ( $cases as $index => $case ) {
	$GLOBALS['notices'] = array();
	$GLOBALS['order']   = new class( $case ) {
		public $completion_calls = 0;
		private $case;

		public function __construct( $case ) {
			$this->case = $case;
		}

		public function get_transaction_id() {
			return $this->case[3];
		}

		public function get_meta( $key ) {
			$meta = array( '_sumup_checkout_status' => $this->case[0], '_sumup_transaction_status' => $this->case[1] );
			return $meta[ $key ] ?? '';
		}

		public function is_paid() {
			return $this->case[2];
		}

		public function payment_complete() {
			++$this->completion_calls;
		}
	};

	$result = $gateway->process_payment( 123 );
	expect( $case[5] === $GLOBALS['order']->completion_calls, "Case {$index}: unexpected payment_complete() calls." );
	expect( $case[4] === ( $result['result'] ?? null ), "Case {$index}: unexpected result." );
	if ( 'success' === $case[4] ) {
		expect( '/order-received/123' === $result['redirect'], "Case {$index}: missing success redirect." );
		expect( array() === $GLOBALS['notices'], "Case {$index}: unexpected notice." );
	} else {
		expect( 1 === count( $GLOBALS['notices'] ), "Case {$index}: expected one notice." );
		expect( 'error' === $GLOBALS['notices'][0][1], "Case {$index}: expected an error notice." );
		if ( 'failure' === $case[4] ) {
			expect( array( 'result' => 'failure' ) === $result, "Case {$index}: unexpected failure payload." );
			expect( false !== strpos( $GLOBALS['notices'][0][0], 'terminal payment has not completed yet' ), "Case {$index}: missing incomplete-payment explanation." );
		} else {
			expect( null === $result, 'No transaction ID must preserve the existing empty return.' );
		}
	}
}

echo "process-payment-requires-success ok\n";
