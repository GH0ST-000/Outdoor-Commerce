<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shipping;

use App\Domains\Shared\Support\CorrelationId;
use App\Domains\Shipping\Actions\ReceiveShipmentWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShipmentWebhookController extends Controller
{
    public function __construct(private readonly ReceiveShipmentWebhookAction $receive) {}

    public function store(Request $request, string $providerCode): JsonResponse
    {
        $raw = $request->getContent();
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            if (is_array($values) && isset($values[0]) && is_string($values[0])) {
                $headers[strtolower((string) $name)] = $values[0];
            }
        }

        $this->receive->execute(
            $providerCode,
            is_string($raw) ? $raw : '',
            $headers,
            (string) $request->header('Content-Type', 'application/json'),
        );

        return response()->json(['received' => true], 200)
            ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
