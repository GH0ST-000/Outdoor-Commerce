<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class SyncCategorySearchDocuments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly ?int $categoryId = null,
    ) {
        $this->onQueue((string) config('search.queue', 'catalog'));
    }

    public function uniqueId(): string
    {
        return 'search-category:'.($this->categoryId ?? 'all');
    }

    public function handle(SearchSynchronizationService $sync): void
    {
        if (! (bool) config('search.enabled', true)) {
            return;
        }
        if ($this->categoryId === null) {
            $sync->syncCategory();

            return;
        }
        $sync->syncCategory($this->categoryId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(SearchSynchronizationService::class)->logFailure('sync_category', [
                'category_id' => $this->categoryId,
            ], $exception);
        }
    }
}
