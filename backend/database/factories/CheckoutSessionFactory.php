<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'user_id' => null,
            'cart_id' => Cart::factory(),
            'guest_token_hash' => hash('sha256', (string) Str::uuid()),
            'status' => CheckoutSessionStatus::Draft,
            'version' => 0,
            'currency' => 'GEL',
            'expires_at' => now()->addHours(24),
            'last_activity_at' => now(),
        ];
    }
}
