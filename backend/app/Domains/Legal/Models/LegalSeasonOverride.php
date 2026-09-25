<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\SeasonOverrideType;
use Database\Factories\LegalSeasonOverrideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Source-backed override that cannot silently rewrite the parent definition.
 *
 * @property int $id
 * @property string $public_id
 * @property int $base_season_definition_id
 * @property int $legal_rule_id
 * @property SeasonOverrideType $override_type
 * @property Carbon $starts_at
 * @property Carbon $ends_at_exclusive
 * @property int $precedence
 * @property LegalRuleStatus $status
 * @property LegalVerificationLevel $verification_level
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 */
class LegalSeasonOverride extends Model
{
    /** @use HasFactory<LegalSeasonOverrideFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['internal_notes'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'base_season_definition_id',
        'legal_rule_id',
        'override_type',
        'starts_at',
        'ends_at_exclusive',
        'jurisdiction_code',
        'region_code',
        'zone_reference',
        'reason',
        'precedence',
        'status',
        'verification_level',
        'internal_notes',
        'created_by',
        'updated_by',
        'reviewed_by',
        'reviewed_at',
        'published_by',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $override): void {
            if ($override->public_id === null || $override->public_id === '') {
                $override->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'override_type' => SeasonOverrideType::class,
            'status' => LegalRuleStatus::class,
            'verification_level' => LegalVerificationLevel::class,
            'starts_at' => 'datetime',
            'ends_at_exclusive' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LegalSeasonDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(LegalSeasonDefinition::class, 'base_season_definition_id');
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'legal_rule_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', LegalRuleStatus::Published);
    }

    protected static function newFactory(): LegalSeasonOverrideFactory
    {
        return LegalSeasonOverrideFactory::new();
    }
}
