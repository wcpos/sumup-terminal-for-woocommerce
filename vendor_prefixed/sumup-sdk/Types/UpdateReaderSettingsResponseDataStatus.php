<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Indicates that the reader applied the settings update.
 */
enum UpdateReaderSettingsResponseDataStatus : string
{
    case APPLIED = 'applied';
}
