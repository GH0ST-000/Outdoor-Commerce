<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds assets whose pipeline stalled — a worker that died mid-encode leaves a row
 * stuck in `processing` forever, and a dispatch lost before the queue accepted it
 * leaves one stuck in `pending`.
 */
final class MediaRecoveryService
{
    /**
     * @return Collection<int, MediaAsset>
     */
    public function stuck(?int $limit = null): Collection
    {
        $processingCutoff = now()->subMinutes((int) config('media.recovery.processing_stuck_minutes', 15));
        $pendingCutoff = now()->subMinutes((int) config('media.recovery.pending_stuck_minutes', 30));

        return MediaAsset::query()
            ->where(function ($query) use ($processingCutoff): void {
                $query->where('status', MediaStatus::Processing->value)
                    ->where(function ($inner) use ($processingCutoff): void {
                        $inner->whereNull('processing_started_at')
                            ->orWhere('processing_started_at', '<=', $processingCutoff);
                    });
            })
            ->orWhere(function ($query) use ($pendingCutoff): void {
                $query->where('status', MediaStatus::Pending->value)
                    ->where('created_at', '<=', $pendingCutoff);
            })
            ->orderBy('id')
            ->limit($limit ?? (int) config('media.recovery.batch_size', 100))
            ->get();
    }
}
