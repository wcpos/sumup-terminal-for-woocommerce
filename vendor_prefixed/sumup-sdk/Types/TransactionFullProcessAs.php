<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Whether the transaction was processed as credit or debit.
 */
enum TransactionFullProcessAs : string
{
    case CREDIT = 'CREDIT';
    case DEBIT = 'DEBIT';
}
