<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentNormalizedEventType;
use App\Domains\Payments\Exceptions\PaymentException;
use Carbon\CarbonImmutable;

final class BankOfGeorgiaResponseMapper
{
    public function __construct(
        private readonly BankOfGeorgiaMoneySerializer $money,
        private readonly BankOfGeorgiaStatusMapper $statuses,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, redirect: string, details: ?string}
     */
    public function mapCreateOrder(array $payload): array
    {
        $id = $payload['id'] ?? null;
        $links = is_array($payload['_links'] ?? null) ? $payload['_links'] : [];
        $redirect = is_array($links['redirect'] ?? null) ? ($links['redirect']['href'] ?? null) : null;
        $details = is_array($links['details'] ?? null) ? ($links['details']['href'] ?? null) : null;

        if (! is_string($id) || $id === '' || ! is_string($redirect) || $redirect === '') {
            throw PaymentException::malformedProviderResponse();
        }

        return [
            'id' => $id,
            'redirect' => $redirect,
            'details' => is_string($details) && $details !== '' ? $details : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider_payment_id: string,
     *     provider_transaction_id: ?string,
     *     merchant_reference: ?string,
     *     provider_status: string,
     *     normalized_status: PaymentAttemptStatus,
     *     event_type: PaymentNormalizedEventType,
     *     amount_minor: ?int,
     *     transfer_amount_minor: ?int,
     *     currency: ?string,
     *     occurred_at: ?CarbonImmutable
     * }
     */
    public function mapPaymentDetails(array $payload): array
    {
        $body = $payload;
        if (isset($payload['body']) && is_array($payload['body'])) {
            $body = $payload['body'];
        }

        $orderId = $body['order_id'] ?? $payload['order_id'] ?? null;
        if (! is_string($orderId) || $orderId === '') {
            throw PaymentException::malformedProviderResponse();
        }

        $statusObject = is_array($body['order_status'] ?? null) ? $body['order_status'] : [];
        $statusKey = is_string($statusObject['key'] ?? null) ? $statusObject['key'] : '';
        $units = is_array($body['purchase_units'] ?? null) ? $body['purchase_units'] : [];
        $detail = is_array($body['payment_detail'] ?? null) ? $body['payment_detail'] : [];

        $requestAmount = $units['request_amount'] ?? null;
        $transferAmount = $units['transfer_amount'] ?? null;
        $currency = isset($units['currency_code']) && is_string($units['currency_code'])
            ? strtoupper($units['currency_code'])
            : null;

        $amountMinor = $requestAmount !== null && $requestAmount !== ''
            ? $this->money->fromMajor($requestAmount)
            : null;
        $transferMinor = $transferAmount !== null && $transferAmount !== ''
            ? $this->money->fromMajor($transferAmount)
            : null;

        $transactionId = isset($detail['transaction_id']) && is_string($detail['transaction_id']) && $detail['transaction_id'] !== ''
            ? $detail['transaction_id']
            : null;

        $external = isset($body['external_order_id']) && is_string($body['external_order_id'])
            ? $body['external_order_id']
            : null;

        $occurred = null;
        foreach (['zoned_request_time', 'zoned_expire_date', 'zoned_create_date'] as $field) {
            $raw = $payload[$field] ?? $body[$field] ?? null;
            if (is_string($raw) && $raw !== '') {
                $occurred = CarbonImmutable::parse($raw);
                break;
            }
        }

        $normalized = $this->statuses->toNormalizedStatus($statusKey);

        if ($normalized === PaymentAttemptStatus::Succeeded && $transferMinor !== null && $amountMinor !== null && $transferMinor !== $amountMinor) {
            $normalized = PaymentAttemptStatus::ManualReview;
        }

        return [
            'provider_payment_id' => $orderId,
            'provider_transaction_id' => $transactionId,
            'merchant_reference' => $external,
            'provider_status' => $statusKey !== '' ? $statusKey : 'unknown',
            'normalized_status' => $normalized,
            'event_type' => $this->statuses->toEventType($statusKey),
            'amount_minor' => $amountMinor,
            'transfer_amount_minor' => $transferMinor,
            'currency' => $currency,
            'occurred_at' => $occurred,
        ];
    }
}
