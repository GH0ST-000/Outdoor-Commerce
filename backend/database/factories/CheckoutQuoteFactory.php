<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutQuote>
 */
class CheckoutQuoteFactory extends Factory
{
    protected $model = CheckoutQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'checkout_session_id' => CheckoutSession::factory(),
            'revision' => 1,
            'status' => CheckoutQuoteStatus::Active,
            'cart_version' => 1,
            'currency' => 'GEL',
            'items_subtotal_minor' => 10000,
            'discount_total_minor' => 0,
            'delivery_total_minor' => 500,
            'tax_total_minor' => 0,
            'grand_total_minor' => 10500,
            'price_includes_tax' => true,
            'quote_fingerprint' => hash('sha256', (string) Str::uuid()),
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
