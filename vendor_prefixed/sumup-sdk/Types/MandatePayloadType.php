<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Type of mandate to create for the saved payment instrument.
 */
enum MandatePayloadType: string
{
    case RECURRENT = 'recurrent';
}
