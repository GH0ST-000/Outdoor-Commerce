<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Actions\ApplyOrderPaymentStartedAction;
use App\Domains\Orders\Actions\AssertOrderAccessAction;
use App\Domains\Orders\Actions\ExpireOrderIfDueAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\CreatePaymentAttemptData;
use App\Domains\Payments\DTOs\CreateProviderPaymentRequestData;
use App\Domains\Payments\DTOs\ProviderPaymentBasketItemData;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Events\PaymentAttemptCreated;
use App\Domains\Payments\Events\PaymentRequiresAction;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Exceptions\PaymentProviderTimeoutException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentDeadlockRetry;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePaymentAttemptService
{
    public function __construct(
        private readonly AssertOrderAccessAction $assertAccess,
        private readonly ExpireOrderIfDueAction $expireOrder,
        private readonly ApplyOrderPaymentStartedAction $startPayment,
        private readonly PaymentMethodRegistry $methods,
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentStateMachine $states,
        private readonly ApplyPaymentFailureService $failure,
        private readonly PaymentDeadlockRetry $retry,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function execute(OrderActorData $actor, CreatePaymentAttemptData $data): PaymentAttempt
    {
        $attempt = $this->retry->run(function () use ($actor, $data): PaymentAttempt {
            return DB::transaction(function () use ($actor, $data): PaymentAttempt {
                $order = Order::query()->where('public_id', $data->orderPublicId)->lockForUpdate()->first();
                if ($order === null) {
                    throw PaymentException::orderNotFound();
                }

                $this->assertAccess->execute($order, $actor);
                $this->expireOrder->execute($order, true);
                $order->refresh();

                if ($data->orderVersion !== null && $data->orderVersion !== $order->version) {
                    throw PaymentException::versionConflict();
                }

                $this->assertPayable($order);
                $this->assertReservations($order);

                $method = $this->methods->requireEligible($order, $data->paymentMethodCode, $actor->locale());

                $succeeded = PaymentAttempt::query()
                    ->where('order_id', $order->id)
                    ->where('status', PaymentAttemptStatus::Succeeded->value)
                    ->lockForUpdate()
                    ->first();
                if ($succeeded !== null) {
                    throw PaymentException::alreadyPaid();
                }

                $active = PaymentAttempt::query()
                    ->where('order_id', $order->id)
                    ->whereIn('status', [
                        PaymentAttemptStatus::Created->value,
                        PaymentAttemptStatus::Pending->value,
                        PaymentAttemptStatus::RequiresAction->value,
                        PaymentAttemptStatus::Processing->value,
                        PaymentAttemptStatus::Unknown->value,
                    ])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($active !== null) {
                    if ($active->payment_method_code === $method->code) {
                        return $active;
                    }
                    throw PaymentException::activeAttemptExists();
                }

                $key = (string) $actor->idempotencyKey;
                $fingerprint = hash('sha256', $order->public_id.'|'.$method->code.'|'.$order->grand_total_minor.'|'.$order->currency);

                $attempt = PaymentAttempt::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'provider' => $method->provider,
                    'payment_method_code' => $method->code,
                    'status' => PaymentAttemptStatus::Created,
                    'amount_minor' => $order->grand_total_minor,
                    'currency' => $order->currency,
                    'idempotency_key_hash' => hash('sha256', $key),
                    'request_fingerprint' => $fingerprint,
                    'version' => 1,
                ]);

                $this->states->recordInitial($attempt, 'created');
                $this->startPayment->execute($order);

                $this->logger->info('attempt_created', [
                    'payment_attempt_public_id' => $attempt->public_id,
                    'order_public_id' => $order->public_id,
                    'provider' => $method->provider,
                ]);

                $publicId = $attempt->public_id;
                $orderPublic = $order->public_id;
                $provider = $method->provider;
                $methodCode = $method->code;
                DB::afterCommit(function () use ($publicId, $orderPublic, $provider, $methodCode): void {
                    event(new PaymentAttemptCreated($publicId, $orderPublic, $provider, $methodCode));
                });

                return $attempt;
            });
        });

        if ($attempt->provider_payment_id !== null || $attempt->status !== PaymentAttemptStatus::Created) {
            return $attempt;
        }

        return $this->callProvider($attempt, $actor);
    }

    private function callProvider(PaymentAttempt $attempt, OrderActorData $actor): PaymentAttempt
    {
        $order = $attempt->order()->with('items')->firstOrFail();
        $provider = $this->providers->resolve($attempt->provider);
        $started = microtime(true);

        $returnUrl = rtrim((string) config('payments.return_url'), '/').'?order_id='.$order->public_id;
        $failureUrl = $returnUrl;
        $callbackUrl = rtrim((string) config('payments.app_url'), '/').'/api/v1/payments/webhooks/'.$attempt->provider;
        if ($attempt->provider === 'bog') {
            $configuredSuccess = (string) config('payments.providers.bog.success_url', '');
            $configuredFail = (string) config('payments.providers.bog.fail_url', '');
            $configuredCallback = (string) config('payments.providers.bog.callback_url', '');
            if ($configuredSuccess !== '') {
                $returnUrl = $configuredSuccess.(str_contains($configuredSuccess, '?') ? '&' : '?').'order_id='.$order->public_id;
            }
            if ($configuredFail !== '') {
                $failureUrl = $configuredFail.(str_contains($configuredFail, '?') ? '&' : '?').'order_id='.$order->public_id;
            }
            if ($configuredCallback !== '') {
                $callbackUrl = $configuredCallback;
            }
        }

        try {
            $request = new CreateProviderPaymentRequestData(
                merchantReference: $attempt->public_id,
                amountMinor: $attempt->amount_minor,
                currency: $attempt->currency,
                description: $order->order_number,
                returnUrl: $returnUrl,
                callbackUrl: $callbackUrl,
                customerLocale: $actor->locale(),
                customerEmail: null,
                customerPhone: null,
                metadata: ['order_public_id' => $order->public_id],
                basketItems: $this->basketItems($order),
                deliveryAmountMinor: $order->delivery_total_minor,
                discountTotalMinor: $order->discount_total_minor,
                reservationExpiresAt: $order->reservation_expires_at,
                providerIdempotencyKey: $attempt->public_id,
                failureReturnUrl: $failureUrl,
            );
            $result = $provider->createPayment($request);
        } catch (PaymentProviderTimeoutException $exception) {
            $this->logger->warning('provider_timeout', [
                'payment_attempt_public_id' => $attempt->public_id,
                'order_public_id' => $order->public_id,
                'provider' => $attempt->provider,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            return $this->retry->run(function () use ($attempt): PaymentAttempt {
                return DB::transaction(function () use ($attempt): PaymentAttempt {
                    $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                    $this->states->transition($locked, PaymentAttemptStatus::Unknown, 'provider_timeout');

                    return $locked;
                });
            });
        } catch (PaymentException $exception) {
            $this->retry->run(function () use ($attempt, $exception): void {
                DB::transaction(function () use ($attempt, $exception): void {
                    $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                    $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                    $category = $exception->errorCode() === 'PAYMENT_PROVIDER_UNAVAILABLE'
                        ? PaymentFailureCategory::ProviderUnavailable
                        : PaymentFailureCategory::ValidationFailed;
                    $this->failure->execute(
                        $locked,
                        $order,
                        PaymentAttemptStatus::Failed,
                        $category,
                        $exception->errorCode(),
                    );
                });
            });

            throw $exception;
        }

        $this->logger->info('provider_create', [
            'payment_attempt_public_id' => $attempt->public_id,
            'order_public_id' => $order->public_id,
            'provider' => $attempt->provider,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'normalized_status' => $result->normalizedStatus->value,
        ]);

        return $this->retry->run(function () use ($attempt, $result): PaymentAttempt {
            return DB::transaction(function () use ($attempt, $result): PaymentAttempt {
                $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

                $locked->provider_payment_id = $result->providerPaymentId;
                $locked->provider_transaction_id = $result->providerTransactionId;
                $locked->provider_status = $result->providerStatus;
                $locked->action_type = $result->action;
                $locked->redirect_url_encrypted = $result->redirectUrl;
                $locked->provider_expires_at = $result->expiresAt;
                $locked->last_provider_sync_at = $this->clock->now();
                $locked->save();

                $this->states->transition($locked, $result->normalizedStatus, 'provider_create');

                $publicId = $locked->public_id;
                $orderPublic = $order->public_id;
                $action = $locked->action_type->value;
                if ($locked->status === PaymentAttemptStatus::RequiresAction) {
                    DB::afterCommit(function () use ($publicId, $orderPublic, $action): void {
                        event(new PaymentRequiresAction($publicId, $orderPublic, $action));
                    });
                }

                return $locked;
            });
        });
    }

    private function assertPayable(Order $order): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            throw PaymentException::alreadyPaid();
        }
        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentProcessing], true)) {
            if ($order->status === OrderStatus::Expired || $order->status === OrderStatus::Cancelled) {
                throw PaymentException::paymentWindowExpired();
            }
            throw PaymentException::orderNotPayable();
        }
        if ($order->reservation_expires_at !== null && $order->reservation_expires_at->lte($this->clock->now())) {
            throw PaymentException::paymentWindowExpired();
        }
    }

    private function assertReservations(Order $order): void
    {
        $active = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->where('status', InventoryReservationStatus::Active)
            ->count();

        if ($active < 1) {
            throw PaymentException::reservationMissing();
        }
    }

    /**
     * @return list<ProviderPaymentBasketItemData>
     */
    private function basketItems(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $unit = $item->unit_effective_price_minor;
            if ($item->quantity > 0 && $item->line_total_minor === ($unit * $item->quantity)) {
                $unitPrice = $unit;
                $unitDiscount = 0;
            } elseif ($item->quantity > 0 && ($item->line_total_minor % $item->quantity) === 0) {
                $unitPrice = intdiv($item->line_total_minor, $item->quantity);
                $unitDiscount = 0;
            } else {
                $this->logger->error('basket_line_unreconcilable', [
                    'order_public_id' => $order->public_id,
                    'item_public_id' => $item->public_id,
                ]);
                throw PaymentException::basketMismatch();
            }

            $media = $item->media_snapshot;
            $image = is_array($media) && is_string($media['url'] ?? null) ? $media['url'] : null;

            $items[] = new ProviderPaymentBasketItemData(
                productId: $item->public_id,
                description: trim($item->product_name.' '.$item->variant_name),
                quantity: $item->quantity,
                unitPriceMinor: $unitPrice,
                unitDiscountMinor: $unitDiscount,
                lineTotalMinor: $item->line_total_minor,
                imageUrl: $image,
            );
        }

        return $items;
    }
}
