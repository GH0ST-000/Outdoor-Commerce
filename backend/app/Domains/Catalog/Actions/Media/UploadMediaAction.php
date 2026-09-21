<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Media;

use App\Domains\Catalog\Enums\MediaAttachmentRole;
use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Media\MediaStorageService;
use App\Domains\Catalog\Services\Media\MediaUploadValidator;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Accepts an upload batch, stores the originals, and hands derivative generation
 * to the queue.
 *
 * Validation is atomic: if any file in the batch is rejected, nothing is stored
 * and nothing is created. Originals are written before the transaction opens so
 * the database is never held open across file I/O; a failure afterwards deletes
 * what was written, and anything that still slips through is reclaimed by
 * `media:cleanup-orphans`.
 */
final class UploadMediaAction
{
    public function __construct(
        private readonly MediaUploadValidator $validator,
        private readonly MediaStorageService $storage,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, MediaAttachment>
     */
    public function execute(
        Model $owner,
        array $files,
        Authenticatable $actor,
        MediaAttachmentRole $role = MediaAttachmentRole::Gallery,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Collection {
        $mediableType = $owner->getMorphClass();
        $mediableId = (int) $owner->getKey();

        $existingCount = MediaAttachment::query()
            ->where('mediable_type', $mediableType)
            ->where('mediable_id', $mediableId)
            ->where('role', $role->value)
            ->count();

        $galleryLimit = match ($mediableType) {
            'product' => (int) config('media.uploads.max_product_gallery', 20),
            'product_variant' => (int) config('media.uploads.max_variant_gallery', 10),
            default => (int) config('media.uploads.max_per_owner', 24),
        };

        $inspections = $this->validator->validate($files, $existingCount, $galleryLimit);

        $actorId = (int) $actor->getAuthIdentifier();
        $now = now();

        /** @var list<array{uuid: string, path: string}> $stored */
        $stored = [];

        try {
            foreach ($inspections as $index => $inspection) {
                $uuid = (string) Str::uuid();
                $stored[] = [
                    'uuid' => $uuid,
                    'path' => $this->storage->storeOriginal(
                        $files[$index],
                        $uuid,
                        $inspection->extension,
                        $now,
                    ),
                ];
            }

            $attachments = DB::transaction(function () use (
                $inspections,
                $stored,
                $mediableType,
                $mediableId,
                $role,
                $actorId,
                $now,
                $requestId,
                $ipAddress,
                $userAgent,
            ): Collection {
                $highestSortOrder = MediaAttachment::query()
                    ->where('mediable_type', $mediableType)
                    ->where('mediable_id', $mediableId)
                    ->where('role', $role->value)
                    ->max('sort_order');

                $nextSortOrder = $highestSortOrder === null ? 0 : ((int) $highestSortOrder) + 1;

                /** @var Collection<int, MediaAttachment> $created */
                $created = new Collection;

                foreach ($inspections as $index => $inspection) {
                    // `created_at` is pinned to the same instant used for the storage
                    // path so the Y/m folders always resolve back to the row.
                    $asset = new MediaAsset;
                    $asset->fill([
                        'uuid' => $stored[$index]['uuid'],
                        'status' => MediaStatus::Pending,
                        'original_disk' => MediaDisk::originals()->value,
                        'original_path' => $stored[$index]['path'],
                        'original_filename' => $inspection->sanitizedFilename,
                        'original_extension' => $inspection->extension,
                        'mime_type' => $inspection->mimeType,
                        'byte_size' => $inspection->byteSize,
                        'width' => $inspection->width,
                        'height' => $inspection->height,
                        'checksum_sha256' => $inspection->checksum,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                    $asset->created_at = $now;
                    $asset->updated_at = $now;
                    $asset->save();

                    $attachment = MediaAttachment::query()->create([
                        'media_asset_id' => $asset->id,
                        'mediable_type' => $mediableType,
                        'mediable_id' => $mediableId,
                        'role' => $role,
                        'sort_order' => $nextSortOrder + $index,
                        // Primary election waits for a ready asset; the job promotes
                        // the first eligible attachment once processing succeeds.
                        'is_primary' => false,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $created->push($attachment);

                    $this->recordAuditEvent->execute(new AuditEventData(
                        event: AuditEvent::MediaUploaded,
                        actorUserId: $actorId,
                        subjectType: 'media_asset',
                        subjectId: (string) $asset->id,
                        requestId: $requestId,
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                        newValues: [
                            'attachment_id' => $attachment->id,
                            'mediable_type' => $mediableType,
                            'mediable_id' => $mediableId,
                            'role' => $role->value,
                            'mime_type' => $inspection->mimeType,
                            'byte_size' => $inspection->byteSize,
                            'width' => $inspection->width,
                            'height' => $inspection->height,
                        ],
                    ));
                }

                return $created;
            });
        } catch (Throwable $exception) {
            foreach ($stored as $entry) {
                $this->storage->deleteRecordedPath(MediaDisk::originals()->value, $entry['path']);
            }

            throw $exception;
        }

        // Dispatched only once the rows are committed, otherwise a fast worker can
        // look for an asset that does not exist yet.
        foreach ($attachments as $attachment) {
            ProcessMediaAsset::dispatch($attachment->media_asset_id);
        }

        $this->catalogCache->bump();

        return $attachments->load(['asset.derivatives', 'translations']);
    }
}
