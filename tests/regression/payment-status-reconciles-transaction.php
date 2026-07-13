<?php
// phpcs:ignoreFile

$root = __DIR__ . '/../..';
$handler = file_get_contents($root . '/includes/AjaxHandler.php');

if ($handler === false) {
	fwrite(STDERR, "Failed to read AjaxHandler.php\n");
	exit(1);
}

$start = strpos($handler, 'public function ajax_check_payment_status');
$end = strpos($handler, 'public function ajax_webhook');
$method = ($start !== false && $end !== false) ? substr($handler, $start, $end - $start) : '';

if (!file_exists($root . '/includes/Services/TransactionService.php')) {
	fwrite(STDERR, "TransactionService is missing.\n");
	exit(1);
}

require_once $root . '/includes/Services/HttpClient.php';
require_once $root . '/includes/Services/ProfileService.php';
require_once $root . '/includes/Services/TransactionService.php';

if (
	!preg_match('/get_transaction_id\s*\(\s*\).*?get_by_client_transaction_id/s', $method)
	|| strpos($method, '$order->update_meta_data( \'_sumup_transaction_status\', $transaction_status );') === false
	|| strpos($method, '! empty( $response_id )') === false
	|| strpos($method, 'hash_equals( $transaction_id, $response_id )') === false
	|| strpos($method, 'in_array( $response_status, $final_transaction_statuses, true )') === false
	|| strpos($method, "get_meta( '_sumup_transaction_checked_at' )") === false
	|| !preg_match('/\$force_transaction_check\s*=.*?\$_POST\[\'force_transaction_check\'\]/s', $method)
	|| !preg_match('/\$transaction_check_due\s*\|\|\s*\$force_transaction_check/s', $method)
) {
	fwrite(STDERR, "Pending payments are not reconciled against the SumUp Transactions API.\n");
	exit(1);
}

function wp_remote_request($url, $args) {
	$GLOBALS['sumup_transaction_request'] = array($url, $args);

	return array(
		'body' => json_encode(array(
			'client_transaction_id' => 'txn-123',
			'status' => 'SUCCESSFUL',
		)),
		'response' => array('code' => 200),
	);
}

function wp_remote_retrieve_body($response) {
	return $response['body'];
}

function wp_remote_retrieve_response_code($response) {
	return $response['response']['code'];
}

function is_wp_error($value) {
	return false;
}

$profile = new class extends WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService {
	public function get_merchant_code($force_refresh = false) {
		return 'M1234567';
	}
};
$service = new WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService(
	'api-key',
	'https://api.sumup.test'
);
$service->set_profile_service($profile);
$transaction = $service->get_by_client_transaction_id('txn-123');

if (($transaction['status'] ?? '') !== 'SUCCESSFUL') {
	fwrite(STDERR, "TransactionService did not return the authoritative payment status.\n");
	exit(1);
}

$expected_url = 'https://api.sumup.test/v2.1/merchants/M1234567/transactions?client_transaction_id=txn-123';
if (($GLOBALS['sumup_transaction_request'][0] ?? '') !== $expected_url) {
	fwrite(STDERR, "TransactionService queried the wrong endpoint.\n");
	exit(1);
}

echo "PASS: pending payments reconcile against the SumUp Transactions API.\n";
