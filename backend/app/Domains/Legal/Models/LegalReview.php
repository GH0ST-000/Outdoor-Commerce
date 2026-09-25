<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalReviewDecision;
use App\Domains\Legal\Enums\LegalReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property LegalReviewType $review_type
 * @property LegalReviewDecision $decision
 */
class LegalReview extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reviewable_type',
        'reviewable_id',
        'review_type',
        'decision',
        'comments',
        'reviewer_id',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_type' => LegalReviewType::class,
            'decision' => LegalReviewDecision::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
