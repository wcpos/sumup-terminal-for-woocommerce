<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Details of an API error.
 */
class Error
{
    /**
     * Short description of the error.
     *
     * @var string|null
     */
    public ?string $message = null;
    /**
     * Platform code for the error.
     *
     * @var string|null
     */
    public ?string $errorCode = null;
}
