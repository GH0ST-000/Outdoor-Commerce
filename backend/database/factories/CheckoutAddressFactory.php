<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Models\CheckoutAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutAddress>
 */
class CheckoutAddressFactory extends Factory
{
    protected $model = CheckoutAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'shipping',
            'recipient_first_name' => 'ნინო',
            'recipient_last_name' => 'ბერიძე',
            'phone' => '+995555123456',
            'country_code' => 'GE',
            'region' => null,
            'municipality_or_city' => 'Tbilisi',
            'district' => null,
            'street' => 'Rustaveli',
            'house_number' => '10',
            'apartment' => null,
            'entrance' => null,
            'floor' => null,
            'postal_code' => '0108',
            'landmark' => null,
            'delivery_instructions' => null,
        ];
    }
}
