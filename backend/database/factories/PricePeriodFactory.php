<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricePeriod>
 */
final class PricePeriodFactory extends Factory
{
    protected $model = PricePeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->subDay();

        return [
            'variant_price_id' => VariantPrice::factory(),
            'amount_minor' => fake()->numberBetween(1000, 50000),
            'status' => PricePeriodStatus::Draft,
            'starts_at' => $start,
            'ends_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PricePeriodStatus::Published,
            'published_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
