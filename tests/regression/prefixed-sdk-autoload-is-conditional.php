<?php
// phpcs:ignoreFile

$plugin = file_get_contents(__DIR__ . '/../../sumup-terminal-for-woocommerce.php');

if ($plugin === false) {
    fwrite(STDERR, "Failed to read plugin bootstrap.\n");
    exit(1);
}

if (strpos($plugin, "vendor_prefixed/sumup-sdk-autoload.php") === false) {
    fwrite(STDERR, "Plugin bootstrap does not reference the prefixed SDK autoloader.\n");
    exit(1);
}

if (strpos($plugin, "PHP_VERSION_ID >= 80200") === false) {
    fwrite(STDERR, "Prefixed SDK autoloader is not guarded by PHP_VERSION_ID >= 80200.\n");
    exit(1);
}

echo "PASS: prefixed SDK autoload is conditional.\n";
