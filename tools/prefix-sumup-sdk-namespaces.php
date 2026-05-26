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
    $contents = preg_replace('/namespace\\s+SumUp\\b/', 'namespace ' . $prefix, $contents);
    $contents = preg_replace('/use\\s+SumUp\\\\/', 'use ' . str_replace('\\', '\\\\', $prefix) . '\\\\', $contents);
    $contents = preg_replace('/(?<!SumUpSdk)\\\\SumUp\\\\/', '\\\\' . str_replace('\\', '\\\\', $prefix) . '\\\\', $contents);
    file_put_contents($path, $contents);
}
