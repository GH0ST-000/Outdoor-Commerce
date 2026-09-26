<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\AssignmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $variant_key
 * @property AssignmentType $assignment_type
 * @property AssignmentSourceType $source_type
 * @property AssignmentStatus $status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 */
class ProductContextAssignment extends Model
{
    protected $fillable = [
        'public_id',
        'product_id',
        'product_variant_id',
        'variant_key',
        'context_taxonomy_term_id',
        'assignment_type',
        'weight',
        'source_type',
        'source_reference_type',
        'source_reference_id',
        'status',
        'effective_from',
        'effective_until',
        'review_notes',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => AssignmentType::class,
            'source_type' => AssignmentSourceType::class,
            'status' => AssignmentStatus::class,
            'weight' => 'float',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(ContextTaxonomyTerm::class, 'context_taxonomy_term_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveNow(Builder $query, Carbon $at): Builder
    {
        return $query->where('status', AssignmentStatus::Active)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('effective_until')->orWhere('effective_until', '>=', $at);
            });
    }
}
