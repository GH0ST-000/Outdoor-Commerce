<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Enums\RecommendationProfileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $public_id
 * @property string $slug
 * @property RecommendationPlacement $placement
 * @property int $version
 * @property RecommendationProfileStatus $status
 * @property int $minimum_score
 * @property int $maximum_results
 * @property int $candidate_limit
 * @property array<string, mixed> $configuration
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property int|null $created_by
 */
class RecommendationProfile extends Model
{
    protected $fillable = [
        'public_id', 'name', 'slug', 'placement', 'version', 'status',
        'minimum_score', 'maximum_results', 'candidate_limit', 'configuration',
        'effective_from', 'effective_until', 'created_by', 'reviewed_by', 'reviewed_at',
        'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'placement' => RecommendationPlacement::class,
            'status' => RecommendationProfileStatus::class,
            'configuration' => 'array',
            'minimum_score' => 'integer',
            'maximum_results' => 'integer',
            'candidate_limit' => 'integer',
            'version' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function weights(): HasMany
    {
        return $this->hasMany(RecommendationProfileWeight::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, int>
     */
    public function weightMap(): array
    {
        $this->loadMissing('weights');
        $map = [];
        foreach ($this->weights as $weight) {
            $map[$weight->dimension] = (int) $weight->weight;
        }

        return $map;
    }
}
