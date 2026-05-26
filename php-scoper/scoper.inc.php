<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'WCPOS\\WooCommercePOS\\SumUpTerminal\\Vendor\\SumUpSdk',
    'finders' => [
        Finder::create()
            ->files()
            ->in(__DIR__ . '/vendor/sumup/sumup-php/src'),
    ],
    'patchers' => [],
];
