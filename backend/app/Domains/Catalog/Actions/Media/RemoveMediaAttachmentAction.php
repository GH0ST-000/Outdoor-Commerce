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

/**
 * Detaches an image from its owner.
 *
 * Files are deliberately left on disk: the same asset may be attached elsewhere,
 * and an accidental removal should be recoverable. `media:cleanup-orphans` deletes
 * the bytes once the asset has been unreferenced past the grace period.
 */
final class RemoveMediaAttachmentAction
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

        $removed = DB::transaction(function () use ($attachment, $actorId, $requestId, $ipAddress, $userAgent): MediaAttachment {
            $mediableType = $attachment->mediable_type;
            $mediableId = $attachment->mediable_id;
            $role = $attachment->role;
            $wasPrimary = $attachment->is_primary;

            $this->primaries->lockSiblings($mediableType, $mediableId, $role);

            $attachment->is_primary = false;
            $attachment->updated_by = $actorId;
            $attachment->save();
            $attachment->delete();

            // Promotes the first remaining ready image by sort order, then id.
            $replacement = $this->primaries->recalculate($mediableType, $mediableId, $role);

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaAttachmentRemoved,
                actorUserId: $actorId,
                subjectType: 'media_attachment',
                subjectId: (string) $attachment->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['is_primary' => $wasPrimary],
                metadata: [
                    'mediable_type' => $mediableType,
                    'mediable_id' => $mediableId,
                    'media_asset_id' => $attachment->media_asset_id,
                    'replacement_primary_attachment_id' => $replacement?->id,
                ],
            ));

            return $attachment;
        });

        $this->catalogCache->bump();

        return $removed;
    }
}
