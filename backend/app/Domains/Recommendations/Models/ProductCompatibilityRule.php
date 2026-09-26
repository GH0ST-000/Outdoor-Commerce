<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\CompatibilityOperator;
use App\Domains\Recommendations\Enums\CompatibilityRuleType;
use App\Domains\Recommendations\Enums\CompatibilityValueType;
use App\Domains\Recommendations\Enums\ContextDimension;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CompatibilityRuleType $rule_type
 * @property ContextDimension $context_dimension
 * @property CompatibilityOperator $operator
 * @property string|null $string_value
 * @property int|null $integer_value
 * @property float|null $decimal_value
 * @property bool|null $boolean_value
 * @property int $weight
 * @property int|null $product_id
 * @property int|null $product_category_id
 */
class ProductCompatibilityRule extends Model
{
    protected $fillable = [
        'public_id', 'name', 'product_id', 'product_variant_id', 'product_category_id',
        'rule_type', 'context_dimension', 'operator', 'context_term_id', 'value_type',
        'string_value', 'integer_value', 'decimal_value', 'boolean_value', 'unit_code',
        'priority', 'weight', 'status', 'effective_from', 'effective_until',
        'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => CompatibilityRuleType::class,
            'context_dimension' => ContextDimension::class,
            'operator' => CompatibilityOperator::class,
            'value_type' => CompatibilityValueType::class,
            'boolean_value' => 'boolean',
            'integer_value' => 'integer',
            'decimal_value' => 'float',
            'weight' => 'integer',
            'priority' => 'integer',
            'status' => AssignmentStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
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
