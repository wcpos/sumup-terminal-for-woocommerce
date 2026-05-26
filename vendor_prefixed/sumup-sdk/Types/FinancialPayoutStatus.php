<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Merchant-facing outcome of the payout record.
 */
enum FinancialPayoutStatus: string
{
    case SUCCESSFUL = 'SUCCESSFUL';
    case FAILED = 'FAILED';
}
