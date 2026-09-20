<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Models\User;
use Database\Factories\InventoryOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Immutable logical inventory command.
 *
 * @property int $id
 * @property string $uuid
 * @property InventoryOperationType $type
 * @property string $idempotency_key
 * @property string|null $payload_hash
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reason_code
 * @property string|null $note
 * @property int|null $performed_by
 * @property string|null $correlation_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
class InventoryOperation extends Model
{
    /** @use HasFactory<InventoryOperationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'idempotency_key',
        'payload_hash',
        'reference_type',
        'reference_id',
        'reason_code',
        'note',
        'performed_by',
        'correlation_id',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryOperationType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InventoryLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InventoryLedgerEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected static function newFactory(): InventoryOperationFactory
    {
        return InventoryOperationFactory::new();
    }
}
