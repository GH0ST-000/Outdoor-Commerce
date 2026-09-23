<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\Contracts\CartOwnerResolver;
use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Events\CartCreated;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Cart\Support\CartTokenHasher;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CartResolver implements CartOwnerResolver
{
    public function __construct(
        private readonly CartTokenHasher $tokens,
        private readonly Clock $clock,
        private readonly CartLogger $logger,
    ) {}

    public function hashGuestToken(?string $rawToken): ?string
    {
        if ($rawToken === null || $rawToken === '') {
            return null;
        }

        return $this->tokens->hash($rawToken);
    }

    public function findActive(CartActorData $actor): ?Cart
    {
        if ($actor->userId !== null) {
            return Cart::query()
                ->where('user_id', $actor->userId)
                ->where('status', CartStatus::Active)
                ->first();
        }

        $hash = $this->hashGuestToken($actor->rawGuestToken);
        if ($hash === null) {
            return null;
        }

        return Cart::query()
            ->where('guest_token_hash', $hash)
            ->where('status', CartStatus::Active)
            ->first();
    }

    /**
     * @return array{cart: Cart, issued_guest_token: string|null}
     */
    public function resolveOrCreate(CartActorData $actor): array
    {
        $existing = $this->findActive($actor);
        if ($existing !== null) {
            return ['cart' => $existing, 'issued_guest_token' => $actor->issuedGuestToken];
        }

        return DB::transaction(function () use ($actor): array {
            if ($actor->userId !== null) {
                $locked = Cart::query()
                    ->where('user_id', $actor->userId)
                    ->where('status', CartStatus::Active)
                    ->lockForUpdate()
                    ->first();

                if ($locked !== null) {
                    return ['cart' => $locked, 'issued_guest_token' => null];
                }

                $cart = $this->createCart($actor, null);

                return ['cart' => $cart, 'issued_guest_token' => null];
            }

            $raw = $actor->rawGuestToken;
            $issued = $actor->issuedGuestToken;
            if ($raw === null || $raw === '') {
                $raw = $this->tokens->generate();
                $issued = $raw;
            }

            $hash = $this->tokens->hash($raw);
            $locked = Cart::query()
                ->where('guest_token_hash', $hash)
                ->where('status', CartStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($locked !== null) {
                return ['cart' => $locked, 'issued_guest_token' => $issued];
            }

            $cart = $this->createCart($actor, $hash);

            return ['cart' => $cart, 'issued_guest_token' => $issued];
        });
    }

    public function lockActive(Cart $cart): Cart
    {
        /** @var Cart $locked */
        $locked = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    private function createCart(CartActorData $actor, ?string $guestHash): Cart
    {
        $now = $this->clock->now();
        $ttlDays = $actor->userId !== null
            ? (int) config('cart.authenticated_ttl_days', 90)
            : (int) config('cart.guest_ttl_days', 30);

        $cart = Cart::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $actor->userId,
            'guest_token_hash' => $guestHash,
            'status' => CartStatus::Active,
            'currency' => $actor->currency,
            'version' => 0,
            'item_count' => 0,
            'unique_item_count' => 0,
            'subtotal_minor' => 0,
            'discount_total_minor' => 0,
            'total_minor' => 0,
            'last_activity_at' => $now,
            'expires_at' => $now->addDays($ttlDays),
        ]);

        $this->logger->info('created', [
            'cart_public_id' => $cart->public_id,
            'user_id' => $actor->userId,
            'guest' => $guestHash !== null,
        ]);

        DB::afterCommit(function () use ($cart, $actor, $guestHash): void {
            event(new CartCreated($cart->public_id, $actor->userId, $guestHash !== null));
        });

        return $cart;
    }
}
