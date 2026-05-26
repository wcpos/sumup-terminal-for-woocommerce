<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Status of a device
 */
enum StatusResponseDataStatus: string
{
    case ONLINE = 'ONLINE';
    case OFFLINE = 'OFFLINE';
}
