<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class SyncProductSearchDocuments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 60];

    public int $timeout = 60;

    public function __construct(
        public readonly int $productId,
        public readonly bool $debounced = false,
    ) {
        $this->onQueue((string) config('search.queue', 'catalog'));
    }

    public function uniqueId(): string
    {
        return 'search-product:'.$this->productId;
    }

    public function handle(SearchSynchronizationService $sync): void
    {
        if (! (bool) config('search.enabled', true)) {
            return;
        }
        $sync->syncProduct($this->productId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(SearchSynchronizationService::class)->logFailure('sync_product', [
                'product_id' => $this->productId,
            ], $exception);
        }
    }
}
