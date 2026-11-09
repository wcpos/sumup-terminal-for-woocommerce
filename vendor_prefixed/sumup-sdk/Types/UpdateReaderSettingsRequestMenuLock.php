<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * Menu lock configuration. Provide at least one of `enabled` or `passcode`.
 */
class UpdateReaderSettingsRequestMenuLock
{
    /**
     * Whether menu lock is enabled.
     *
     * @var bool|null
     */
    public ?bool $enabled = null;
    /**
     * Four-digit PIN used to unlock the reader's menu.
     *
     * @var string|null
     */
    public ?string $passcode = null;
}
