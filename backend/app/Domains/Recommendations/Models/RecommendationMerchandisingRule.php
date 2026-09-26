<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Recommendations\Enums\MerchandisingAdjustmentType;
use App\Domains\Recommendations\Enums\MerchandisingRuleStatus;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MerchandisingAdjustmentType $adjustment_type
 * @property int $adjustment_value
 * @property int $priority
 * @property bool $paid_placement
 * @property MerchandisingRuleStatus $status
 */
class RecommendationMerchandisingRule extends Model
{
    protected $fillable = [
        'public_id', 'name', 'placement', 'product_id', 'product_category_id',
        'adjustment_type', 'adjustment_value', 'priority', 'status', 'paid_placement',
        'reason', 'starts_at', 'ends_at', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'placement' => RecommendationPlacement::class,
            'adjustment_type' => MerchandisingAdjustmentType::class,
            'status' => MerchandisingRuleStatus::class,
            'paid_placement' => 'boolean',
            'adjustment_value' => 'integer',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }
}
