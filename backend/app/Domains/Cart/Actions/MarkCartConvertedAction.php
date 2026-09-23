<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Models\Cart;
use App\Domains\Shared\Support\Clock;

final class MarkCartConvertedAction
{
    public function __construct(private readonly Clock $clock) {}

    public function execute(int $cartId, int $orderId): void
    {
        $cart = Cart::query()->whereKey($cartId)->lockForUpdate()->first();
        if ($cart === null) {
            return;
        }

        $cart->status = CartStatus::Converted;
        $cart->converted_order_id = $orderId;
        $cart->guest_token_hash = null;
        $cart->version = $cart->version + 1;
        $cart->last_activity_at = $this->clock->now();
        $cart->save();
    }
}
