<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * The source of the catalog the line originates from.
 */
enum ItemReferencesV10CatalogSource : string
{
    case SUMUP = 'SUMUP';
    case EXTERNAL = 'EXTERNAL';
}
