<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Jobs;

use App\Domains\Hunting\Services\SpeciesSearchSynchronizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SyncSpeciesSearchDocuments implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $speciesId)
    {
        $this->onQueue((string) config('species.search.queue', 'catalog'));
    }

    public function handle(SpeciesSearchSynchronizationService $sync): void
    {
        $sync->sync($this->speciesId);
    }
}
