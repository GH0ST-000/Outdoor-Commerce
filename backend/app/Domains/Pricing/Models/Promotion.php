<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Models;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PromotionStatus $status
 * @property DiscountType $discount_type
 * @property int|null $percentage_basis_points
 * @property int|null $fixed_amount_minor
 * @property string|null $currency_code
 * @property int $priority
 * @property PromotionStackingMode $stacking_mode
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'discount_type',
        'percentage_basis_points',
        'fixed_amount_minor',
        'currency_code',
        'priority',
        'stacking_mode',
        'starts_at',
        'ends_at',
        'maximum_discount_minor',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromotionStatus::class,
            'discount_type' => DiscountType::class,
            'stacking_mode' => PromotionStackingMode::class,
            'percentage_basis_points' => 'integer',
            'fixed_amount_minor' => 'integer',
            'maximum_discount_minor' => 'integer',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PromotionTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function isEffectiveAt(CarbonImmutable $at): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        $starts = CarbonImmutable::instance($this->starts_at)->utc();
        if ($starts->greaterThan($at)) {
            return false;
        }

        if ($this->ends_at === null) {
            return true;
        }

        return CarbonImmutable::instance($this->ends_at)->utc()->greaterThan($at);
    }

    /**
     * Admin presentation label derived from status + time.
     */
    public function lifecycleLabel(CarbonImmutable $at): string
    {
        return match ($this->status) {
            PromotionStatus::Draft => 'Draft',
            PromotionStatus::Paused => 'Paused',
            PromotionStatus::Archived => 'Archived',
            PromotionStatus::Active => $this->activeLifecycleLabel($at),
        };
    }

    private function activeLifecycleLabel(CarbonImmutable $at): string
    {
        $starts = CarbonImmutable::instance($this->starts_at)->utc();
        if ($starts->greaterThan($at)) {
            return 'Scheduled';
        }

        if ($this->ends_at !== null && ! CarbonImmutable::instance($this->ends_at)->utc()->greaterThan($at)) {
            return 'Expired';
        }

        return 'Live';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }
}
