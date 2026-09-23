<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Domains\Payments\Actions\ReceivePaymentWebhookAction;
use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Support\PaymentWebhookHeaderAllowlist;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly ReceivePaymentWebhookAction $receive,
        private readonly PaymentWebhookHeaderAllowlist $headers,
    ) {}

    public function store(Request $request, string $providerCode): JsonResponse
    {
        if (PaymentProviderCode::tryFrom($providerCode) === null) {
            throw PaymentException::providerUnknown();
        }

        $raw = $request->getContent();
        $safe = $this->headers->filter($request->headers->all());

        $this->receive->execute(
            $providerCode,
            $raw,
            $safe,
            (string) $request->headers->get('Content-Type', 'application/json'),
        );

        return response()->json(
            ['received' => true],
            200,
            ['Cache-Control' => 'private, no-store'],
        )->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
