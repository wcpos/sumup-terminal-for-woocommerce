<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * The net amount (including modifiers if applied). It does NOT include discounts.
 */
class PriceAmountsTotalNetAmount
{
    /**
     * Currency ISO 4217 code
     *
     * @var string
     */
    public string $currency;
    /**
     * Amount in minor units (e.g. cents).
     *
     * @var int
     */
    public int $value;
}
