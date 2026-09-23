<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Console\Command;
use Throwable;

final class SearchRemoveProductCommand extends Command
{
    protected $signature = 'search:remove-product {productId : Product id}';

    protected $description = 'Remove all search documents for one product';

    public function handle(SearchSynchronizationService $sync): int
    {
        $productId = (int) $this->argument('productId');
        if ($productId < 1) {
            $this->error('Invalid product id.');

            return self::FAILURE;
        }

        try {
            $sync->removeProduct($productId);
        } catch (Throwable) {
            $this->error('Unable to remove product search documents.');

            return self::FAILURE;
        }

        $this->info("Removed product {$productId} from search.");

        return self::SUCCESS;
    }
}
