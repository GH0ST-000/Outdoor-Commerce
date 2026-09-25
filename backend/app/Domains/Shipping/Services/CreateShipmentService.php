<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Shipping\DTOs\CreateProviderShipmentRequestData;
use App\Domains\Shipping\DTOs\CreateProviderShipmentResultData;
use App\Domains\Shipping\DTOs\CreateShipmentData;
use App\Domains\Shipping\DTOs\ShipmentItemQuantityData;
use App\Domains\Shipping\Enums\FulfillmentType;
use App\Domains\Shipping\Enums\ShipmentEventCode;
use App\Domains\Shipping\Enums\ShipmentEventSource;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Events\ShipmentCreated;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentItem;
use App\Domains\Shipping\Support\OperationalNoteSanitizer;
use App\Domains\Shipping\Support\ShipmentDeadlockRetry;
use App\Domains\Shipping\Support\ShipmentLogger;
use App\Domains\Shipping\Support\ShipmentNumberGenerator;
use App\Domains\Shipping\Support\TrackingNumberNormalizer;
use App\Domains\Shipping\Support\TrackingUrlValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateShipmentService
{
    public function __construct(
        private readonly FulfillmentEligibilityService $eligibility,
        private readonly FulfillmentAggregationService $aggregation,
        private readonly WarehouseAllocationService $warehouses,
        private readonly ShipmentProviderRegistry $providers,
        private readonly RecordShipmentEventService $events,
        private readonly ShipmentNumberGenerator $numbers,
        private readonly TrackingNumberNormalizer $trackingNumbers,
        private readonly TrackingUrlValidator $trackingUrls,
        private readonly OperationalNoteSanitizer $notes,
        private readonly RecordAuditEventAction $audit,
        private readonly ShipmentDeadlockRetry $retry,
        private readonly ShipmentLogger $logger,
    ) {}

    public function execute(CreateShipmentData $data): Shipment
    {
        $provider = $this->providers->resolve($data->providerCode);

        /** @var Shipment $shipment */
        $shipment = $this->retry->run(function () use ($data): Shipment {
            return DB::transaction(function () use ($data): Shipment {
                $order = Order::query()->where('public_id', $data->orderPublicId)->lockForUpdate()->first();
                if ($order === null) {
                    throw ShipmentException::orderNotEligible();
                }

                $this->eligibility->assertCanCreateShipment($order);
                $type = $data->fulfillmentType !== null
                    ? FulfillmentType::from($data->fulfillmentType)
                    : $this->eligibility->fulfillmentTypeForOrder($order);
                $this->eligibility->assertTypeCompatible($order, $type);

                $order->load(['items' => fn ($query) => $query->orderBy('id')->lockForUpdate()]);
                $requested = $this->mapRequestedItems($order, $data->items);
                if ($requested === []) {
                    throw ShipmentException::nothingRemaining();
                }

                $remaining = $this->aggregation->remainingByOrderItemId($order);
                foreach ($requested as $row) {
                    $left = $remaining[$row['item']->id] ?? 0;
                    if ($row['quantity'] > $left) {
                        $this->logger->warning('allocation_conflict', [
                            'order_public_id' => $order->public_id,
                            'order_item_public_id' => $row['item']->public_id,
                        ]);
                        throw ShipmentException::quantityExceeded();
                    }
                }

                $warehouse = $this->warehouses->resolve($order, $data->warehouseCode, $requested[0]['item']);
                $this->warehouses->assertItemsBelongToWarehouse(
                    $order,
                    $warehouse,
                    array_map(static fn (array $row): OrderItem => $row['item'], $requested),
                );

                if ($type === FulfillmentType::StorePickup) {
                    $pickup = $order->fulfillment_snapshot['pickup_location'] ?? null;
                    if (! is_array($pickup) || ! is_string($pickup['id'] ?? null)) {
                        throw ShipmentException::pickupLocationInvalid();
                    }
                }

                $publicId = (string) Str::uuid();
                $shipment = Shipment::query()->create([
                    'public_id' => $publicId,
                    'shipment_number' => $this->uniqueNumber(),
                    'order_id' => $order->id,
                    'warehouse_id' => $warehouse->id,
                    'fulfillment_type' => $type,
                    'provider_code' => $data->providerCode,
                    'status' => ShipmentStatus::Draft,
                    'carrier_display_name' => $this->safeName($data->carrierDisplayName),
                    'tracking_number' => $this->trackingNumbers->normalize($data->trackingNumber),
                    'public_tracking_url' => $this->trackingUrls->validate($data->trackingUrl),
                    'pickup_location_public_id' => is_array($order->fulfillment_snapshot['pickup_location'] ?? null)
                        ? ($order->fulfillment_snapshot['pickup_location']['id'] ?? null)
                        : null,
                    'recipient_snapshot' => is_array($order->contact_snapshot) ? $order->contact_snapshot : [],
                    'address_snapshot' => $order->shipping_address_snapshot,
                    'pickup_location_snapshot' => $order->fulfillment_snapshot['pickup_location'] ?? null,
                    'package_count' => max(1, $data->packageCount ?? 1),
                    'version' => 1,
                    'created_by' => $data->actorUserId,
                ]);

                foreach ($requested as $row) {
                    ShipmentItem::query()->create([
                        'shipment_id' => $shipment->id,
                        'order_item_id' => $row['item']->id,
                        'quantity' => $row['quantity'],
                    ]);
                }

                $this->events->record(
                    $shipment,
                    ShipmentStatus::Draft,
                    ShipmentEventCode::Created,
                    ShipmentEventSource::Admin,
                    'created',
                    $this->notes->sanitize($data->internalNote),
                    actorUserId: $data->actorUserId,
                );

                $this->aggregation->recalculate($order);
                $this->audit->execute(new AuditEventData(
                    event: AuditEvent::ShipmentCreated,
                    actorUserId: $data->actorUserId,
                    subjectType: 'shipment',
                    subjectId: $shipment->public_id,
                    requestId: $data->requestId,
                    ipAddress: $data->ipAddress,
                    userAgent: $data->userAgent,
                    newValues: [
                        'shipment_number' => $shipment->shipment_number,
                        'order_public_id' => $order->public_id,
                        'status' => $shipment->status->value,
                    ],
                ));

                $this->logger->info('created', [
                    'shipment_public_id' => $shipment->public_id,
                    'order_public_id' => $order->public_id,
                    'provider' => $shipment->provider_code,
                ]);

                return $shipment->fresh(['items.orderItem', 'events', 'order']) ?? $shipment;
            });
        });

        try {
            $this->attachProviderResult($shipment, $provider->createShipment(new CreateProviderShipmentRequestData(
                shipmentPublicId: $shipment->public_id,
                shipmentNumber: $shipment->shipment_number,
                orderPublicId: $data->orderPublicId,
                fulfillmentType: $shipment->fulfillment_type->value,
                items: $shipment->items->map(static function (ShipmentItem $item): array {
                    $orderItem = $item->orderItem;

                    return [
                        'sku' => (string) $orderItem->sku,
                        'name' => (string) $orderItem->product_name,
                        'quantity' => $item->quantity,
                    ];
                })->all(),
                trackingNumber: $shipment->tracking_number,
                trackingUrl: $shipment->public_tracking_url,
                carrierDisplayName: $shipment->carrier_display_name,
            )));
        } catch (ShipmentException $exception) {
            $this->logger->warning('provider_create_failed', [
                'shipment_public_id' => $shipment->public_id,
                'error_code' => $exception->errorCode(),
            ]);
        }

        event(new ShipmentCreated($shipment->public_id, $data->orderPublicId));

        return $shipment->fresh(['items.orderItem', 'events', 'order']) ?? $shipment;
    }

    /**
     * @param  list<ShipmentItemQuantityData>  $items
     * @return list<array{item: OrderItem, quantity: int}>
     */
    private function mapRequestedItems(Order $order, array $items): array
    {
        $mapped = [];
        foreach ($items as $request) {
            if ($request->quantity < 1) {
                throw ShipmentException::quantityInvalid();
            }
            $item = $order->items->firstWhere('public_id', $request->orderItemPublicId);
            if (! $item instanceof OrderItem) {
                throw ShipmentException::quantityInvalid();
            }
            $mapped[] = ['item' => $item, 'quantity' => $request->quantity];
        }

        usort($mapped, static fn (array $left, array $right): int => $left['item']->id <=> $right['item']->id);

        return $mapped;
    }

    private function uniqueNumber(): string
    {
        $retries = max(1, (int) config('shipping.number_collision_retries', 8));
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            $number = $this->numbers->next();
            if (! Shipment::query()->where('shipment_number', $number)->exists()) {
                return $number;
            }
        }

        throw ShipmentException::orderNotEligible();
    }

    private function safeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $trimmed = trim($name);
        if ($trimmed === '' || preg_match('/<[^>]+>/', $trimmed) === 1) {
            return null;
        }

        return mb_substr($trimmed, 0, 128);
    }

    private function attachProviderResult(Shipment $shipment, CreateProviderShipmentResultData $result): void
    {
        $shipment->provider_shipment_id = $result->providerShipmentId;
        if ($result->trackingNumber !== null) {
            $shipment->tracking_number = $result->trackingNumber;
        }
        if ($result->trackingUrl !== null) {
            $shipment->public_tracking_url = $result->trackingUrl;
        }
        if ($result->carrierDisplayName !== null) {
            $shipment->carrier_display_name = $result->carrierDisplayName;
        }
        $shipment->save();
    }
}
