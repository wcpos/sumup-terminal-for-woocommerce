<?php
// phpcs:ignoreFile

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

require_once __DIR__ . '/../../includes/Services/ReaderApiClientInterface.php';
require_once __DIR__ . '/../../includes/Services/HttpClient.php';
require_once __DIR__ . '/../../includes/Services/WordPressHttpReaderApiClient.php';
require_once __DIR__ . '/../../includes/Services/SdkAvailability.php';
require_once __DIR__ . '/../../includes/Services/SdkReaderApiClient.php';

$reflection = new ReflectionClass('WCPOS\\WooCommercePOS\\SumUpTerminal\\Services\\SdkReaderApiClient');
$client = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('normalize_reader');
if (PHP_VERSION_ID < 80100) {
    $method->setAccessible(true);
}

$reader = new stdClass();
$reader->id = 'reader-123';
$reader->name = 'Front Desk';
$reader->status = 'paired';
$reader->createdAt = '2026-05-26T10:00:00Z';
$reader->device = new stdClass();
$reader->device->model = 'solo';
$reader->device->identifier = 'SN123';

$result = $method->invoke($client, $reader);

$expected = array(
    'id' => 'reader-123',
    'name' => 'Front Desk',
    'status' => 'paired',
    'created_at' => '2026-05-26T10:00:00Z',
    'device' => array(
        'model' => 'solo',
        'identifier' => 'SN123',
    ),
);

if ($result !== $expected) {
    fwrite(STDERR, "SDK reader normalization did not preserve the existing array shape.\n");
    fwrite(STDERR, var_export($result, true) . "\n");
    exit(1);
}

echo "PASS: SDK reader normalization matches existing array shape.\n";

$checkoutMethod = $reflection->getMethod('normalize_checkout_response');
if (PHP_VERSION_ID < 80100) {
    $checkoutMethod->setAccessible(true);
}

$checkout = (object) array(
    'data' => (object) array(
        'clientTransactionId' => 'txn-123',
        'totalAmount' => (object) array(
            'minorUnit' => 2,
        ),
    ),
);

$checkoutResult = $checkoutMethod->invoke($client, $checkout);

$expectedCheckout = array(
    'data' => array(
        'client_transaction_id' => 'txn-123',
        'total_amount' => array(
            'minor_unit' => 2,
        ),
    ),
);

if ($checkoutResult !== $expectedCheckout) {
    fwrite(STDERR, "SDK checkout normalization did not convert response keys to snake_case.\n");
    fwrite(STDERR, var_export($checkoutResult, true) . "\n");
    exit(1);
}

echo "PASS: SDK checkout normalization matches existing array shape.\n";
