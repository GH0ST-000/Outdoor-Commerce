<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Domains\Payments\Actions\ListEligiblePaymentMethodsAction;
use App\Domains\Payments\DTOs\PaymentMethodData;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Support\OrderActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicPaymentMethodController extends Controller
{
    public function __construct(
        private readonly OrderActorFactory $actors,
        private readonly ListEligiblePaymentMethodsAction $methods,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orderId = (string) $request->query('order_id', '');
        $actor = $this->actors->fromRequest($request);
        $methods = $this->methods->execute($actor, $orderId);

        return response()->json(
            [
                'data' => array_map(
                    static fn (PaymentMethodData $method): array => $method->toPublicArray(),
                    $methods,
                ),
            ],
            200,
            ['Cache-Control' => 'private, no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
