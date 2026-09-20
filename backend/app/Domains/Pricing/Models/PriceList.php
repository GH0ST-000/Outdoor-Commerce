<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Models;

use App\Domains\Pricing\Enums\PriceListStatus;
use App\Models\User;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $currency_code
 * @property PriceListStatus $status
 * @property bool $is_default
 * @property int $priority
 * @property bool $prices_include_tax
 * @property-read int|null $priced_variant_count
 */
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'status',
        'is_default',
        'priority',
        'prices_include_tax',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceListStatus::class,
            'is_default' => 'boolean',
            'prices_include_tax' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return HasMany<VariantPrice, $this>
     */
    public function variantPrices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function newFactory(): PriceListFactory
    {
        return PriceListFactory::new();
    }
}
