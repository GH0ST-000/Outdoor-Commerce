<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Listeners;

use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Hunting\Events\SpeciesArchived;
use App\Domains\Hunting\Events\SpeciesChanged;
use App\Domains\Hunting\Events\SpeciesPublished;
use App\Domains\Hunting\Events\SpeciesUnpublished;
use App\Domains\Hunting\Jobs\SyncSpeciesSearchDocuments;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Services\SpeciesPublicCache;
use App\Domains\Hunting\Support\SpeciesLogger;
use Illuminate\Support\Facades\DB;

final class RefreshSpeciesProjections
{
    public function __construct(
        private readonly SpeciesPublicCache $cache,
        private readonly SpeciesLogger $logger,
    ) {}

    public function changed(SpeciesChanged|SpeciesPublished|SpeciesUnpublished|SpeciesArchived $event): void
    {
        $this->after(function () use ($event): void {
            try {
                $this->cache->bump();
            } catch (\Throwable $exception) {
                $this->logger->warning('cache_invalidation_failed', [
                    'species_public_id' => $event->publicId,
                    'message' => $exception->getMessage(),
                ]);
            }
            if ((bool) config('search.enabled', true)) {
                SyncSpeciesSearchDocuments::dispatch($event->speciesId);
            }
        });
    }

    public function mediaAssetSaved(MediaAsset $asset): void
    {
        $asset->loadMissing('attachments');
        foreach ($asset->attachments as $attachment) {
            if ($attachment->mediable_type !== 'species') {
                continue;
            }
            $species = Species::query()->find($attachment->mediable_id);
            if ($species === null) {
                continue;
            }
            $this->changed(new SpeciesChanged($species->id, $species->public_id, 'media'));
        }
    }

    private function after(callable $callback): void
    {
        DB::afterCommit($callback);
    }
}
