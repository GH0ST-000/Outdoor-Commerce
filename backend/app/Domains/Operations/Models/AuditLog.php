<?php

declare(strict_types=1);

namespace App\Domains\Operations\Models;

use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only privileged-action audit trail.
 *
 * @property int $id
 * @property int|null $actor_user_id
 * @property AuditEvent $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $request_id
 * @property string|null $ip_address_hash
 * @property string|null $user_agent
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_user_id',
        'event',
        'subject_type',
        'subject_id',
        'request_id',
        'ip_address_hash',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Audit logs are append-only and cannot be deleted.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit logs are append-only and cannot be updated.');
    }
}
