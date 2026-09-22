<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variant = ProductVariant::factory()->active()->create();

        return [
            'public_id' => (string) Str::uuid(),
            'cart_id' => Cart::factory(),
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price_at_add_minor' => 10000,
            'discount_at_add_minor' => 0,
            'currency' => 'GEL',
            'sku_at_add' => $variant->sku,
            'added_at' => now(),
        ];
    }
}
