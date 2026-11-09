<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * The scheme type of the loyalty program.
 */
enum DiscountSourceLoyaltySchemeType : string
{
    case STAMPS = 'STAMPS';
    case POINTS = 'POINTS';
}
