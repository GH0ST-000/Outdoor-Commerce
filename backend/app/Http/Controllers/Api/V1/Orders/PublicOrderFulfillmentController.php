<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Domains\Shared\Support\CorrelationId;
use App\Domains\Shipping\Actions\GetOrderFulfillmentAction;
use App\Domains\Shipping\Services\FulfillmentPresenter;
use App\Http\Controllers\Controller;
use App\Http\Support\OrderActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicOrderFulfillmentController extends Controller
{
    public function __construct(
        private readonly OrderActorFactory $actors,
        private readonly GetOrderFulfillmentAction $get,
        private readonly FulfillmentPresenter $presenter,
    ) {}

    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $order = $this->get->execute($this->actors->fromRequest($request), $orderPublicId);
        $locale = str_starts_with(strtolower((string) $request->header('X-Locale', 'ka')), 'en') ? 'en' : 'ka';

        return response()->json(
            $this->presenter->presentCustomer($order, $locale),
            200,
            ['Cache-Control' => 'private, no-store'],
        )->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
