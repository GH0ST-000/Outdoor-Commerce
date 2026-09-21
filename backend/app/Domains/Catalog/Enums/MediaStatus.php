<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * Lifecycle of a stored original. Only `ready` assets are ever exposed publicly.
 */
enum MediaStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Quarantined = 'quarantined';

    /**
     * Statuses a worker may pick up for derivative generation.
     *
     * @return list<self>
     */
    public static function processable(): array
    {
        return [self::Pending, self::Processing, self::Failed];
    }

    public function isTerminal(): bool
    {
        return $this === self::Ready || $this === self::Quarantined;
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }
}
