<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalRuleType;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use Database\Factories\LegalRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string $slug
 * @property LegalActivityType $activity_type
 * @property LegalRuleType $rule_type
 * @property LegalRuleEffect $effect
 * @property int|null $species_id
 * @property string $jurisdiction_code
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property LegalRuleStatus $status
 * @property LegalVerificationLevel $verification_level
 * @property string|null $interpretation_summary
 * @property string|null $internal_notes
 * @property int $content_version
 * @property int|null $created_by
 * @property int|null $published_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 */
class LegalRule extends Model
{
    /** @use HasFactory<LegalRuleFactory> */
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
        'title',
        'slug',
        'activity_type',
        'rule_type',
        'effect',
        'species_id',
        'jurisdiction_code',
        'region_code',
        'zone_reference',
        'effective_from',
        'effective_until',
        'priority',
        'status',
        'verification_level',
        'interpretation_summary',
        'public_notes',
        'internal_notes',
        'created_by',
        'updated_by',
        'reviewed_by',
        'reviewed_at',
        'published_by',
        'published_at',
        'supersedes_rule_id',
        'content_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => LegalActivityType::class,
            'rule_type' => LegalRuleType::class,
            'effect' => LegalRuleEffect::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'priority' => 'integer',
            'status' => LegalRuleStatus::class,
            'verification_level' => LegalVerificationLevel::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'content_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalRuleFactory
    {
        return LegalRuleFactory::new();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', LegalRuleStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === LegalRuleStatus::Published;
    }

    public function isEffectiveAt(Carbon $at): bool
    {
        if ($this->effective_from->gt($at)) {
            return false;
        }

        return $this->effective_until === null || $this->effective_until->gte($at);
    }

    /**
     * @return HasMany<LegalRuleCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(LegalRuleCitation::class);
    }

    /**
     * @return HasMany<LegalRuleCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(LegalRuleCondition::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<LegalRuleLimit, $this>
     */
    public function limits(): HasMany
    {
        return $this->hasMany(LegalRuleLimit::class);
    }

    /**
     * @return HasMany<LegalRuleException, $this>
     */
    public function exceptionsFrom(): HasMany
    {
        return $this->hasMany(LegalRuleException::class, 'base_rule_id');
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersededRule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_rule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
