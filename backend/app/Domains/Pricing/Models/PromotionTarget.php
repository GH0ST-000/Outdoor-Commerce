<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Models;

use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promotion_id
 * @property PromotionTargetType $target_type
 * @property int|null $target_id
 * @property PromotionTargetMode $mode
 */
class PromotionTarget extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
        'mode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => PromotionTargetType::class,
            'mode' => PromotionTargetMode::class,
            'target_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
