<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Details of the saved payment instrument created or reused during checkout processing.
 */
class CheckoutSuccessPaymentInstrument
{
    /**
     * Unique token of the saved payment instrument.
     *
     * @var string|null
     */
    public ?string $token = null;
}
