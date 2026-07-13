<?php
// phpcs:ignoreFile

$handler = file_get_contents(__DIR__ . '/../../includes/AjaxHandler.php');

if ($handler === false) {
	fwrite(STDERR, "Failed to read AjaxHandler.php\n");
	exit(1);
}

$start = strpos($handler, 'public function ajax_check_payment_status');
$end = strpos($handler, 'public function ajax_webhook');

if ($start === false || $end === false || $end <= $start) {
	fwrite(STDERR, "Could not isolate ajax_check_payment_status().\n");
	exit(1);
}

$method = substr($handler, $start, $end - $start);

if (!preg_match('/get_status\s*\(\s*\$reader_id\s*\)/', $method)) {
	fwrite(STDERR, "Payment status does not query SumUp reader status.\n");
	exit(1);
}

if (!preg_match('/hash_equals\s*\(\s*\$order->get_order_key\(\),\s*\$order_key\s*\)/', $method)) {
	fwrite(STDERR, "Payment status does not authorize the order context.\n");
	exit(1);
}

if (!preg_match('/\$reader_id\s*=\s*sanitize_text_field\s*\(\s*\(string\)\s*\$order->get_meta/', $method) || strpos($method, "\$_POST['reader_id']") !== false) {
	fwrite(STDERR, "Payment status must bind reader lookup to order metadata.\n");
	exit(1);
}

if (substr_count($handler, "\$_POST['order_key']") < 3 || substr_count($handler, 'hash_equals( $order->get_order_key(), $order_key )') < 3) {
	fwrite(STDERR, "Payment create, cancel, and status endpoints must authorize the order context.\n");
	exit(1);
}

if (!preg_match("/ajax_cancel_checkout.*?get_meta\( '_sumup_reader_id' \).*?cancel_checkout/s", $handler)) {
	fwrite(STDERR, "Cancellation must bind the requested reader to the order.\n");
	exit(1);
}

if (!preg_match("/ajax_create_checkout.*?delete_meta_data\( '_sumup_checkout_status' \).*?delete_meta_data\( '_sumup_transaction_status' \).*?create_checkout_for_order/s", $handler)) {
	fwrite(STDERR, "Starting a retry must clear terminal metadata from the previous attempt.\n");
	exit(1);
}

$transaction_read = strpos($method, "get_meta( '_sumup_transaction_status' )");
$empty_fallback = strpos($method, 'if ( empty( $checkout_status ) )');
if ($transaction_read === false || ($empty_fallback !== false && $transaction_read > $empty_fallback)) {
	fwrite(STDERR, "Terminal transaction status must take precedence over nonterminal checkout metadata.\n");
	exit(1);
}

if (substr_count($handler, 'webhook_matches_current_attempt') < 3) {
	fwrite(STDERR, "Webhook state changes must correlate to the active transaction.\n");
	exit(1);
}

if (!preg_match("/webhook_matches_current_attempt.*?'CREATING'.*?get_meta\( '_sumup_checkout_status' \)/s", $handler)) {
	fwrite(STDERR, "Webhooks must be gated during transaction-ID handoff.\n");
	exit(1);
}

if (!preg_match('/\'reader_status\'\s*=>\s*\$reader_status/', $method)) {
	fwrite(STDERR, "Payment status response does not include reader status.\n");
	exit(1);
}

echo "PASS: payment status includes live SumUp reader state.\n";
