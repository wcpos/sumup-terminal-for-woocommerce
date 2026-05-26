<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Type of the payment instrument.
 */
enum PaymentInstrumentResponseType: string
{
    case CARD = 'card';
}
