<?php
/**
 * Generate a PHP 7.4-safe autoloader wrapper for the prefixed PHP 8.2+ SumUp SDK.
 */

$root = dirname(__DIR__);
$targetDir = $root . '/vendor_prefixed/sumup-sdk';

if (!is_dir($targetDir)) {
    fwrite(STDERR, "Missing prefixed SDK directory: {$targetDir}\n");
    exit(1);
}

$sourceDir = null;
$candidates = array(
    $targetDir . '/src',
    $targetDir,
);

foreach ($candidates as $candidate) {
    if (is_file($candidate . '/SumUp.php')) {
        $sourceDir = $candidate;
        break;
    }
}

if ($sourceDir === null) {
    $matches = glob($targetDir . '/**/SumUp.php');
    if (!empty($matches)) {
        $sourceDir = dirname($matches[0]);
    }
}

if ($sourceDir === null) {
    fwrite(STDERR, "Unable to locate prefixed SumUp.php under {$targetDir}\n");
    exit(1);
}

$relativeSourceDir = trim(str_replace($root . '/vendor_prefixed/', '', $sourceDir), '/');

$autoload = <<<PHP
<?php
/**
 * PHP 8.2+ guarded autoloader for the prefixed official SumUp SDK.
 * Do not require SDK files on older PHP versions; they contain PHP 8.2 syntax.
 */

if (PHP_VERSION_ID < 80200) {
    return;
}

\$baseDir = __DIR__ . '/{$relativeSourceDir}/';
\$prefix = 'WCPOS\\\\WooCommercePOS\\\\SumUpTerminal\\\\Vendor\\\\SumUpSdk\\\\SumUp\\\\';

spl_autoload_register(
    static function (\$class) use (\$baseDir, \$prefix): void {
        if (strncmp(\$class, \$prefix, strlen(\$prefix)) !== 0) {
            return;
        }

        \$relative = substr(\$class, strlen(\$prefix));
        \$file = \$baseDir . str_replace('\\\\', '/', \$relative) . '.php';

        if (!is_file(\$file) && strpos(\$relative, 'Services\\\\') === 0) {
            \$service = substr(\$relative, strlen('Services\\\\'));
            \$file = \$baseDir . \$service . '/' . \$service . '.php';
        }

        if (is_file(\$file)) {
            require \$file;
        }
    }
);
PHP;

$autoloadPath = $root . '/vendor_prefixed/sumup-sdk-autoload.php';
$written = file_put_contents($autoloadPath, $autoload);
if ($written === false || $written !== strlen($autoload)) {
    fwrite(STDERR, "Failed to write prefixed SDK autoload file: {$autoloadPath}\n");
    exit(1);
}
