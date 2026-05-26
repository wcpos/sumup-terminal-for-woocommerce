<?php
// phpcs:ignoreFile

$helper = file_get_contents(__DIR__ . '/../../includes/Services/SdkAvailability.php');
$gateway = file_get_contents(__DIR__ . '/../../includes/Gateway.php');

if ($helper === false || $gateway === false) {
    fwrite(STDERR, "Failed to read SDK availability helper or Gateway.\n");
    exit(1);
}

foreach (['official SumUp PHP SDK', 'PHP 8.2', 'WordPress HTTP compatibility client'] as $text) {
    if (strpos($helper, $text) === false && strpos($gateway, $text) === false) {
        fwrite(STDERR, "Missing expected compatibility message text: {$text}\n");
        exit(1);
    }
}

if (strpos($gateway, 'get_sdk_status_html') === false) {
    fwrite(STDERR, "Gateway does not define SDK status HTML.\n");
    exit(1);
}

if (strpos($gateway, 'public function admin_options') === false || strpos($gateway, '$this->get_sdk_status_html()') === false) {
    fwrite(STDERR, "Gateway does not render SDK status from admin_options().\n");
    exit(1);
}

echo "PASS: SDK compatibility message is present.\n";
