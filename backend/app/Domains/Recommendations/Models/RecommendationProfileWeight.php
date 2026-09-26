<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $dimension
 * @property int $weight
 */
class RecommendationProfileWeight extends Model
{
    protected $fillable = ['recommendation_profile_id', 'dimension', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RecommendationProfile::class, 'recommendation_profile_id');
    }
}
