<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Services\PaymentReconciliationService;

final class RetryPaymentWebhookAction
{
    public function __construct(private readonly PaymentReconciliationService $payments) {}

    public function execute(string $webhookPublicId): void
    {
        $this->payments->retryWebhook($webhookPublicId);
    }
}
