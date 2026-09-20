<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Support\PriceListCodeNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
final class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => PriceListCodeNormalizer::normalize(fake()->unique()->bothify('list-####')),
            'name' => fake()->words(3, true),
            'currency_code' => 'GEL',
            'status' => PriceListStatus::Active,
            'is_default' => false,
            'priority' => 0,
            'prices_include_tax' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function draft(): static
    {
        return $this->state(['status' => PriceListStatus::Draft]);
    }
}
