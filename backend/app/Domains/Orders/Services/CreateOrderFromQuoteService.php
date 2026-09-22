<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Cart\Actions\MarkCartConvertedAction;
use App\Domains\Catalog\Actions\SnapshotPublicProductMediaAction;
use App\Domains\Checkout\Actions\ConsumeCheckoutQuoteAction;
use App\Domains\Checkout\Actions\ConvertCheckoutSessionAction;
use App\Domains\Checkout\Actions\LockCheckoutQuoteForOrderAction;
use App\Domains\Checkout\Actions\VerifyCheckoutQuoteIntegrityAction;
use App\Domains\Checkout\Enums\CheckoutQuoteAdjustmentType;
use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutRestrictionOutcome;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutQuoteLine;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\DTOs\OrderMutationResultData;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderAdjustmentType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderCreated;
use App\Domains\Orders\Events\OrderPendingPayment;
use App\Domains\Orders\Events\OrderReservationTransferred;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAdjustment;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Support\OrderDeadlockRetry;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Orders\Support\OrderNumberGenerator;
use App\Domains\Orders\Support\OrderTokenHasher;
use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrderFromQuoteService
{
    public function __construct(
        private readonly LockCheckoutQuoteForOrderAction $lockQuote,
        private readonly VerifyCheckoutQuoteIntegrityAction $verifyQuote,
        private readonly ConsumeCheckoutQuoteAction $consumeQuote,
        private readonly ConvertCheckoutSessionAction $convertSession,
        private readonly MarkCartConvertedAction $convertCart,
        private readonly CheckoutInventoryService $inventory,
        private readonly SnapshotPublicProductMediaAction $media,
        private readonly OrderStateMachine $states,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderTokenHasher $tokens,
        private readonly OrderDeadlockRetry $retry,
        private readonly Clock $clock,
        private readonly OrderLogger $logger,
    ) {}

    public function execute(
        OrderActorData $actor,
        string $sessionPublicId,
        string $quotePublicId,
    ): OrderMutationResultData {
        $started = microtime(true);
        $this->logger->info('creation_attempt', [
            'checkout_session_id' => $sessionPublicId,
            'quote_id' => $quotePublicId,
        ]);

        try {
            $result = $this->retry->run(
                fn (): OrderMutationResultData => $this->attempt($actor, $sessionPublicId, $quotePublicId, $started),
            );
        } catch (QueryException $exception) {
            if ($this->retry->isDeadlock($exception)) {
                $this->logger->warning('deadlock_retry', [
                    'checkout_session_id' => $sessionPublicId,
                    'quote_id' => $quotePublicId,
                ]);
            }
            throw $exception;
        }

        return $result;
    }

    private function attempt(
        OrderActorData $actor,
        string $sessionPublicId,
        string $quotePublicId,
        float $started,
    ): OrderMutationResultData {
        return DB::transaction(function () use ($actor, $sessionPublicId, $quotePublicId, $started): OrderMutationResultData {
            try {
                $locked = $this->lockQuote->execute($actor->checkout, $sessionPublicId, $quotePublicId);
            } catch (DomainException $exception) {
                throw OrderException::fromCheckoutFailure($exception);
            }

            $session = $locked['session'];
            $quote = $locked['quote'];

            $existing = Order::query()
                ->where('checkout_quote_id', $quote->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! $this->ownsExisting($existing, $actor, $session)) {
                    throw OrderException::notFound();
                }
                $this->logger->info('duplicate_quote_consumption', [
                    'order_public_id' => $existing->public_id,
                    'quote_public_id' => $quote->public_id,
                ]);

                return new OrderMutationResultData($existing, false);
            }

            $this->assertQuoteConsumable($actor, $session, $quote);
            $this->assertFinancialInvariant($quote);
            $this->assertRestrictions($quote);
            $reservations = $this->lockAndValidateReservations($quote);

            $now = $this->clock->now();
            $paymentTtl = max(1, (int) config('order.pending_payment_ttl_minutes', 20));
            $reservationExpiresAt = $now->addMinutes($paymentTtl);
            $media = $this->media->execute(
                $quote->lines->pluck('product_id')->map(static fn (mixed $id): int => (int) $id)->all(),
                $actor->locale(),
            );

            $order = $this->insertOrder($actor, $session, $quote, $now, $reservationExpiresAt);
            if (! $order->wasRecentlyCreated) {
                $this->logger->info('duplicate_quote_consumption', [
                    'order_public_id' => $order->public_id,
                    'quote_public_id' => $quote->public_id,
                ]);

                return new OrderMutationResultData($order, false);
            }

            $this->insertItems($order, $quote, $media);
            $this->insertAdjustments($order, $quote);
            $this->states->recordInitial(
                $order,
                OrderStatusReasonCode::OrderCreated,
                OrderActorType::Customer,
                $actor->userId(),
                ['quote_public_id' => $quote->public_id, 'quote_revision' => $quote->revision],
            );

            foreach ($reservations as $reservation) {
                $this->inventory->reassignReference(
                    $reservation,
                    'order',
                    $order->public_id,
                    $reservationExpiresAt,
                );
            }

            $this->consumeQuote->execute($quote);
            $this->convertSession->execute($session, $order->id);
            $this->convertCart->execute($session->cart_id, $order->id);

            $issuedToken = null;
            if ($actor->userId() === null) {
                $issuedToken = $this->tokens->generate();
                $order->access_token_hash = $this->tokens->hash($issuedToken);
                $order->save();
            }

            $duration = (int) round((microtime(true) - $started) * 1000);
            $quotePublic = $quote->public_id;
            $sessionPublic = $session->public_id;
            $reservationCount = count($reservations);

            DB::afterCommit(function () use ($order, $quotePublic, $sessionPublic, $reservationCount): void {
                event(new OrderCreated(
                    $order->public_id,
                    $order->order_number,
                    $quotePublic,
                    $sessionPublic,
                    $order->grand_total_minor,
                    $order->currency,
                    $order->user_id,
                ));
                event(new OrderPendingPayment(
                    $order->public_id,
                    $order->order_number,
                    $order->reservation_expires_at?->toIso8601String(),
                ));
                event(new OrderReservationTransferred($order->public_id, $quotePublic, $reservationCount));
            });

            $this->logger->info('creation_success', [
                'order_public_id' => $order->public_id,
                'quote_public_id' => $quote->public_id,
                'duration_ms' => $duration,
                'reservation_count' => $reservationCount,
            ]);

            $fresh = $order->fresh(['items', 'adjustments']) ?? $order;

            return new OrderMutationResultData($fresh, true, $issuedToken, false);
        });
    }

    private function ownsExisting(Order $order, OrderActorData $actor, CheckoutSession $session): bool
    {
        if ($actor->userId() !== null) {
            return $order->user_id === $actor->userId();
        }

        return $order->user_id === null && $session->id === $order->checkout_session_id;
    }

    private function assertQuoteConsumable(OrderActorData $actor, CheckoutSession $session, CheckoutQuote $quote): void
    {
        if ($session->status === CheckoutSessionStatus::Converted) {
            throw OrderException::checkoutAlreadyConverted();
        }
        if ($session->status === CheckoutSessionStatus::Expired) {
            throw OrderException::quoteExpired();
        }
        if ($quote->status === CheckoutQuoteStatus::Consumed) {
            throw OrderException::quoteAlreadyConsumed();
        }
        if ($quote->status === CheckoutQuoteStatus::Superseded || $session->current_quote_id !== $quote->id) {
            throw OrderException::quoteSuperseded();
        }
        if ($quote->status === CheckoutQuoteStatus::Expired || $quote->expires_at->lte($this->clock->now())) {
            throw OrderException::quoteExpired();
        }
        if ($quote->status === CheckoutQuoteStatus::Cancelled || $quote->status !== CheckoutQuoteStatus::Active) {
            throw OrderException::quoteNotFound();
        }
        if ($actor->checkout->expectedSessionVersion !== null
            && $actor->checkout->expectedSessionVersion !== $session->version) {
            throw OrderException::versionConflict([
                'checkout_session' => [
                    'id' => $session->public_id,
                    'status' => $session->status->value,
                    'version' => $session->version,
                ],
            ]);
        }

        try {
            $this->verifyQuote->execute($quote, $session);
        } catch (DomainException $exception) {
            $this->logger->warning('quote_integrity_failed', [
                'quote_public_id' => $quote->public_id,
            ]);
            throw OrderException::fromCheckoutFailure($exception);
        }

        $supported = strtoupper((string) config('order.currency', 'GEL'));
        if (strtoupper($quote->currency) !== $supported) {
            throw OrderException::financialInvariantFailed();
        }
    }

    private function assertFinancialInvariant(CheckoutQuote $quote): void
    {
        $lineSubtotal = (int) $quote->lines->sum('line_subtotal_minor');
        $lineDiscount = (int) $quote->lines->sum('line_discount_minor');
        $rounding = (int) $quote->adjustments
            ->where('type', CheckoutQuoteAdjustmentType::Rounding)
            ->sum('amount_minor');
        $expectedGrand = $quote->items_subtotal_minor
            - $quote->discount_total_minor
            + $quote->delivery_total_minor
            + $quote->tax_total_minor
            + $rounding;

        if ($lineSubtotal !== $quote->items_subtotal_minor
            || $lineDiscount !== $quote->discount_total_minor
            || $expectedGrand !== $quote->grand_total_minor
            || $quote->grand_total_minor < 0) {
            $this->logger->error('financial_invariant_failed', [
                'quote_public_id' => $quote->public_id,
            ]);
            throw OrderException::financialInvariantFailed();
        }
    }

    private function assertRestrictions(CheckoutQuote $quote): void
    {
        foreach ($quote->lines as $line) {
            $snapshot = $line->restriction_snapshot;
            $outcome = is_array($snapshot) ? ($snapshot['outcome'] ?? null) : null;
            if ($outcome === CheckoutRestrictionOutcome::CheckoutBlocked->value) {
                throw OrderException::restrictionBlocked([
                    'item_id' => $line->public_id,
                ]);
            }
        }
    }

    /**
     * @return list<InventoryReservation>
     */
    private function lockAndValidateReservations(CheckoutQuote $quote): array
    {
        $keys = $quote->lines->pluck('reservation_key')->filter()->values()->all();
        $query = InventoryReservation::query()
            ->where(function ($inner) use ($quote, $keys): void {
                $inner->where(function ($ref) use ($quote): void {
                    $ref->where('reference_type', 'checkout_quote')
                        ->where('reference_id', $quote->public_id);
                });
                if ($keys !== []) {
                    $inner->orWhereIn('reservation_key', $keys);
                }
            })
            ->orderBy('id')
            ->lockForUpdate();

        $byKey = $query->get()->keyBy('reservation_key');
        $locked = [];

        foreach ($quote->lines as $line) {
            $reservation = is_string($line->reservation_key) && $line->reservation_key !== ''
                ? $byKey->get($line->reservation_key)
                : $byKey->first(
                    fn (InventoryReservation $item): bool => $item->product_variant_id === $line->variant_id,
                );

            if (! $reservation instanceof InventoryReservation) {
                throw OrderException::reservationMissing();
            }
            if ($reservation->status !== InventoryReservationStatus::Active) {
                throw OrderException::reservationInvalid();
            }
            if ($reservation->expires_at !== null && $reservation->expires_at->lte($this->clock->now())) {
                throw OrderException::reservationExpired();
            }
            if ($reservation->quantity !== $line->quantity || $reservation->product_variant_id !== $line->variant_id) {
                throw OrderException::reservationInvalid();
            }

            $locked[] = $reservation;
        }

        return $locked;
    }

    private function insertOrder(
        OrderActorData $actor,
        CheckoutSession $session,
        CheckoutQuote $quote,
        CarbonImmutable $now,
        CarbonImmutable $reservationExpiresAt,
    ): Order {
        $session->loadMissing(['shippingAddress', 'billingAddress', 'pickupLocation', 'fulfillmentMethod']);
        $fulfillment = is_array($quote->fulfillment_snapshot) ? $quote->fulfillment_snapshot : [];
        $retries = max(1, (int) config('order.number_collision_retries', 8));
        $attempt = 0;

        while (true) {
            try {
                return Order::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'order_number' => $this->numbers->next(),
                    'user_id' => $actor->userId(),
                    'checkout_session_id' => $session->id,
                    'checkout_quote_id' => $quote->id,
                    'cart_id' => $session->cart_id,
                    'status' => OrderStatus::PendingPayment,
                    'payment_status' => PaymentStatus::Unpaid,
                    'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                    'currency' => $quote->currency,
                    'items_subtotal_minor' => $quote->items_subtotal_minor,
                    'discount_total_minor' => $quote->discount_total_minor,
                    'delivery_total_minor' => $quote->delivery_total_minor,
                    'tax_total_minor' => $quote->tax_total_minor,
                    'grand_total_minor' => $quote->grand_total_minor,
                    'price_includes_tax' => $quote->price_includes_tax,
                    'customer_email' => (string) $session->email,
                    'customer_phone' => (string) $session->phone,
                    'customer_first_name' => (string) $session->first_name,
                    'customer_last_name' => (string) $session->last_name,
                    'customer_note' => $session->customer_note,
                    'fulfillment_method_code' => (string) ($fulfillment['method_code'] ?? $session->fulfillmentMethod?->code),
                    'fulfillment_method_name' => (string) ($fulfillment['name'] ?? $session->fulfillmentMethod?->localizedName($actor->locale()) ?? ''),
                    'quote_revision' => $quote->revision,
                    'quote_fingerprint' => $quote->quote_fingerprint,
                    'contact_snapshot' => [
                        'first_name' => $session->first_name,
                        'last_name' => $session->last_name,
                        'email' => $session->email,
                        'phone' => $session->phone,
                        'customer_note' => $session->customer_note,
                    ],
                    'shipping_address_snapshot' => $session->shippingAddress?->publicSnapshot(),
                    'billing_address_snapshot' => $session->billing_same_as_shipping
                        ? null
                        : $session->billingAddress?->publicSnapshot(),
                    'fulfillment_snapshot' => [
                        'method_code' => $fulfillment['method_code'] ?? null,
                        'method_type' => $fulfillment['method_type'] ?? ($session->fulfillmentMethod !== null ? $session->fulfillmentMethod->type->value : null),
                        'localized_name' => $fulfillment['name'] ?? null,
                        'delivery_amount_minor' => $quote->delivery_total_minor,
                        'pickup_location' => $fulfillment['pickup_location'] ?? null,
                        'estimated_min_days' => $fulfillment['estimated_min_days'] ?? null,
                        'estimated_max_days' => $fulfillment['estimated_max_days'] ?? null,
                    ],
                    'reservation_expires_at' => $reservationExpiresAt,
                    'placed_at' => $now,
                    'version' => 1,
                ]);
            } catch (QueryException $exception) {
                if ($this->isOrderNumberCollision($exception) && $attempt < $retries) {
                    $attempt++;
                    $this->logger->warning('order_number_collision', [
                        'attempt' => $attempt,
                    ]);

                    continue;
                }
                if ($this->retry->isUniqueConstraint($exception) && str_contains(strtolower($exception->getMessage()), 'checkout_quote_id')) {
                    $existing = Order::query()->where('checkout_quote_id', $quote->id)->lockForUpdate()->first();
                    if ($existing !== null && $this->ownsExisting($existing, $actor, $session)) {
                        return $existing;
                    }
                }
                throw $exception;
            }
        }
    }

    /**
     * @param  array<int, array{url: string, alt: string|null}>  $media
     */
    private function insertItems(Order $order, CheckoutQuote $quote, array $media): void
    {
        $rows = [];
        $now = $this->clock->now();
        foreach ($quote->lines as $line) {
            $rows[] = [
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'sku' => $line->sku,
                'product_name' => $line->product_name,
                'variant_name' => $line->variant_label,
                'quantity' => $line->quantity,
                'unit_base_price_minor' => $line->unit_base_price_minor,
                'unit_effective_price_minor' => $line->unit_effective_price_minor,
                'unit_discount_minor' => $line->unit_discount_minor,
                'line_subtotal_minor' => $line->line_subtotal_minor,
                'line_discount_minor' => $line->line_discount_minor,
                'line_total_minor' => $line->line_total_minor,
                'currency' => $line->currency,
                'product_snapshot' => json_encode([
                    'name' => $line->product_name,
                    'slug' => $line->slug,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'variant_snapshot' => json_encode([
                    'name' => $line->variant_label,
                    'sku' => $line->sku,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'attribute_snapshot' => $line->attribute_snapshot !== null
                    ? json_encode($line->attribute_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : null,
                'promotion_snapshot' => $line->promotion_snapshot !== null
                    ? json_encode($line->promotion_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : null,
                'media_snapshot' => json_encode(
                    $media[$line->product_id] ?? $line->media_snapshot,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'restriction_snapshot' => $line->restriction_snapshot !== null
                    ? json_encode($line->restriction_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : null,
                'reservation_key' => $line->reservation_key,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            OrderItem::query()->insert($rows);
        }
    }

    private function insertAdjustments(Order $order, CheckoutQuote $quote): void
    {
        $items = $order->items()->get()->keyBy(fn (OrderItem $item): string => $item->sku.'|'.$item->variant_id);
        $now = $this->clock->now();
        $rows = [];

        foreach ($quote->adjustments as $adjustment) {
            $orderItemId = null;
            if ($adjustment->quote_line_id !== null) {
                $line = $quote->lines->firstWhere('id', $adjustment->quote_line_id);
                if ($line instanceof CheckoutQuoteLine) {
                    $match = $items->get($line->sku.'|'.$line->variant_id);
                    $orderItemId = $match instanceof OrderItem ? $match->id : null;
                }
            }

            $type = OrderAdjustmentType::tryFrom($adjustment->type->value) ?? OrderAdjustmentType::Manual;
            $rows[] = [
                'order_id' => $order->id,
                'order_item_id' => $orderItemId,
                'type' => $type->value,
                'code' => $adjustment->code,
                'label' => $adjustment->label,
                'amount_minor' => $adjustment->amount_minor,
                'metadata' => $adjustment->metadata !== null
                    ? json_encode($adjustment->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : null,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            OrderAdjustment::query()->insert($rows);
        }
    }

    private function isOrderNumberCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return $this->retry->isUniqueConstraint($exception) && str_contains($message, 'order_number');
    }
}
