<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only editorial snapshot. Restoring creates a new revision.
 *
 * @property int $id
 * @property int $species_id
 * @property int $revision_number
 * @property int|null $actor_id
 * @property string $change_summary
 * @property Carbon|null $created_at
 */
class SpeciesRevision extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'revision_number',
        'actor_id',
        'change_summary',
        'snapshot',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
