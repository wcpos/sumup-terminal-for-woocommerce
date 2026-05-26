<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * The card type of the card used for the transaction.
 * Is is required only for some countries (e.g: Brazil).
 *
 */
enum CreateReaderCheckoutRequestCardType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
}
