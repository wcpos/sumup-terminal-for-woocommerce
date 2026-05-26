<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Type of the transaction for the registered user specified in the `user` property.
 */
enum TransactionHistoryType: string
{
    case PAYMENT = 'PAYMENT';
    case REFUND = 'REFUND';
    case CHARGE_BACK = 'CHARGE_BACK';
}
