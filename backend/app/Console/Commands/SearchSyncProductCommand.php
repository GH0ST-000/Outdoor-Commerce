<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Console\Command;
use Throwable;

final class SearchSyncProductCommand extends Command
{
    protected $signature = 'search:sync-product {productId : Product id}';

    protected $description = 'Rebuild search documents for one product from MySQL';

    public function handle(SearchSynchronizationService $sync): int
    {
        $productId = (int) $this->argument('productId');
        if ($productId < 1) {
            $this->error('Invalid product id.');

            return self::FAILURE;
        }

        try {
            $sync->syncProduct($productId);
        } catch (Throwable) {
            $this->error('Unable to sync product search documents.');

            return self::FAILURE;
        }

        $this->info("Synced product {$productId}.");

        return self::SUCCESS;
    }
}
