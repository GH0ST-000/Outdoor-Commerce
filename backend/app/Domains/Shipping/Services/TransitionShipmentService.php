<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Orders\Models\Order;
use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\DTOs\TransitionShipmentData;
use App\Domains\Shipping\Enums\ShipmentEventCode;
use App\Domains\Shipping\Enums\ShipmentEventSource;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Events\PickupCollected;
use App\Domains\Shipping\Events\PickupReady;
use App\Domains\Shipping\Events\ShipmentCancelled;
use App\Domains\Shipping\Events\ShipmentDelivered;
use App\Domains\Shipping\Events\ShipmentDeliveryAttemptFailed;
use App\Domains\Shipping\Events\ShipmentDispatched;
use App\Domains\Shipping\Events\ShipmentExceptionRecorded;
use App\Domains\Shipping\Events\ShipmentInTransit;
use App\Domains\Shipping\Events\ShipmentItemsPacked;
use App\Domains\Shipping\Events\ShipmentItemsPicked;
use App\Domains\Shipping\Events\ShipmentOutForDelivery;
use App\Domains\Shipping\Events\ShipmentPreparationStarted;
use App\Domains\Shipping\Events\ShipmentReadyForDispatch;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentItem;
use App\Domains\Shipping\Support\OperationalNoteSanitizer;
use App\Domains\Shipping\Support\ShipmentDeadlockRetry;
use App\Domains\Shipping\Support\ShipmentLogger;
use App\Domains\Shipping\Support\TrackingNumberNormalizer;
use App\Domains\Shipping\Support\TrackingUrlValidator;
use Illuminate\Support\Facades\DB;

final class TransitionShipmentService
{
    public function __construct(
        private readonly ShipmentStateMachine $states,
        private readonly FulfillmentAggregationService $aggregation,
        private readonly RecordShipmentEventService $events,
        private readonly TrackingNumberNormalizer $trackingNumbers,
        private readonly TrackingUrlValidator $trackingUrls,
        private readonly OperationalNoteSanitizer $notes,
        private readonly RecordAuditEventAction $audit,
        private readonly ShipmentDeadlockRetry $retry,
        private readonly ShipmentLogger $logger,
        private readonly Clock $clock,
    ) {}

    public function startPreparation(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Preparing, ShipmentEventCode::PreparationStarted, 'preparation_started', AuditEvent::ShipmentPreparationStarted, ShipmentPreparationStarted::class);
    }

    public function markReadyForDispatch(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::ReadyForDispatch, ShipmentEventCode::ReadyForDispatch, 'ready_for_dispatch', AuditEvent::ShipmentReadyForDispatch, ShipmentReadyForDispatch::class, requirePacked: true);
    }

    public function dispatch(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Shipped, ShipmentEventCode::Dispatched, 'dispatched', AuditEvent::ShipmentDispatched, ShipmentDispatched::class, requirePacked: true, setShipped: true);
    }

    public function markInTransit(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::InTransit, ShipmentEventCode::InTransit, 'in_transit', AuditEvent::ShipmentInTransit, ShipmentInTransit::class);
    }

    public function markOutForDelivery(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::OutForDelivery, ShipmentEventCode::OutForDelivery, 'out_for_delivery', AuditEvent::ShipmentOutForDelivery, ShipmentOutForDelivery::class);
    }

    public function markDelivered(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Delivered, ShipmentEventCode::Delivered, 'delivered', AuditEvent::ShipmentDelivered, ShipmentDelivered::class, setDelivered: true);
    }

    public function recordDeliveryAttemptFailed(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::DeliveryAttemptFailed, ShipmentEventCode::DeliveryAttemptFailed, 'delivery_attempt_failed', AuditEvent::ShipmentDeliveryAttemptFailed, ShipmentDeliveryAttemptFailed::class);
    }

    public function markReadyForPickup(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::ReadyForPickup, ShipmentEventCode::ReadyForPickup, 'ready_for_pickup', AuditEvent::ShipmentReadyForPickup, PickupReady::class, requirePacked: true, setShipped: true);
    }

    public function markCollected(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Collected, ShipmentEventCode::Collected, 'collected', AuditEvent::ShipmentCollected, PickupCollected::class, setDelivered: true);
    }

    public function recordException(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Exception, ShipmentEventCode::ExceptionRecorded, 'exception', AuditEvent::ShipmentExceptionRecorded, ShipmentExceptionRecorded::class);
    }

    public function cancel(TransitionShipmentData $data): Shipment
    {
        return $this->transition($data, ShipmentStatus::Cancelled, ShipmentEventCode::Cancelled, 'cancelled', AuditEvent::ShipmentCancelled, ShipmentCancelled::class, cancelItems: true);
    }

    public function recordPicked(TransitionShipmentData $data): Shipment
    {
        return $this->mutateQuantities($data, 'picked', AuditEvent::ShipmentPicked, ShipmentItemsPicked::class);
    }

    public function recordPacked(TransitionShipmentData $data): Shipment
    {
        return $this->mutateQuantities($data, 'packed', AuditEvent::ShipmentPacked, ShipmentItemsPacked::class);
    }

    /**
     * @param  class-string  $eventClass
     */
    private function transition(
        TransitionShipmentData $data,
        ShipmentStatus $to,
        ShipmentEventCode $code,
        string $customerKey,
        AuditEvent $audit,
        string $eventClass,
        bool $requirePacked = false,
        bool $setShipped = false,
        bool $setDelivered = false,
        bool $cancelItems = false,
    ): Shipment {
        return $this->retry->run(function () use ($data, $to, $code, $customerKey, $audit, $eventClass, $requirePacked, $setShipped, $setDelivered, $cancelItems): Shipment {
            return DB::transaction(function () use ($data, $to, $code, $customerKey, $audit, $eventClass, $requirePacked, $setShipped, $setDelivered, $cancelItems): Shipment {
                $shipment = $this->lock($data->shipmentPublicId);
                $from = $shipment->status;

                if ($from === $to) {
                    return $shipment;
                }

                $this->assertVersion($shipment, $data->expectedVersion);
                $this->guardTerminal($from, $to);
                $this->states->assertStatusAllowedForType($shipment->fulfillment_type, $to);
                $this->states->assertTransition($shipment->fulfillment_type, $from, $to);

                if ($requirePacked) {
                    $this->assertFullyPacked($shipment);
                }
                if ($setShipped) {
                    $this->copyPackedToShipped($shipment);
                    $shipment->shipped_at = $shipment->shipped_at ?? $this->clock->now();
                }
                if ($setDelivered) {
                    $this->copyShippedToDelivered($shipment);
                    if ($to === ShipmentStatus::Collected) {
                        $shipment->collected_at = $this->clock->now();
                    } else {
                        $shipment->delivered_at = $this->clock->now();
                    }
                }
                if ($cancelItems) {
                    if ($from->hasLeftTheWarehouse() || $from->isTerminalSuccess()) {
                        throw ShipmentException::cancellationNotAllowed();
                    }
                    foreach ($shipment->items as $item) {
                        $item->cancelled_quantity = $item->quantity;
                        $item->save();
                    }
                    $shipment->cancelled_at = $this->clock->now();
                }

                if ($data->trackingNumber !== null) {
                    $shipment->tracking_number = $this->trackingNumbers->normalize($data->trackingNumber);
                }
                if ($data->trackingUrl !== null) {
                    $shipment->public_tracking_url = $this->trackingUrls->validate($data->trackingUrl);
                }
                if ($data->carrierDisplayName !== null && $data->carrierDisplayName !== '') {
                    $shipment->carrier_display_name = mb_substr(trim($data->carrierDisplayName), 0, 128);
                }
                if ($to === ShipmentStatus::Exception) {
                    $shipment->exception_code = $data->exceptionCode;
                }

                $shipment->status = $to;
                $shipment->version = $shipment->version + 1;
                $shipment->save();

                $this->events->record(
                    $shipment,
                    $to,
                    $code,
                    ShipmentEventSource::Admin,
                    $customerKey,
                    $this->notes->sanitize($data->internalNote),
                    $data->customerLocationLabel,
                    actorUserId: $data->actorUserId,
                    safeMetadata: $data->exceptionCode !== null ? ['exception_code' => $data->exceptionCode->value] : null,
                );

                $order = Order::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
                $this->aggregation->recalculate($order);
                $this->auditMutation($data, $audit, $shipment, $from, $to);
                $this->logger->info('transition', [
                    'shipment_public_id' => $shipment->public_id,
                    'from' => $from->value,
                    'to' => $to->value,
                ]);

                $this->dispatchDomainEvent($eventClass, $shipment, $order, $data);

                return $shipment->fresh(['items.orderItem', 'events', 'order']) ?? $shipment;
            });
        });
    }

    /**
     * @param  class-string  $eventClass
     */
    private function mutateQuantities(
        TransitionShipmentData $data,
        string $kind,
        AuditEvent $audit,
        string $eventClass,
    ): Shipment {
        return $this->retry->run(function () use ($data, $kind, $audit, $eventClass): Shipment {
            return DB::transaction(function () use ($data, $kind, $audit, $eventClass): Shipment {
                $shipment = $this->lock($data->shipmentPublicId);
                $this->assertVersion($shipment, $data->expectedVersion);
                if (! in_array($shipment->status, [ShipmentStatus::Draft, ShipmentStatus::Preparing], true)) {
                    throw ShipmentException::invalidTransition($shipment->status->value, $shipment->status->value);
                }

                if ($shipment->status === ShipmentStatus::Draft) {
                    $this->states->assertTransition($shipment->fulfillment_type, $shipment->status, ShipmentStatus::Preparing);
                    $shipment->status = ShipmentStatus::Preparing;
                }

                $this->applyItemQuantities($shipment, $data, $kind);
                $shipment->version = $shipment->version + 1;
                $shipment->save();

                $code = $kind === 'picked' ? ShipmentEventCode::ItemsPicked : ShipmentEventCode::ItemsPacked;
                $this->events->record(
                    $shipment,
                    $shipment->status,
                    $code,
                    ShipmentEventSource::Admin,
                    $kind === 'picked' ? 'picked' : 'packed',
                    $this->notes->sanitize($data->internalNote),
                    actorUserId: $data->actorUserId,
                );

                $order = Order::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
                $this->aggregation->recalculate($order);
                $this->auditMutation($data, $audit, $shipment, $shipment->status, $shipment->status);
                $this->dispatchDomainEvent($eventClass, $shipment, $order, $data);

                return $shipment->fresh(['items.orderItem', 'events', 'order']) ?? $shipment;
            });
        });
    }

    private function lock(string $publicId): Shipment
    {
        $shipment = Shipment::query()
            ->where('public_id', $publicId)
            ->with(['items.orderItem'])
            ->lockForUpdate()
            ->first();
        if ($shipment === null) {
            throw ShipmentException::notFound();
        }

        return $shipment;
    }

    private function assertVersion(Shipment $shipment, int $expected): void
    {
        if ($shipment->version !== $expected) {
            throw ShipmentException::versionConflict([
                'current_version' => $shipment->version,
                'shipment_id' => $shipment->public_id,
            ]);
        }
    }

    private function guardTerminal(ShipmentStatus $from, ShipmentStatus $to): void
    {
        if ($from === ShipmentStatus::Delivered) {
            throw ShipmentException::alreadyDelivered();
        }
        if ($from === ShipmentStatus::Collected) {
            throw ShipmentException::alreadyCollected();
        }
        if ($from->hasLeftTheWarehouse() && $to === ShipmentStatus::Cancelled) {
            throw ShipmentException::alreadyDispatched();
        }
    }

    private function assertFullyPacked(Shipment $shipment): void
    {
        foreach ($shipment->items as $item) {
            if ($item->packed_quantity !== $item->quantity) {
                throw ShipmentException::quantityInvalid();
            }
        }
    }

    private function copyPackedToShipped(Shipment $shipment): void
    {
        foreach ($shipment->items as $item) {
            if ($item->packed_quantity < $item->quantity) {
                throw ShipmentException::quantityInvalid();
            }
            $item->shipped_quantity = $item->packed_quantity;
            $item->save();
        }
    }

    private function copyShippedToDelivered(Shipment $shipment): void
    {
        foreach ($shipment->items as $item) {
            if ($item->shipped_quantity < $item->quantity) {
                throw ShipmentException::quantityInvalid();
            }
            if ($item->delivered_quantity > $item->shipped_quantity) {
                throw ShipmentException::quantityInvalid();
            }
            $item->delivered_quantity = $item->shipped_quantity;
            $item->save();
        }
    }

    private function applyItemQuantities(Shipment $shipment, TransitionShipmentData $data, string $kind): void
    {
        if ($data->items === null || $data->items === []) {
            foreach ($shipment->items as $item) {
                if ($kind === 'picked') {
                    $item->picked_quantity = $item->quantity;
                } else {
                    if ($item->picked_quantity < $item->quantity) {
                        throw ShipmentException::quantityInvalid();
                    }
                    $item->packed_quantity = $item->picked_quantity;
                }
                $item->save();
            }

            return;
        }

        foreach ($data->items as $request) {
            $item = $shipment->items->first(
                fn (ShipmentItem $row): bool => $row->orderItem?->public_id === $request->orderItemPublicId,
            );
            if (! $item instanceof ShipmentItem) {
                throw ShipmentException::quantityInvalid();
            }
            if ($request->quantity < 0 || $request->quantity > $item->quantity) {
                throw ShipmentException::quantityInvalid();
            }
            if ($kind === 'picked') {
                $item->picked_quantity = $request->quantity;
            } else {
                if ($request->quantity > $item->picked_quantity) {
                    throw ShipmentException::quantityInvalid();
                }
                $item->packed_quantity = $request->quantity;
            }
            $item->save();
        }
    }

    private function auditMutation(
        TransitionShipmentData $data,
        AuditEvent $event,
        Shipment $shipment,
        ShipmentStatus $from,
        ShipmentStatus $to,
    ): void {
        $this->audit->execute(new AuditEventData(
            event: $event,
            actorUserId: $data->actorUserId,
            subjectType: 'shipment',
            subjectId: $shipment->public_id,
            requestId: $data->requestId,
            ipAddress: $data->ipAddress,
            userAgent: $data->userAgent,
            oldValues: ['status' => $from->value],
            newValues: ['status' => $to->value, 'version' => $shipment->version],
        ));
    }

    /**
     * @param  class-string  $eventClass
     */
    private function dispatchDomainEvent(string $eventClass, Shipment $shipment, Order $order, TransitionShipmentData $data): void
    {
        $shipmentId = $shipment->public_id;
        $orderId = $order->public_id;
        $code = $data->exceptionCode !== null ? $data->exceptionCode->value : '';
        DB::afterCommit(function () use ($eventClass, $shipmentId, $orderId, $code): void {
            if ($eventClass === ShipmentExceptionRecorded::class) {
                event(new ShipmentExceptionRecorded($shipmentId, $orderId, $code));

                return;
            }
            event(new $eventClass($shipmentId, $orderId));
        });
    }
}
