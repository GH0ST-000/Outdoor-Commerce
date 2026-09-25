<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalConflictType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $first_rule_id
 * @property int $second_rule_id
 * @property LegalConflictType $conflict_type
 * @property LegalConflictSeverity $severity
 * @property LegalConflictStatus $status
 * @property Carbon|null $detected_at
 * @property Carbon|null $resolved_at
 */
class LegalConflict extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'first_rule_id',
        'second_rule_id',
        'conflict_type',
        'severity',
        'status',
        'detected_at',
        'detected_by',
        'evidence',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conflict_type' => LegalConflictType::class,
            'severity' => LegalConflictSeverity::class,
            'status' => LegalConflictStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    public function isBlocking(): bool
    {
        return $this->severity === LegalConflictSeverity::High
            && in_array($this->status, [LegalConflictStatus::Open, LegalConflictStatus::UnderReview], true);
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function firstRule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'first_rule_id');
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function secondRule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'second_rule_id');
    }
}
