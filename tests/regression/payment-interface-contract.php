<?php
// phpcs:ignoreFile

$gateway = file_get_contents(__DIR__ . '/../../includes/Gateway.php');
$javascript = file_get_contents(__DIR__ . '/../../assets/js/payment.js');

if ($gateway === false || $javascript === false) {
	fwrite(STDERR, "Failed to read payment interface sources.\n");
	exit(1);
}

$gateway_patterns = array(
	"/'show_payment_logs'\s*=>\s*array/" => 'gateway debug-log setting',
	'/class="[^"]*sumup-check-status-btn/' => 'manual status button',
	'/class="[^"]*sumup-cancel-btn[^>]*data-order-id=/' => 'cancel order context',
	'/data-order-key="/' => 'status request authorization context',
	'/role="status"\s+aria-live="polite"/' => 'accessible live status',
	'/class="sumup-payment-log-textarea"[^>]*readonly/' => 'readonly cashier log',
	'/class="[^"]*sumup-toggle-log/' => 'show/hide log control',
);

foreach ($gateway_patterns as $pattern => $description) {
	if (!preg_match($pattern, $gateway)) {
		fwrite(STDERR, "Missing {$description}.\n");
		exit(1);
	}
}

if (!preg_match("/'show_payment_logs'\s*=>\s*array\((.*?)\n\s*\),/s", $gateway, $setting) || !preg_match("/'default'\s*=>\s*'no'/", $setting[1])) {
	fwrite(STDERR, "Cashier debug logs must be disabled by default.\n");
	exit(1);
}

$javascript_patterns = array(
	'/activePolls\s*:\s*\{\}/' => 'owned polling registry',
	'/clearInterval\s*\(/' => 'poll cancellation',
	'/order_key\s*:\s*pollData\.orderKey/' => 'authorized order status request',
	'/timeoutPending/' => 'final status check before timeout cancellation',
	"/\\$\('\\.sumup-check-status-btn'\)\.prop\('disabled', true\)/" => 'unrelated status controls disabled during payment',
	'/\.text\s*\(/' => 'safe dynamic text rendering',
	'/sumup-toggle-log/' => 'log toggle behavior',
	'/sumup-copy-log/' => 'log copy behavior',
	'/sumup-clear-log/' => 'log clear behavior',
);

foreach ($javascript_patterns as $pattern => $description) {
	if (!preg_match($pattern, $javascript)) {
		fwrite(STDERR, "Missing {$description}.\n");
		exit(1);
	}
}

if (strpos($javascript, "$('form').first()") !== false) {
	fwrite(STDERR, "Payment completion must not submit an arbitrary form.\n");
	exit(1);
}

$cancel_start = strpos($javascript, 'requestCancellation: function');
$cancel_end = strpos($javascript, 'pollPaymentStatus: function', $cancel_start);
$cancel_method = substr($javascript, $cancel_start, $cancel_end - $cancel_start);
if (strpos($cancel_method, 'resetPaymentInterface') !== false || strpos($cancel_method, 'startPolling') === false) {
	fwrite(STDERR, "Accepted asynchronous cancellation must keep polling instead of resetting the payment interface.\n");
	exit(1);
}

if (strpos($cancel_method, 'resumePollingAfterCancellationFailure') === false) {
	fwrite(STDERR, "A failed timeout cancellation must resume payment status polling.\n");
	exit(1);
}

echo "PASS: payment interface exposes accessible logs and reliable lifecycle controls.\n";
