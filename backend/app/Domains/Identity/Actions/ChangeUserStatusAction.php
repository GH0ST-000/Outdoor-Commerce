<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\LastActiveAdminGuard;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class ChangeUserStatusAction
{
    public function __construct(
        private readonly LastActiveAdminGuard $lastActiveAdminGuard,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        User $actor,
        User $target,
        UserStatus $status,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        return DB::transaction(function () use (
            $actor,
            $target,
            $status,
            $requestId,
            $ipAddress,
            $userAgent,
        ): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status;

            if ($oldStatus === $status) {
                return $locked;
            }

            if ($status === UserStatus::Disabled) {
                $this->lastActiveAdminGuard->assertCanLoseAdminAccess($locked);
            }

            $locked->status = $status;
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::UserStatusChanged,
                actorUserId: $actor->id,
                subjectType: 'user',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
            ));

            return $locked->fresh(['roles']) ?? $locked;
        });
    }
}
