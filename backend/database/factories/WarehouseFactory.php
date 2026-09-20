<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Support\WarehouseCodeNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
final class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = WarehouseCodeNormalizer::normalize(fake()->unique()->bothify('WH-##??'));

        return [
            'code' => $code,
            'name' => fake()->company().' Warehouse',
            'status' => WarehouseStatus::Active,
            'is_default' => false,
            'country_code' => 'GE',
            'city' => fake()->city(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(41.0, 43.0),
            'longitude' => fake()->longitude(40.0, 46.0),
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => WarehouseStatus::Inactive]);
    }

    public function archived(): static
    {
        return $this->state(['status' => WarehouseStatus::Archived]);
    }
}
