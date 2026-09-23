<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Models\Cart;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'user_id' => null,
            'guest_token_hash' => hash('sha256', (string) Str::uuid()),
            'status' => CartStatus::Active,
            'currency' => 'GEL',
            'version' => 1,
            'item_count' => 0,
            'unique_item_count' => 0,
            'subtotal_minor' => 0,
            'discount_total_minor' => 0,
            'total_minor' => 0,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'guest_token_hash' => null,
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function merged(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Merged,
        ]);
    }
}
