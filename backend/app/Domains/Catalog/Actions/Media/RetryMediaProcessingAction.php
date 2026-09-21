<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Media;

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Requeues a failed asset.
 *
 * Quarantined assets are not retryable: they failed a security check, and the fix
 * is uploading a different file, not asking the same bytes to pass twice.
 */
final class RetryMediaProcessingAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        MediaAsset $asset,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): MediaAsset {
        if ($asset->status === MediaStatus::Quarantined) {
            throw ValidationException::withMessages([
                'asset' => ['A quarantined image cannot be reprocessed. Upload a replacement instead.'],
            ]);
        }

        if ($asset->status === MediaStatus::Ready) {
            throw ValidationException::withMessages([
                'asset' => ['This image has already been processed.'],
            ]);
        }

        $actorId = (int) $actor->getAuthIdentifier();

        $updated = DB::transaction(function () use ($asset, $actorId, $requestId, $ipAddress, $userAgent): MediaAsset {
            /** @var MediaAsset $locked */
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $previousStatus = $locked->status;

            $locked->status = MediaStatus::Pending;
            $locked->failure_code = null;
            $locked->failure_message = null;
            $locked->processing_started_at = null;
            $locked->updated_by = $actorId;
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaRetryRequested,
                actorUserId: $actorId,
                subjectType: 'media_asset',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $previousStatus->value],
                newValues: ['status' => MediaStatus::Pending->value],
                metadata: ['attempts' => $locked->attempts],
            ));

            return $locked;
        });

        ProcessMediaAsset::dispatch($updated->id);

        return $updated->refresh();
    }
}
