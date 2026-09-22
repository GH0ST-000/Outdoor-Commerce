<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }
}
