<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Current lifecycle status of the mandate.
 */
enum MandateResponseStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
