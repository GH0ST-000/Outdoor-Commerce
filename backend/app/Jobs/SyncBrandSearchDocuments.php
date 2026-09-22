<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class SyncBrandSearchDocuments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly ?int $brandId = null,
    ) {
        $this->onQueue((string) config('search.queue', 'catalog'));
    }

    public function uniqueId(): string
    {
        return 'search-brand:'.($this->brandId ?? 'all');
    }

    public function handle(SearchSynchronizationService $sync): void
    {
        if (! (bool) config('search.enabled', true)) {
            return;
        }
        $sync->syncBrand($this->brandId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(SearchSynchronizationService::class)->logFailure('sync_brand', [
                'brand_id' => $this->brandId,
            ], $exception);
        }
    }
}
