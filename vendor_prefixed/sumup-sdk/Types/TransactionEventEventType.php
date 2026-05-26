<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Type of the transaction event.
 */
enum TransactionEventEventType: string
{
    case PAYOUT = 'PAYOUT';
    case CHARGE_BACK = 'CHARGE_BACK';
    case REFUND = 'REFUND';
    case PAYOUT_DEDUCTION = 'PAYOUT_DEDUCTION';
}
