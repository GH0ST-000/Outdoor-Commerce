<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

final readonly class PaymentNeedsManualReview
{
    public const VERSION = 1;

    public function __construct(
        public string $paymentAttemptPublicId,
        public string $orderPublicId,
        public string $reasonCode,
        public int $eventVersion = self::VERSION,
    ) {}
}
