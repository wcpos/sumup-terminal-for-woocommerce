<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Method of applying the discount to the items or cart.
 */
enum DiscountSourceOfferApplicationMode : string
{
    case MANUAL = 'MANUAL';
    case AUTOMATIC = 'AUTOMATIC';
}
