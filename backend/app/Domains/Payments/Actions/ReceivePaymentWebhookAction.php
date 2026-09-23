<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Models\PaymentWebhook;
use App\Domains\Payments\Services\ReceivePaymentWebhookService;

final class ReceivePaymentWebhookAction
{
    public function __construct(private readonly ReceivePaymentWebhookService $webhooks) {}

    /**
     * @param  array<string, string>  $safeHeaders
     */
    public function execute(string $providerCode, string $rawBody, array $safeHeaders, string $contentType): PaymentWebhook
    {
        return $this->webhooks->execute($providerCode, $rawBody, $safeHeaders, $contentType);
    }
}
