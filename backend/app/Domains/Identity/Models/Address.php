<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Shipping / billing address book entry owned by a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $recipient_name
 * @property string $phone
 * @property string $country_code
 * @property string $region
 * @property string $city
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $postal_code
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $table = 'addresses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'recipient_name',
        'phone',
        'country_code',
        'region',
        'city',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
}
