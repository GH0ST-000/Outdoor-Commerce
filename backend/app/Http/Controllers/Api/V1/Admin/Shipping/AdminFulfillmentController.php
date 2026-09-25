<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Shipping;

use App\Domains\Shared\Support\CorrelationId;
use App\Domains\Shipping\Actions\CreateShipmentAction;
use App\Domains\Shipping\Actions\GetAdminFulfillmentAction;
use App\Domains\Shipping\Actions\TransitionShipmentAction;
use App\Domains\Shipping\DTOs\CreateShipmentData;
use App\Domains\Shipping\DTOs\ShipmentItemQuantityData;
use App\Domains\Shipping\DTOs\TransitionShipmentData;
use App\Domains\Shipping\Enums\ShipmentExceptionCode;
use App\Domains\Shipping\Exceptions\ShipmentIdempotencyReplayException;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Services\FulfillmentPresenter;
use App\Domains\Shipping\Services\ShipmentIdempotencyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Shipping\CreateShipmentRequest;
use App\Http\Requests\Api\V1\Admin\Shipping\TransitionShipmentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFulfillmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GetAdminFulfillmentAction $get,
        private readonly CreateShipmentAction $create,
        private readonly TransitionShipmentAction $transitions,
        private readonly FulfillmentPresenter $presenter,
        private readonly ShipmentIdempotencyService $idempotency,
    ) {}

    public function order(Request $request, string $orderPublicId): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);
        $order = $this->get->order($orderPublicId);

        return $this->json($request, $this->presenter->presentAdminOrder($order, $this->locale($request)));
    }

    public function store(CreateShipmentRequest $request, string $orderPublicId): JsonResponse
    {
        $this->authorize('manage', Shipment::class);
        $validated = $request->validated();
        $items = [];
        foreach ($validated['items'] as $item) {
            $items[] = new ShipmentItemQuantityData((string) $item['order_item_id'], (int) $item['quantity']);
        }

        $data = new CreateShipmentData(
            orderPublicId: $orderPublicId,
            items: $items,
            providerCode: (string) $validated['provider_code'],
            warehouseCode: $validated['warehouse_code'] ?? null,
            fulfillmentType: $validated['fulfillment_type'] ?? null,
            carrierDisplayName: $validated['carrier_display_name'] ?? null,
            trackingNumber: $validated['tracking_number'] ?? null,
            trackingUrl: $validated['tracking_url'] ?? null,
            packageCount: isset($validated['package_count']) ? (int) $validated['package_count'] : null,
            internalNote: $validated['internal_note'] ?? null,
            idempotencyKey: $request->idempotencyKey(),
            actorUserId: (int) $request->user()?->getAuthIdentifier(),
            requestId: $this->requestId($request),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        try {
            $record = $this->idempotency->begin(
                $data->actorUserId,
                'admin.shipments.store',
                $request->idempotencyKey(),
                $validated,
            );
            $shipment = $this->create->execute($data);
        } catch (ShipmentIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode());
        }

        $body = $this->presenter->presentAdminShipment($shipment, $this->locale($request));
        $this->idempotency->complete($record, $body, 201);

        return $this->json($request, $body, 201);
    }

    public function show(Request $request, string $shipmentPublicId): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        return $this->json($request, $this->presenter->presentAdminShipment(
            $this->get->shipment($shipmentPublicId),
            $this->locale($request),
        ));
    }

    public function startPreparation(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'startPreparation');
    }

    public function pick(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'recordPicked');
    }

    public function pack(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'recordPacked');
    }

    public function readyForDispatch(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markReadyForDispatch');
    }

    public function dispatch(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'dispatch');
    }

    public function markInTransit(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markInTransit');
    }

    public function markOutForDelivery(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markOutForDelivery');
    }

    public function markDelivered(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markDelivered');
    }

    public function recordDeliveryAttemptFailed(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'recordDeliveryAttemptFailed');
    }

    public function markReadyForPickup(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markReadyForPickup');
    }

    public function markCollected(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'markCollected');
    }

    public function recordException(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'recordException');
    }

    public function cancel(TransitionShipmentRequest $request, string $shipmentPublicId): JsonResponse
    {
        return $this->mutate($request, $shipmentPublicId, 'cancel');
    }

    private function mutate(TransitionShipmentRequest $request, string $shipmentPublicId, string $method): JsonResponse
    {
        $this->authorize('manage', Shipment::class);
        $validated = $request->validated();
        $items = null;
        if (isset($validated['items']) && is_array($validated['items'])) {
            $items = [];
            foreach ($validated['items'] as $item) {
                $items[] = new ShipmentItemQuantityData((string) $item['order_item_id'], (int) $item['quantity']);
            }
        }

        $data = new TransitionShipmentData(
            shipmentPublicId: $shipmentPublicId,
            expectedVersion: $request->expectedVersion(),
            actorUserId: (int) $request->user()?->getAuthIdentifier(),
            internalNote: $validated['internal_note'] ?? null,
            items: $items,
            trackingNumber: $validated['tracking_number'] ?? null,
            trackingUrl: $validated['tracking_url'] ?? null,
            carrierDisplayName: $validated['carrier_display_name'] ?? null,
            exceptionCode: isset($validated['exception_code'])
                ? ShipmentExceptionCode::from((string) $validated['exception_code'])
                : null,
            customerLocationLabel: $validated['location_label'] ?? null,
            idempotencyKey: $request->idempotencyKey(),
            requestId: $this->requestId($request),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            endpoint: 'admin.shipments.'.$method,
        );

        try {
            $record = $this->idempotency->begin(
                (int) $data->actorUserId,
                (string) $data->endpoint,
                $request->idempotencyKey(),
                $validated + ['shipment' => $shipmentPublicId, 'method' => $method],
            );
            $shipment = $this->transitions->{$method}($data);
        } catch (ShipmentIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode());
        }

        $body = $this->presenter->presentAdminShipment($shipment, $this->locale($request));
        $this->idempotency->complete($record, $body, 200);

        return $this->json($request, $body);
    }

    /**
     * @param  array{data: array<string, mixed>}  $payload
     */
    private function json(Request $request, array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, ['Cache-Control' => 'private, no-store'])
            ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }

    private function locale(Request $request): string
    {
        $header = strtolower((string) $request->header('X-Locale', $request->header('Accept-Language', 'ka')));

        return str_starts_with($header, 'en') ? 'en' : 'ka';
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
