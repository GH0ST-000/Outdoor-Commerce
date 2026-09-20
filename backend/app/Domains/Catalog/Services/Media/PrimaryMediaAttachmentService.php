<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaAttachmentRole;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Guards the one-primary-per-owner invariant.
 *
 * Every mutation here expects to run inside a transaction and takes a row lock on
 * the owner's attachments first, so two concurrent "make this primary" requests
 * serialize instead of both winning.
 */
final class PrimaryMediaAttachmentService
{
    /**
     * @return Collection<int, MediaAttachment>
     */
    public function lockSiblings(
        string $mediableType,
        int $mediableId,
        MediaAttachmentRole $role = MediaAttachmentRole::Gallery,
    ): Collection {
        return MediaAttachment::query()
            ->where('mediable_type', $mediableType)
            ->where('mediable_id', $mediableId)
            ->where('role', $role->value)
            ->lockForUpdate()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Promotes one attachment and demotes the rest. The target must be processed:
     * a pending or failed image would render as a broken primary.
     *
     * @throws ValidationException
     */
    public function setPrimary(MediaAttachment $attachment): MediaAttachment
    {
        $siblings = $this->lockSiblings(
            $attachment->mediable_type,
            $attachment->mediable_id,
            $attachment->role,
        );

        $target = $siblings->firstWhere('id', $attachment->id) ?? $attachment;

        if (! $this->isReady($target)) {
            throw ValidationException::withMessages([
                'attachment' => ['Only a processed image can be the primary image.'],
            ]);
        }

        foreach ($siblings as $sibling) {
            $shouldBePrimary = $sibling->id === $target->id;

            if ($sibling->is_primary !== $shouldBePrimary) {
                $sibling->is_primary = $shouldBePrimary;
                $sibling->save();
            }
        }

        $target->is_primary = true;

        return $target;
    }

    /**
     * Re-elects a primary after a removal or a status change: first ready
     * attachment by sort order, then id. Returns null when nothing is eligible,
     * which leaves the owner with no primary rather than a broken one.
     */
    public function recalculate(
        string $mediableType,
        int $mediableId,
        MediaAttachmentRole $role = MediaAttachmentRole::Gallery,
    ): ?MediaAttachment {
        $siblings = $this->lockSiblings($mediableType, $mediableId, $role);

        $eligible = $siblings->first(fn (MediaAttachment $candidate): bool => $this->isReady($candidate));

        foreach ($siblings as $sibling) {
            $shouldBePrimary = $eligible !== null && $sibling->id === $eligible->id;

            if ($sibling->is_primary !== $shouldBePrimary) {
                $sibling->is_primary = $shouldBePrimary;
                $sibling->save();
            }
        }

        return $eligible;
    }

    private function isReady(MediaAttachment $attachment): bool
    {
        $attachment->loadMissing('asset');

        return $attachment->asset?->status === MediaStatus::Ready;
    }
}
