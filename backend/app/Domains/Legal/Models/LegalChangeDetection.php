<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\ChangeDetectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_source_id
 * @property ChangeDetectionStatus $status
 * @property string $signal
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $detected_at
 */
class LegalChangeDetection extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = ['internal_notes'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_source_id',
        'legal_document_version_id',
        'status',
        'previous_metadata',
        'current_metadata',
        'signal',
        'internal_notes',
        'reviewed_by',
        'reviewed_at',
        'detected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChangeDetectionStatus::class,
            'previous_metadata' => 'array',
            'current_metadata' => 'array',
            'reviewed_at' => 'datetime',
            'detected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<LegalSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class, 'legal_source_id');
    }
}
