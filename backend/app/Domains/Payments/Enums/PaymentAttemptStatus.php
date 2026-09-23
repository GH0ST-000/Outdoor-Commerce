<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentAttemptStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Unknown = 'unknown';
    case ManualReview = 'manual_review';

    public function isActive(): bool
    {
        return in_array($this, [
            self::Created,
            self::Pending,
            self::RequiresAction,
            self::Processing,
            self::Unknown,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Failed,
            self::Cancelled,
            self::Expired,
            self::ManualReview,
        ], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Created,
            self::Pending,
            self::RequiresAction,
            self::Unknown,
        ], true);
    }
}
