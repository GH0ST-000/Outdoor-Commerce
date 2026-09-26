<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Support\Facades\Request;

final class RecommendationAuditRecorder
{
    public function __construct(private readonly RecordAuditEventAction $audit) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(AuditEvent $event, ?User $actor, string $subjectType, string $subjectId, ?array $old = null, ?array $new = null, ?string $reason = null): void
    {
        $metadata = $reason === null ? null : ['reason' => $reason];
        $this->audit->execute(new AuditEventData(
            event: $event,
            actorUserId: $actor?->id,
            subjectType: $subjectType,
            subjectId: $subjectId,
            requestId: request()->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ipAddress: Request::ip(),
            userAgent: Request::userAgent(),
            oldValues: $old,
            newValues: $new,
            metadata: $metadata,
        ));
    }
}
