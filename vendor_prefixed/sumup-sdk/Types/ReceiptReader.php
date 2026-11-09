<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Card reader details displayed on the receipt.
 */
class ReceiptReader
{
    /**
     * Unique identifier of the physical card reader.
     *
     * @var string|null
     */
    public ?string $code = null;
    /**
     * Model of the physical card reader.
     *
     * @var string|null
     */
    public ?string $type = null;
}
