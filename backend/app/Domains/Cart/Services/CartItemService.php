<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Events\CartItemAdded;
use App\Domains\Cart\Events\CartItemQuantityChanged;
use App\Domains\Cart\Events\CartItemRemoved;
use App\Domains\Cart\Exceptions\CartException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CartItemService
{
    public function __construct(
        private readonly CartValidationService $validation,
        private readonly Clock $clock,
    ) {}

    public function addOrIncrease(
        Cart $cart,
        ProductVariant $variant,
        int $quantity,
        PublicPriceQuoteData $quote,
        string $locale,
    ): CartItem {
        if ($cart->status !== CartStatus::Active) {
            throw CartException::notMutable();
        }

        $existing = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            $next = $existing->quantity + $quantity;
            $this->validation->assertQuantity($next);
            $this->validation->assertStockForIncrease((int) $variant->id, $next);
            $previous = $existing->quantity;
            $existing->quantity = $next;
            $existing->save();

            DB::afterCommit(function () use ($cart, $existing, $previous, $next): void {
                event(new CartItemQuantityChanged($cart->public_id, $existing->public_id, $previous, $next));
            });

            return $existing;
        }

        $maxLines = max(1, (int) config('cart.max_unique_lines', 50));
        $currentLines = CartItem::query()->where('cart_id', $cart->id)->count();
        if ($currentLines >= $maxLines) {
            throw CartException::tooManyLines();
        }

        $this->validation->assertQuantity($quantity);
        $this->validation->assertStockForIncrease((int) $variant->id, $quantity);

        $item = CartItem::query()->create([
            'public_id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price_at_add_minor' => $quote->finalAmountMinor,
            'discount_at_add_minor' => $quote->discountAmountMinor,
            'currency' => $quote->currencyCode,
            'sku_at_add' => $variant->sku,
            'product_name_at_add' => $variant->product?->translation($locale)?->name,
            'variant_name_at_add' => $variant->sku,
            'added_at' => $this->clock->now(),
        ]);

        DB::afterCommit(function () use ($cart, $item, $variant, $quantity): void {
            event(new CartItemAdded($cart->public_id, $item->public_id, (int) $variant->id, $quantity));
        });

        return $item;
    }

    public function setQuantity(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        if ($cart->status !== CartStatus::Active) {
            throw CartException::notMutable();
        }

        $this->validation->assertQuantity($quantity);
        if ($quantity > $item->quantity) {
            $this->validation->assertStockForIncrease((int) $item->variant_id, $quantity);
        }

        $previous = $item->quantity;
        $item->quantity = $quantity;
        $item->save();

        DB::afterCommit(function () use ($cart, $item, $previous, $quantity): void {
            event(new CartItemQuantityChanged($cart->public_id, $item->public_id, $previous, $quantity));
        });

        return $item;
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        if ($cart->status !== CartStatus::Active) {
            throw CartException::notMutable();
        }

        $publicId = $item->public_id;
        $variantId = (int) $item->variant_id;
        $item->delete();

        DB::afterCommit(function () use ($cart, $publicId, $variantId): void {
            event(new CartItemRemoved($cart->public_id, $publicId, $variantId));
        });
    }
}
