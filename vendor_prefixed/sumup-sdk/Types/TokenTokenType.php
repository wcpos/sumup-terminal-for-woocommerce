<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Authentication scheme used by the token. Always Bearer.
 */
enum TokenTokenType : string
{
    case BEARER = 'Bearer';
}
