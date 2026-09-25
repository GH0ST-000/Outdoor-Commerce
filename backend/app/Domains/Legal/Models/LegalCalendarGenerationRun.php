<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\CalendarGenerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Operational record of a projection generation run. Stack traces stay in logs, not here.
 *
 * @property int $id
 * @property CalendarGenerationStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class LegalCalendarGenerationRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'completed_at',
        'from_year',
        'through_year',
        'status',
        'definitions_processed',
        'occurrences_created',
        'occurrences_updated',
        'occurrences_invalidated',
        'failures',
        'triggered_by_type',
        'triggered_by_id',
        'error_summary',
        'jurisdiction_code',
        'dry_run',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CalendarGenerationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'dry_run' => 'boolean',
        ];
    }
}
