<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Media;

use App\Domains\Catalog\Enums\MediaAttachmentRole;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Media\PrimaryMediaAttachmentService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a complete ordering.
 *
 * The caller must send every attachment id for the owner exactly once. A partial
 * list is rejected instead of being merged, because merging a stale client list
 * silently reorders images the admin never touched.
 */
final class ReorderMediaAction
{
    public function __construct(
        private readonly PrimaryMediaAttachmentService $primaries,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    /**
     * @param  list<int>  $orderedAttachmentIds
     * @return Collection<int, MediaAttachment>
     */
    public function execute(
        Model $owner,
        array $orderedAttachmentIds,
        Authenticatable $actor,
        MediaAttachmentRole $role = MediaAttachmentRole::Gallery,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Collection {
        $mediableType = $owner->getMorphClass();
        $mediableId = (int) $owner->getKey();
        $actorId = (int) $actor->getAuthIdentifier();

        $reordered = DB::transaction(function () use (
            $mediableType,
            $mediableId,
            $role,
            $orderedAttachmentIds,
            $actorId,
            $requestId,
            $ipAddress,
            $userAgent,
        ): Collection {
            $siblings = $this->primaries->lockSiblings($mediableType, $mediableId, $role);

            $existingIds = $siblings->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $submitted = array_map('intval', $orderedAttachmentIds);

            $this->assertCompleteOrdering($existingIds, $submitted);

            $previousOrder = $existingIds;

            foreach ($submitted as $position => $attachmentId) {
                /** @var MediaAttachment $attachment */
                $attachment = $siblings->firstWhere('id', $attachmentId);

                if ($attachment->sort_order !== $position) {
                    $attachment->sort_order = $position;
                    $attachment->updated_by = $actorId;
                    $attachment->save();
                }
            }

            // Ordering decides which attachment is eligible to be primary, so the
            // election runs again inside the same lock.
            $this->primaries->recalculate($mediableType, $mediableId, $role);

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaReordered,
                actorUserId: $actorId,
                subjectType: $mediableType,
                subjectId: (string) $mediableId,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['attachment_ids' => $previousOrder],
                newValues: ['attachment_ids' => $submitted],
            ));

            return MediaAttachment::query()
                ->where('mediable_type', $mediableType)
                ->where('mediable_id', $mediableId)
                ->where('role', $role->value)
                ->ordered()
                ->with(['asset.derivatives', 'translations'])
                ->get();
        });

        $this->catalogCache->bump();

        return $reordered;
    }

    /**
     * @param  list<int>  $existingIds
     * @param  list<int>  $submitted
     */
    private function assertCompleteOrdering(array $existingIds, array $submitted): void
    {
        if (count($submitted) !== count(array_unique($submitted))) {
            throw ValidationException::withMessages([
                'attachment_ids' => ['Duplicate attachment identifiers are not allowed.'],
            ]);
        }

        $missing = array_values(array_diff($existingIds, $submitted));
        $unknown = array_values(array_diff($submitted, $existingIds));

        if ($missing !== [] || $unknown !== []) {
            throw ValidationException::withMessages([
                'attachment_ids' => ['The ordering must list every attachment for this owner exactly once.'],
            ]);
        }
    }
}
