<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types;

/**
 * The status of the membership.
 */
enum MemberStatus: string
{
    case ACCEPTED = 'accepted';
    case PENDING = 'pending';
    case EXPIRED = 'expired';
    case DISABLED = 'disabled';
    case UNKNOWN = 'unknown';
}
