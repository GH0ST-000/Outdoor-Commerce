<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Address;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'phone' => '+995555123456',
            'country_code' => 'GE',
            'region' => 'Tbilisi',
            'city' => 'Tbilisi',
            'address_line_1' => 'Rustaveli Ave 1',
            'address_line_2' => null,
            'postal_code' => '0108',
            'is_default' => true,
        ];
    }

    public function defaultAddress(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => false,
            'label' => 'Other',
        ]);
    }
}
