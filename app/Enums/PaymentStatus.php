<?php

namespace App\Enums;

/**
 * Lifecycle of a payment request.
 *
 * pending ──approve──▶ approved   (terminal)
 *    │
 *    ├────reject───▶ rejected     (terminal)
 *    │
 *    └────48h TTL──▶ expired      (terminal, automated)
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * The statuses a finance member is allowed to move a pending request into.
     *
     * @return array<int, self>
     */
    public static function financeDecisions(): array
    {
        return [self::Approved, self::Rejected];
    }
}
