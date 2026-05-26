<?php
// phpcs:ignoreFile

$plugin = file_get_contents(__DIR__ . '/../../sumup-terminal-for-woocommerce.php');

if ($plugin === false) {
    fwrite(STDERR, "Failed to read plugin bootstrap.\n");
    exit(1);
}

foreach (['register_activation_hook', 'sutwc_activate', 'SUTWC_MINIMUM_PHP_VERSION', 'deactivate_plugins', 'wp_die'] as $needle) {
    if (strpos($plugin, $needle) === false) {
        fwrite(STDERR, "Missing activation guard element: {$needle}\n");
        exit(1);
    }
}

if (strpos($plugin, 'PHP_VERSION_ID >= SUTWC_MINIMUM_PHP_VERSION_ID') === false) {
    fwrite(STDERR, "Activation guard does not perform the supported-runtime early return.\n");
    exit(1);
}

echo "PASS: activation guard blocks unsupported plugin runtimes cleanly.\n";
