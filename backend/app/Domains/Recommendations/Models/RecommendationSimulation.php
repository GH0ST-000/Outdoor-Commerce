<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationSimulation extends Model
{
    protected $fillable = [
        'public_id',
        'recommendation_profile_id',
        'safe_context_snapshot',
        'result_summary',
        'executed_by',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'safe_context_snapshot' => 'array',
            'result_summary' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RecommendationProfile::class, 'recommendation_profile_id');
    }
}
