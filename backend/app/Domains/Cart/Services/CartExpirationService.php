<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Events\CartExpired;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Shared\Support\Clock;

final class CartExpirationService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly CartLogger $logger,
    ) {}

    /**
     * @return array{expired: int, skipped: int}
     */
    public function expireDueCarts(): array
    {
        $chunk = max(1, (int) config('cart.expire_chunk_size', 200));
        $now = $this->clock->now();
        $expired = 0;
        $skipped = 0;

        Cart::query()
            ->where('status', CartStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById($chunk, function ($carts) use (&$expired, &$skipped): void {
                foreach ($carts as $cart) {
                    if (! $cart instanceof Cart || $cart->status !== CartStatus::Active) {
                        $skipped++;

                        continue;
                    }

                    $cart->status = CartStatus::Expired;
                    $cart->guest_token_hash = null;
                    $cart->save();
                    $expired++;
                    event(new CartExpired($cart->public_id));
                }
            });

        $this->logger->info('expired', ['expired' => $expired, 'skipped' => $skipped]);

        return ['expired' => $expired, 'skipped' => $skipped];
    }
}
