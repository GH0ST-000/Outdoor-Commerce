<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Domains\Operations\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'actor_user_id' => $log->actor_user_id,
            'event' => $log->event->value,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'request_id' => $log->request_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
