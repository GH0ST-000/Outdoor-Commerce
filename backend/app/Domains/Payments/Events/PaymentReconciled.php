<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

final readonly class PaymentReconciled
{
    public const VERSION = 1;

    public function __construct(
        public string $paymentAttemptPublicId,
        public string $orderPublicId,
        public string $normalizedStatus,
        public int $eventVersion = self::VERSION,
    ) {}
}
