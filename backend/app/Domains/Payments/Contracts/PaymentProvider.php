<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\DTOs\CancelProviderPaymentResultData;
use App\Domains\Payments\DTOs\CreateProviderPaymentRequestData;
use App\Domains\Payments\DTOs\CreateProviderPaymentResultData;
use App\Domains\Payments\DTOs\ProviderPaymentReferenceData;
use App\Domains\Payments\DTOs\ProviderPaymentStatusResultData;
use App\Domains\Payments\DTOs\ProviderWebhookRequestData;
use App\Domains\Payments\DTOs\VerifiedProviderWebhookData;

interface PaymentProvider
{
    public function code(): string;

    public function createPayment(CreateProviderPaymentRequestData $request): CreateProviderPaymentResultData;

    public function fetchPaymentStatus(ProviderPaymentReferenceData $reference): ProviderPaymentStatusResultData;

    public function parseAndVerifyWebhook(ProviderWebhookRequestData $request): VerifiedProviderWebhookData;

    public function cancelPayment(ProviderPaymentReferenceData $reference): CancelProviderPaymentResultData;
}
