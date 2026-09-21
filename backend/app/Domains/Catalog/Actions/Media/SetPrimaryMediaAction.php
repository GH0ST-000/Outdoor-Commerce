<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Media;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Media\PrimaryMediaAttachmentService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class SetPrimaryMediaAction
{
    public function __construct(
        private readonly PrimaryMediaAttachmentService $primaries,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        MediaAttachment $attachment,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): MediaAttachment {
        $actorId = (int) $actor->getAuthIdentifier();

        $updated = DB::transaction(function () use ($attachment, $actorId, $requestId, $ipAddress, $userAgent): MediaAttachment {
            $previousId = MediaAttachment::query()
                ->where('mediable_type', $attachment->mediable_type)
                ->where('mediable_id', $attachment->mediable_id)
                ->where('role', $attachment->role->value)
                ->where('is_primary', true)
                ->value('id');

            $target = $this->primaries->setPrimary($attachment);
            $target->updated_by = $actorId;
            $target->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaPrimaryChanged,
                actorUserId: $actorId,
                subjectType: $attachment->mediable_type,
                subjectId: (string) $attachment->mediable_id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['primary_attachment_id' => $previousId !== null ? (int) $previousId : null],
                newValues: ['primary_attachment_id' => $target->id],
            ));

            return $target;
        });

        $this->catalogCache->bump();

        return $updated->load(['asset.derivatives', 'translations']);
    }
}
