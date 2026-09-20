<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtolower(fake()->unique()->bothify('promo-####')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => PromotionStatus::Draft,
            'discount_type' => DiscountType::Percentage,
            'percentage_basis_points' => 1000,
            'fixed_amount_minor' => null,
            'currency_code' => 'GEL',
            'priority' => 10,
            'stacking_mode' => PromotionStackingMode::Exclusive,
            'starts_at' => CarbonImmutable::now('UTC')->subHour(),
            'ends_at' => null,
            'maximum_discount_minor' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => PromotionStatus::Active,
            'published_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
