<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use Database\Factories\CheckoutAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Checkout-lifecycle address snapshot. Not a customer address book row.
 *
 * @property int $id
 * @property string $kind
 * @property string $recipient_first_name
 * @property string $recipient_last_name
 * @property string $phone
 * @property string $country_code
 * @property string|null $region
 * @property string|null $municipality_or_city
 * @property string|null $district
 * @property string|null $street
 * @property string|null $house_number
 * @property string|null $apartment
 * @property string|null $entrance
 * @property string|null $floor
 * @property string|null $postal_code
 * @property string|null $landmark
 * @property string|null $delivery_instructions
 * @property string|null $latitude
 * @property string|null $longitude
 */
class CheckoutAddress extends Model
{
    /** @use HasFactory<CheckoutAddressFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'recipient_first_name',
        'recipient_last_name',
        'phone',
        'country_code',
        'region',
        'municipality_or_city',
        'district',
        'street',
        'house_number',
        'apartment',
        'entrance',
        'floor',
        'postal_code',
        'landmark',
        'delivery_instructions',
        'latitude',
        'longitude',
    ];

    /**
     * @return array<string, mixed>
     */
    public function publicSnapshot(): array
    {
        return [
            'recipient_first_name' => $this->recipient_first_name,
            'recipient_last_name' => $this->recipient_last_name,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'municipality_or_city' => $this->municipality_or_city,
            'district' => $this->district,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'apartment' => $this->apartment,
            'entrance' => $this->entrance,
            'floor' => $this->floor,
            'postal_code' => $this->postal_code,
            'landmark' => $this->landmark,
            'delivery_instructions' => $this->delivery_instructions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fingerprintPayload(): array
    {
        return [
            'country_code' => strtoupper((string) $this->country_code),
            'region' => mb_strtolower(trim((string) $this->region)),
            'municipality_or_city' => mb_strtolower(trim((string) $this->municipality_or_city)),
            'district' => mb_strtolower(trim((string) $this->district)),
            'street' => mb_strtolower(trim((string) $this->street)),
            'house_number' => mb_strtolower(trim((string) $this->house_number)),
            'apartment' => mb_strtolower(trim((string) $this->apartment)),
            'postal_code' => mb_strtolower(trim((string) $this->postal_code)),
        ];
    }

    protected static function newFactory(): CheckoutAddressFactory
    {
        return CheckoutAddressFactory::new();
    }
}
