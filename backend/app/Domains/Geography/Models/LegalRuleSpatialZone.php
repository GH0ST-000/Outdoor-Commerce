<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialAssignmentStatus;
use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Models\LegalRule;
use Database\Factories\LegalRuleSpatialZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_rule_id
 * @property int $spatial_zone_id
 * @property int|null $zone_geometry_version_id
 * @property SpatialAssignmentType $assignment_type
 * @property int $precedence
 * @property SpatialAssignmentStatus $status
 * @property-read LegalRule|null $rule
 * @property-read SpatialZone|null $zone
 */
class LegalRuleSpatialZone extends Model
{
    /** @use HasFactory<LegalRuleSpatialZoneFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['review_notes'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_rule_id',
        'spatial_zone_id',
        'zone_geometry_version_id',
        'assignment_type',
        'precedence',
        'effective_from',
        'effective_until',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignment_type' => SpatialAssignmentType::class,
            'status' => SpatialAssignmentStatus::class,
            'precedence' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            if ($assignment->public_id === null || $assignment->public_id === '') {
                $assignment->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishedEffective(Builder $query, Carbon $at): Builder
    {
        return $query
            ->where('status', SpatialAssignmentStatus::Published)
            ->where('effective_from', '<=', $at)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $at);
            });
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'legal_rule_id');
    }

    /**
     * @return BelongsTo<SpatialZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SpatialZone::class, 'spatial_zone_id');
    }

    /**
     * @return BelongsTo<SpatialZoneGeometryVersion, $this>
     */
    public function geometryVersion(): BelongsTo
    {
        return $this->belongsTo(SpatialZoneGeometryVersion::class, 'zone_geometry_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function newFactory(): LegalRuleSpatialZoneFactory
    {
        return LegalRuleSpatialZoneFactory::new();
    }
}
