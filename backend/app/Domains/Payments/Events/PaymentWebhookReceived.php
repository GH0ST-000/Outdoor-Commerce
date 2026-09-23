<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

final readonly class PaymentWebhookReceived
{
    public const VERSION = 1;

    public function __construct(
        public string $webhookPublicId,
        public string $provider,
        public ?string $providerEventId,
        public int $eventVersion = self::VERSION,
    ) {}
}
