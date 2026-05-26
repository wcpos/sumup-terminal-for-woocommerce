<?php
/**
 * Ensure the scoped SumUp SDK files use the plugin namespace prefix.
 */

$root      = dirname(__DIR__);
$targetDir = $root . '/vendor_prefixed/sumup-sdk';
$prefix    = 'WCPOS\\WooCommercePOS\\SumUpTerminal\\Vendor\\SumUpSdk\\SumUp';

if (!is_dir($targetDir)) {
    fwrite(STDERR, "Missing prefixed SDK directory: {$targetDir}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Failed to read file: {$path}\n");
        exit(1);
    }

    $replaced = preg_replace('/namespace\\s+SumUp\\b/', 'namespace ' . $prefix, $contents);
    if ($replaced === null || preg_last_error() !== PREG_NO_ERROR) {
        fwrite(STDERR, "Regex namespace replacement failed for file: {$path}\n");
        exit(1);
    }
    $contents = $replaced;

    $replaced = preg_replace('/use\\s+SumUp\\\\/', 'use ' . str_replace('\\', '\\\\', $prefix) . '\\\\', $contents);
    if ($replaced === null || preg_last_error() !== PREG_NO_ERROR) {
        fwrite(STDERR, "Regex use replacement failed for file: {$path}\n");
        exit(1);
    }
    $contents = $replaced;

    $replaced = preg_replace('/(?<!SumUpSdk)\\\\SumUp\\\\/', '\\\\' . str_replace('\\', '\\\\', $prefix) . '\\\\', $contents);
    if ($replaced === null || preg_last_error() !== PREG_NO_ERROR) {
        fwrite(STDERR, "Regex fully-qualified replacement failed for file: {$path}\n");
        exit(1);
    }
    $contents = $replaced;

    $written = file_put_contents($path, $contents);
    if ($written === false || $written !== strlen($contents)) {
        fwrite(STDERR, "Failed to write file: {$path}\n");
        exit(1);
    }
}
