<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\DTOs\Media\MediaProcessingResultData;
use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Exceptions\MediaProcessingException;
use App\Domains\Catalog\Exceptions\MediaSecurityException;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Media\ImageProcessor;
use App\Domains\Catalog\Services\Media\MediaStorageService;
use App\Domains\Catalog\Services\Media\MediaUploadValidator;
use App\Domains\Catalog\Services\Media\PrimaryMediaAttachmentService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Generates derivatives for one stored original.
 *
 * Idempotent by construction: an asset that is already `ready` returns
 * immediately, and a reprocess replaces the whole derivative row set and
 * overwrites the same deterministic paths. Combined with ShouldBeUnique on the
 * asset id, a duplicate dispatch is harmless.
 */
final class ProcessMediaAsset implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public readonly int $mediaAssetId,
    ) {
        $this->tries = (int) config('media.processing.tries', 3);
        $this->timeout = (int) config('media.processing.timeout', 120);
        $this->uniqueFor = (int) config('media.processing.unique_for', 900);
        $this->onQueue((string) config('media.queue', 'media'));
    }

    public function uniqueId(): string
    {
        return 'media-asset:'.$this->mediaAssetId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        /** @var list<int> $backoff */
        $backoff = config('media.processing.backoff', [10, 60, 300]);

        return $backoff;
    }

    public function handle(
        MediaStorageService $storage,
        MediaUploadValidator $validator,
        ImageProcessor $processor,
        PrimaryMediaAttachmentService $primaries,
        RecordAuditEventAction $recordAuditEvent,
        CatalogCache $catalogCache,
    ): void {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset === null || $asset->trashed()) {
            return;
        }

        if ($asset->status === MediaStatus::Ready) {
            return;
        }

        if ($asset->status === MediaStatus::Quarantined) {
            return;
        }

        $this->claim($asset);

        $temporaryPath = null;

        try {
            $temporaryPath = $storage->copyOriginalToTemporaryFile($asset);

            // Re-inspect the stored bytes: what passed validation at upload time is
            // not necessarily what is on disk now.
            try {
                $inspection = $validator->inspectPath($temporaryPath);
            } catch (ValidationException $exception) {
                throw new MediaSecurityException(
                    'content_rejected',
                    'The stored file failed the security re-check.',
                    json_encode($exception->errors()) ?: '',
                    $exception,
                );
            }

            $result = $processor->process(
                (string) file_get_contents($temporaryPath),
                $inspection->format,
            );

            $this->persist($asset, $result, $storage, $primaries, $recordAuditEvent);

            $catalogCache->bump();
        } catch (MediaSecurityException $exception) {
            // Security failures are terminal: never retried, never auto-published.
            $this->quarantine($asset, $exception, $recordAuditEvent);
        } catch (Throwable $exception) {
            $code = $exception instanceof MediaProcessingException ? $exception->failureCode : 'processing_error';
            $message = $exception instanceof MediaProcessingException
                ? $exception->safeMessage
                : 'Image processing failed. Please retry.';

            Log::warning('Media processing failed.', [
                'media_asset_id' => $asset->id,
                'failure_code' => $code,
                'attempt' => $this->attempts(),
                'exception' => $exception->getMessage(),
            ]);

            if (! $this->isFinalAttempt()) {
                throw $exception;
            }

            $this->markFailed($asset, $code, $message, $recordAuditEvent);
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * Terminal handler for the queued path, where the worker swallows the final
     * exception instead of returning to handle().
     */
    public function failed(?Throwable $exception): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset === null || $asset->status->isTerminal()) {
            return;
        }

        $asset->status = MediaStatus::Failed;
        $asset->failure_code ??= 'processing_error';
        $asset->failure_message ??= 'Image processing failed. Please retry.';
        $asset->save();

        app(RecordAuditEventAction::class)->execute(new AuditEventData(
            event: AuditEvent::MediaProcessingFailed,
            actorUserId: $asset->created_by,
            subjectType: 'media_asset',
            subjectId: (string) $asset->id,
            metadata: [
                'failure_code' => $asset->failure_code,
                'attempts' => $asset->attempts,
            ],
        ));
    }

    /**
     * Marks the asset as in-flight and increments the attempt counter. The guarded
     * update means a second worker that somehow got past the unique lock cannot
     * re-claim an asset another worker already finished.
     */
    private function claim(MediaAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            /** @var MediaAsset|null $locked */
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $locked->status = MediaStatus::Processing;
            $locked->attempts = $locked->attempts + 1;
            $locked->processing_started_at = now();
            $locked->failure_code = null;
            $locked->failure_message = null;
            $locked->save();
        });

        $asset->refresh();
    }

    private function persist(
        MediaAsset $asset,
        MediaProcessingResultData $result,
        MediaStorageService $storage,
        PrimaryMediaAttachmentService $primaries,
        RecordAuditEventAction $recordAuditEvent,
    ): void {
        $createdAt = $asset->created_at ?? now();
        $disk = MediaDisk::derivatives()->value;

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($result->renditions as $rendition) {
            $path = $storage->derivativePath($asset->uuid, $rendition->preset, $rendition->format, $createdAt);
            $storage->putDerivative($path, $rendition->contents);

            $rows[] = [
                'media_asset_id' => $asset->id,
                'preset' => $rendition->preset->value,
                'format' => $rendition->format->value,
                'disk' => $disk,
                'path' => $path,
                'width' => $rendition->width,
                'height' => $rendition->height,
                'byte_size' => $rendition->byteSize(),
            ];
        }

        DB::transaction(function () use ($asset, $result, $rows, $primaries, $recordAuditEvent): void {
            // Replacing the set keeps reprocessing idempotent even when the preset
            // or format configuration changed between runs.
            $asset->derivatives()->delete();
            $asset->derivatives()->createMany($rows);

            $asset->width = $result->width;
            $asset->height = $result->height;
            $asset->status = MediaStatus::Ready;
            $asset->processed_at = now();
            $asset->failure_code = null;
            $asset->failure_message = null;
            $asset->save();

            $this->refreshPrimaries($asset, $primaries);

            $recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaProcessingCompleted,
                actorUserId: $asset->created_by,
                subjectType: 'media_asset',
                subjectId: (string) $asset->id,
                newValues: [
                    'status' => MediaStatus::Ready->value,
                    'width' => $result->width,
                    'height' => $result->height,
                ],
                metadata: [
                    'derivatives' => count($rows),
                    'formats' => array_values(array_unique(array_map(
                        static fn (array $row): string => (string) $row['format'],
                        $rows,
                    ))),
                    'skipped_formats' => $result->skippedFormats,
                    'attempts' => $asset->attempts,
                ],
            ));
        });
    }

    /**
     * A newly ready asset may be the first eligible primary for its owners.
     */
    private function refreshPrimaries(MediaAsset $asset, PrimaryMediaAttachmentService $primaries): void
    {
        $owners = MediaAttachment::query()
            ->where('media_asset_id', $asset->id)
            ->get(['mediable_type', 'mediable_id', 'role'])
            ->unique(static fn (MediaAttachment $attachment): string => $attachment->mediable_type
                .':'.$attachment->mediable_id
                .':'.$attachment->role->value);

        foreach ($owners as $owner) {
            $hasPrimary = MediaAttachment::query()
                ->where('mediable_type', $owner->mediable_type)
                ->where('mediable_id', $owner->mediable_id)
                ->where('role', $owner->role->value)
                ->where('is_primary', true)
                ->exists();

            if ($hasPrimary) {
                continue;
            }

            $primaries->recalculate($owner->mediable_type, $owner->mediable_id, $owner->role);
        }
    }

    private function markFailed(
        MediaAsset $asset,
        string $code,
        string $safeMessage,
        RecordAuditEventAction $recordAuditEvent,
    ): void {
        DB::transaction(function () use ($asset, $code, $safeMessage, $recordAuditEvent): void {
            $asset->status = MediaStatus::Failed;
            $asset->failure_code = $code;
            $asset->failure_message = $safeMessage;
            $asset->save();

            $recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaProcessingFailed,
                actorUserId: $asset->created_by,
                subjectType: 'media_asset',
                subjectId: (string) $asset->id,
                metadata: [
                    'failure_code' => $code,
                    'attempts' => $asset->attempts,
                ],
            ));
        });
    }

    private function quarantine(
        MediaAsset $asset,
        MediaSecurityException $exception,
        RecordAuditEventAction $recordAuditEvent,
    ): void {
        Log::warning('Media asset quarantined.', [
            'media_asset_id' => $asset->id,
            'failure_code' => $exception->failureCode,
            'exception' => $exception->getMessage(),
        ]);

        DB::transaction(function () use ($asset, $exception, $recordAuditEvent): void {
            $asset->status = MediaStatus::Quarantined;
            $asset->failure_code = $exception->failureCode;
            $asset->failure_message = $exception->safeMessage;
            $asset->save();

            // Quarantined assets must never render, including as somebody's primary.
            MediaAttachment::query()
                ->where('media_asset_id', $asset->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaProcessingFailed,
                actorUserId: $asset->created_by,
                subjectType: 'media_asset',
                subjectId: (string) $asset->id,
                metadata: [
                    'failure_code' => $exception->failureCode,
                    'quarantined' => true,
                    'attempts' => $asset->attempts,
                ],
            ));
        });
    }

    /**
     * Synchronous execution has no retry machinery, so the first pass is also the
     * last and the asset must be marked failed rather than bubbling out silently.
     */
    private function isFinalAttempt(): bool
    {
        if ($this->job === null || $this->job instanceof SyncJob) {
            return true;
        }

        return $this->attempts() >= $this->tries;
    }
}
