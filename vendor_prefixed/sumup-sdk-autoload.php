<?php
/**
 * PHP 8.2+ guarded autoloader for the prefixed official SumUp SDK.
 * Do not require SDK files on older PHP versions; they contain PHP 8.2 syntax.
 */

if (PHP_VERSION_ID < 80200) {
    return;
}

$baseDir = __DIR__ . '/sumup-sdk/';
$prefix = 'WCPOS\\WooCommercePOS\\SumUpTerminal\\Vendor\\SumUpSdk\\SumUp\\';

spl_autoload_register(
    static function ($class) use ($baseDir, $prefix): void {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (!is_file($file) && strpos($relative, 'Services\\') === 0) {
            $service = substr($relative, strlen('Services\\'));
            $file = $baseDir . $service . '/' . $service . '.php';
        }

        if (is_file($file)) {
            require $file;
        }
    }
);