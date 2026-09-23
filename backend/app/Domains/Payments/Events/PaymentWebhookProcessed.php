<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

final readonly class PaymentWebhookProcessed
{
    public const VERSION = 1;

    public function __construct(
        public string $webhookPublicId,
        public string $provider,
        public string $processingStatus,
        public int $eventVersion = self::VERSION,
    ) {}
}
