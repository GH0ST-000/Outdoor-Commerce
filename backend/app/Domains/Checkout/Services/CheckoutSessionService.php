<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Cart\Contracts\CartOwnerResolver;
use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\DTOs\CheckoutAddressWriteData;
use App\Domains\Checkout\DTOs\CheckoutContactData;
use App\Domains\Checkout\DTOs\CheckoutMutationResultData;
use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Events\CheckoutAddressUpdated;
use App\Domains\Checkout\Events\CheckoutContactUpdated;
use App\Domains\Checkout\Events\CheckoutFulfillmentSelected;
use App\Domains\Checkout\Events\CheckoutSessionCancelled;
use App\Domains\Checkout\Events\CheckoutSessionCreated;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutAddress;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Support\CheckoutDeadlockRetry;
use App\Domains\Checkout\Support\CheckoutLogger;
use App\Domains\Checkout\Support\CheckoutPhoneNormalizer;
use App\Domains\Identity\Models\Address;
use App\Domains\Identity\Models\User;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CheckoutSessionService
{
    public function __construct(
        private readonly CartOwnerResolver $carts,
        private readonly CheckoutQuoteService $quotes,
        private readonly CheckoutFulfillmentService $fulfillment,
        private readonly CheckoutExpirationService $expiration,
        private readonly CheckoutReservationService $reservations,
        private readonly CheckoutPresenter $presenter,
        private readonly PublicCatalogPricing $pricing,
        private readonly CheckoutDeadlockRetry $retry,
        private readonly Clock $clock,
        private readonly CheckoutLogger $logger,
    ) {}

    public function create(CheckoutActorData $actor): CheckoutMutationResultData
    {
        return $this->retry->run(function () use ($actor): CheckoutMutationResultData {
            return DB::transaction(function () use ($actor): CheckoutMutationResultData {
                $cart = $this->requireCart($actor);
                $this->assertCartNotEmpty($cart);

                $existing = $this->findReusable($actor, $cart);
                if ($existing !== null) {
                    $session = $this->expiration->enforceSession($existing);

                    return new CheckoutMutationResultData($session, $session->currentQuote, $actor->cartActor->issuedGuestToken);
                }

                $this->assertSessionCap($actor);

                $ttlHours = max(1, (int) config('checkout.session_ttl_hours', 24));
                $now = $this->clock->now();
                $session = CheckoutSession::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $actor->userId(),
                    'cart_id' => $cart->id,
                    'guest_token_hash' => $cart->guest_token_hash ?? $this->carts->hashGuestToken($actor->cartActor->rawGuestToken),
                    'status' => CheckoutSessionStatus::Draft,
                    'version' => 0,
                    'currency' => $cart->currency,
                    'expires_at' => $now->addHours($ttlHours),
                    'last_activity_at' => $now,
                ]);

                if ($actor->userId() !== null) {
                    $user = User::query()->find($actor->userId());
                    if ($user instanceof User) {
                        $session->first_name = $user->first_name;
                        $session->last_name = $user->last_name;
                        $session->email = $user->email;
                        $session->phone = is_string($user->phone) && $user->phone !== ''
                            ? CheckoutPhoneNormalizer::normalize($user->phone)
                            : null;
                        if ($session->hasContact()) {
                            $session->status = CheckoutSessionStatus::Ready;
                        }
                        $session->save();
                    }
                }

                $this->logger->info('session_created', [
                    'session_public_id' => $session->public_id,
                    'user_id' => $actor->userId(),
                    'guest' => $actor->userId() === null,
                ]);

                DB::afterCommit(function () use ($session, $actor): void {
                    event(new CheckoutSessionCreated($session->public_id, $actor->userId(), $actor->userId() === null));
                });

                return new CheckoutMutationResultData($session, null, $actor->cartActor->issuedGuestToken);
            });
        });
    }

    public function get(CheckoutActorData $actor, string $publicId): CheckoutMutationResultData
    {
        $session = $this->ownedSession($actor, $publicId);
        $session = $this->expiration->enforceSession($session);

        return new CheckoutMutationResultData($session, $session->currentQuote);
    }

    public function updateContact(CheckoutActorData $actor, string $publicId, CheckoutContactData $contact): CheckoutMutationResultData
    {
        return $this->mutate($actor, $publicId, function (CheckoutSession $session) use ($contact): void {
            $session->first_name = $contact->firstName;
            $session->last_name = $contact->lastName;
            $session->email = $contact->email;
            $session->phone = $contact->phone;
            $session->customer_note = $contact->customerNote;
            $this->refreshStatus($session);
            $this->invalidateQuote($session, 'contact_updated');

            DB::afterCommit(function () use ($session): void {
                event(new CheckoutContactUpdated($session->public_id));
            });
        });
    }

    public function updateAddress(CheckoutActorData $actor, string $publicId, CheckoutAddressWriteData $address): CheckoutMutationResultData
    {
        return $this->mutate($actor, $publicId, function (CheckoutSession $session) use ($actor, $address): void {
            $stored = CheckoutAddress::query()->create([
                'kind' => 'shipping',
                'recipient_first_name' => $address->recipientFirstName,
                'recipient_last_name' => $address->recipientLastName,
                'phone' => $address->phone,
                'country_code' => strtoupper($address->countryCode),
                'region' => $address->region,
                'municipality_or_city' => $address->municipalityOrCity,
                'district' => $address->district,
                'street' => $address->street,
                'house_number' => $address->houseNumber,
                'apartment' => $address->apartment,
                'entrance' => $address->entrance,
                'floor' => $address->floor,
                'postal_code' => $address->postalCode,
                'landmark' => $address->landmark,
                'delivery_instructions' => $address->deliveryInstructions,
            ]);

            $session->shipping_address_id = $stored->id;
            $session->billing_same_as_shipping = $address->billingSameAsShipping;
            if ($address->billingSameAsShipping) {
                $session->billing_address_id = $stored->id;
            }

            if ($address->saveToAccount && $actor->userId() !== null) {
                $book = new Address([
                    'label' => 'checkout',
                    'recipient_name' => trim($address->recipientFirstName.' '.$address->recipientLastName),
                    'phone' => $address->phone,
                    'country_code' => strtoupper($address->countryCode),
                    'region' => (string) $address->region,
                    'city' => (string) $address->municipalityOrCity,
                    'address_line_1' => trim((string) $address->street.' '.(string) $address->houseNumber),
                    'address_line_2' => $address->apartment,
                    'postal_code' => (string) $address->postalCode,
                    'is_default' => false,
                ]);
                $book->user_id = $actor->userId();
                $book->save();
            }

            $session->load('shippingAddress', 'fulfillmentMethod', 'pickupLocation');
            $subtotal = $this->cartSubtotal($session, $actor);
            if ($session->fulfillment_method_id !== null && ! $this->fulfillment->methodStillEligible($session, $subtotal)) {
                $session->fulfillment_method_id = null;
                $session->pickup_location_id = null;
            }

            $this->refreshStatus($session);
            $this->invalidateQuote($session, 'address_updated');

            DB::afterCommit(function () use ($session): void {
                event(new CheckoutAddressUpdated($session->public_id));
            });
        });
    }

    public function updateFulfillment(
        CheckoutActorData $actor,
        string $publicId,
        string $methodCode,
        ?string $pickupLocationPublicId,
    ): CheckoutMutationResultData {
        return $this->mutate($actor, $publicId, function (CheckoutSession $session) use ($actor, $methodCode, $pickupLocationPublicId): void {
            $method = $this->fulfillment->requireMethod($methodCode);
            $pickup = $method->type === FulfillmentMethodType::StorePickup
                ? $this->fulfillment->requirePickup($pickupLocationPublicId)
                : null;

            if ($method->requires_address && $session->shippingAddress === null) {
                throw CheckoutException::addressIncomplete();
            }

            $session->fulfillment_method_id = $method->id;
            $session->pickup_location_id = $pickup?->id;
            $session->load('fulfillmentMethod', 'pickupLocation', 'shippingAddress');

            $subtotal = $this->cartSubtotal($session, $actor);
            $this->fulfillment->quoteSelected($session, $subtotal);
            $this->refreshStatus($session);
            $this->invalidateQuote($session, 'fulfillment_updated');

            DB::afterCommit(function () use ($session, $method): void {
                event(new CheckoutFulfillmentSelected($session->public_id, $method->code));
            });
        });
    }

    public function quote(CheckoutActorData $actor, string $publicId): CheckoutMutationResultData
    {
        return $this->retry->run(function () use ($actor, $publicId): CheckoutMutationResultData {
            return DB::transaction(function () use ($actor, $publicId): CheckoutMutationResultData {
                $session = $this->lockOwned($actor, $publicId);
                $this->assertMutable($session);
                $this->assertSessionVersion($session, $actor);
                $cart = $this->carts->lockActive($session->cart);
                $this->assertCartVersion($cart, $session, $actor);
                $this->assertCartNotEmpty($cart);
                $this->assertCartStillOwned($cart, $session);

                $quote = $this->quotes->create($session->fresh(['fulfillmentMethod', 'pickupLocation', 'shippingAddress']) ?? $session, $cart, $actor);

                return new CheckoutMutationResultData($session->fresh() ?? $session, $quote);
            });
        });
    }

    public function cancel(CheckoutActorData $actor, string $publicId): CheckoutMutationResultData
    {
        return $this->mutate($actor, $publicId, function (CheckoutSession $session): void {
            $session->loadMissing('quotes');
            foreach ($session->quotes as $quote) {
                if ($quote->status === CheckoutQuoteStatus::Active) {
                    $this->reservations->releaseQuote($quote, 'session_cancelled');
                    $quote->status = CheckoutQuoteStatus::Cancelled;
                    $quote->save();
                }
            }

            $session->status = CheckoutSessionStatus::Cancelled;

            DB::afterCommit(function () use ($session): void {
                event(new CheckoutSessionCancelled($session->public_id));
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CheckoutSession $session, CheckoutActorData $actor): array
    {
        $session->loadMissing([
            'cart',
            'fulfillmentMethod',
            'pickupLocation',
            'shippingAddress',
            'currentQuote.lines',
            'currentQuote.adjustments',
        ]);
        $subtotal = $this->cartSubtotal($session, $actor);
        $methods = $this->fulfillment->eligibleMethods($session, $subtotal, $actor->locale());

        return $this->presenter->envelope($session, $methods, $actor->locale(), (int) $session->cart->version);
    }

    /**
     * @param  callable(CheckoutSession): void  $callback
     */
    private function mutate(CheckoutActorData $actor, string $publicId, callable $callback): CheckoutMutationResultData
    {
        return $this->retry->run(function () use ($actor, $publicId, $callback): CheckoutMutationResultData {
            return DB::transaction(function () use ($actor, $publicId, $callback): CheckoutMutationResultData {
                $session = $this->lockOwned($actor, $publicId);
                $this->assertMutable($session);
                $this->assertSessionVersion($session, $actor);
                $callback($session);
                $session->version = $session->version + 1;
                $session->last_activity_at = $this->clock->now();
                $session->save();

                return new CheckoutMutationResultData($session->fresh() ?? $session, $session->currentQuote);
            });
        });
    }

    private function requireCart(CheckoutActorData $actor): Cart
    {
        $cart = $this->carts->findActive($actor->cartActor);
        if ($cart === null || $cart->status !== CartStatus::Active) {
            throw CheckoutException::cartEmpty();
        }

        return $this->carts->lockActive($cart);
    }

    private function assertCartNotEmpty(Cart $cart): void
    {
        $count = CartItem::query()->where('cart_id', $cart->id)->count();
        if ($count === 0) {
            throw CheckoutException::cartEmpty();
        }
    }

    private function findReusable(CheckoutActorData $actor, Cart $cart): ?CheckoutSession
    {
        $query = CheckoutSession::query()
            ->where('cart_id', $cart->id)
            ->whereIn('status', [
                CheckoutSessionStatus::Draft,
                CheckoutSessionStatus::Ready,
                CheckoutSessionStatus::Quoted,
            ])
            ->orderByDesc('id');

        if ($actor->userId() !== null) {
            $query->where('user_id', $actor->userId());
        } else {
            $hash = $this->carts->hashGuestToken($actor->cartActor->rawGuestToken);
            if ($hash === null) {
                return null;
            }
            $query->where('guest_token_hash', $hash);
        }

        return $query->first();
    }

    private function assertSessionCap(CheckoutActorData $actor): void
    {
        $max = max(1, (int) config('checkout.max_active_sessions_per_owner', 3));
        $query = CheckoutSession::query()->whereIn('status', [
            CheckoutSessionStatus::Draft,
            CheckoutSessionStatus::Ready,
            CheckoutSessionStatus::Quoted,
        ]);

        if ($actor->userId() !== null) {
            $query->where('user_id', $actor->userId());
        } else {
            $hash = $this->carts->hashGuestToken($actor->cartActor->rawGuestToken);
            if ($hash === null) {
                return;
            }
            $query->where('guest_token_hash', $hash);
        }

        if ($query->count() >= $max) {
            throw CheckoutException::notMutable();
        }
    }

    private function ownedSession(CheckoutActorData $actor, string $publicId): CheckoutSession
    {
        $session = CheckoutSession::query()->where('public_id', $publicId)->first();
        if ($session === null) {
            throw CheckoutException::sessionNotFound();
        }

        $this->assertOwnership($session, $actor);

        return $session;
    }

    private function lockOwned(CheckoutActorData $actor, string $publicId): CheckoutSession
    {
        $session = CheckoutSession::query()->where('public_id', $publicId)->lockForUpdate()->first();
        if ($session === null) {
            throw CheckoutException::sessionNotFound();
        }

        $this->assertOwnership($session, $actor);
        $session = $this->expiration->enforceSession($session);
        if ($session->status === CheckoutSessionStatus::Expired) {
            throw CheckoutException::sessionExpired();
        }

        return $session;
    }

    private function assertOwnership(CheckoutSession $session, CheckoutActorData $actor): void
    {
        if ($actor->userId() !== null) {
            if ($session->user_id !== $actor->userId()) {
                throw CheckoutException::sessionNotFound();
            }

            return;
        }

        $hash = $this->carts->hashGuestToken($actor->cartActor->rawGuestToken);
        if ($hash === null || $session->guest_token_hash !== $hash || $session->user_id !== null) {
            throw CheckoutException::sessionNotFound();
        }
    }

    private function assertMutable(CheckoutSession $session): void
    {
        if ($session->status === CheckoutSessionStatus::Converted) {
            throw CheckoutException::notMutable();
        }

        if ($session->status === CheckoutSessionStatus::Cancelled) {
            throw CheckoutException::notMutable();
        }

        if ($session->status === CheckoutSessionStatus::Expired) {
            throw CheckoutException::sessionExpired();
        }

        if (! $session->status->isMutable()) {
            throw CheckoutException::notMutable();
        }
    }

    private function assertSessionVersion(CheckoutSession $session, CheckoutActorData $actor): void
    {
        if ($actor->expectedSessionVersion === null) {
            return;
        }

        if ($actor->expectedSessionVersion !== $session->version) {
            throw CheckoutException::versionConflict([
                'checkout_session' => [
                    'id' => $session->public_id,
                    'status' => $session->status->value,
                    'version' => $session->version,
                ],
            ]);
        }
    }

    private function assertCartVersion(Cart $cart, CheckoutSession $session, CheckoutActorData $actor): void
    {
        if ($actor->expectedCartVersion === null) {
            return;
        }

        if ($actor->expectedCartVersion !== $cart->version) {
            throw CheckoutException::versionConflict([
                'checkout_session' => [
                    'id' => $session->public_id,
                    'status' => $session->status->value,
                    'version' => $session->version,
                ],
                'cart_version' => $cart->version,
            ]);
        }
    }

    private function assertCartStillOwned(Cart $cart, CheckoutSession $session): void
    {
        if ((int) $cart->id !== (int) $session->cart_id) {
            throw CheckoutException::cartChanged();
        }

        if ($cart->status !== CartStatus::Active) {
            throw CheckoutException::cartChanged();
        }
    }

    private function invalidateQuote(CheckoutSession $session, string $reason): void
    {
        $quote = $session->currentQuote;
        if (! $quote instanceof CheckoutQuote || $quote->status !== CheckoutQuoteStatus::Active) {
            return;
        }

        $this->reservations->releaseQuote($quote, $reason);
        $quote->status = CheckoutQuoteStatus::Superseded;
        $quote->superseded_at = $this->clock->now();
        $quote->save();
        $session->current_quote_id = null;
        unset($reason);
    }

    private function refreshStatus(CheckoutSession $session): void
    {
        if ($session->status === CheckoutSessionStatus::Quoted) {
            $session->status = $session->hasContact() ? CheckoutSessionStatus::Ready : CheckoutSessionStatus::Draft;

            return;
        }

        if ($session->hasContact()) {
            $session->status = CheckoutSessionStatus::Ready;
        }
    }

    private function cartSubtotal(CheckoutSession $session, CheckoutActorData $actor): int
    {
        $items = CartItem::query()->where('cart_id', $session->cart_id)->get();
        $variantIds = $items->pluck('variant_id')->map(static fn (mixed $id): int => (int) $id)->all();
        $quotes = $this->pricing->quoteVariants($variantIds, $actor->priceListId());
        $subtotal = 0;
        foreach ($items as $item) {
            $quote = $quotes[$item->variant_id] ?? null;
            if ($quote === null) {
                continue;
            }
            $subtotal += $quote->baseAmountMinor * $item->quantity;
        }

        return $subtotal;
    }
}
