<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Models;

use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PricePeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $variant_price_id
 * @property int $amount_minor
 * @property PricePeriodStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class PricePeriod extends Model
{
    /** @use HasFactory<PricePeriodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'variant_price_id',
        'amount_minor',
        'status',
        'starts_at',
        'ends_at',
        'published_at',
        'cancelled_at',
        'created_by',
        'published_by',
        'cancelled_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PricePeriodStatus::class,
            'amount_minor' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VariantPrice, $this>
     */
    public function variantPrice(): BelongsTo
    {
        return $this->belongsTo(VariantPrice::class);
    }

    /**
     * Inclusive start, exclusive end.
     */
    public function isEffectiveAt(CarbonImmutable $at): bool
    {
        if ($this->status !== PricePeriodStatus::Published) {
            return false;
        }

        $starts = CarbonImmutable::instance($this->starts_at)->utc();
        if ($starts->greaterThan($at)) {
            return false;
        }

        if ($this->ends_at === null) {
            return true;
        }

        $ends = CarbonImmutable::instance($this->ends_at)->utc();

        return $ends->greaterThan($at);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): PricePeriodFactory
    {
        return PricePeriodFactory::new();
    }
}
