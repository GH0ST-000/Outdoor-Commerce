<?php

declare(strict_types=1);

namespace App\Domains\Operations\DTOs;

use App\Domains\Operations\Enums\AuditEvent;

final readonly class AuditEventData
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public AuditEvent $event,
        public ?int $actorUserId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $requestId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?array $metadata = null,
    ) {}
}
