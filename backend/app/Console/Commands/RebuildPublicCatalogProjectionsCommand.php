<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Catalog\Services\CatalogCache;
use App\Jobs\RefreshPublicProductProjection;
use Illuminate\Console\Command;

final class RebuildPublicCatalogProjectionsCommand extends Command
{
    protected $signature = 'catalog:rebuild-public-projections
        {--product= : Rebuild one product graph}
        {--variant= : Rebuild one variant then its product}
        {--chunk=500 : Chunk size for full rebuild}
        {--queue : Dispatch jobs instead of running inline}
        {--dry-run : Count affected rows without writing}';

    protected $description = 'Rebuild disposable public catalog projections from authoritative domains';

    public function handle(PublicCatalogProjector $projector, CatalogCache $catalogCache): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $queue = (bool) $this->option('queue');
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('variant') !== null) {
            $variantId = (int) $this->option('variant');
            $this->info($dryRun ? "Would refresh variant {$variantId}" : "Refreshing variant {$variantId}");
            if (! $dryRun) {
                $projector->refreshVariant($variantId);
                $catalogCache->bump();
            }

            return self::SUCCESS;
        }

        if ($this->option('product') !== null) {
            $productId = (int) $this->option('product');
            $this->info($dryRun ? "Would refresh product {$productId}" : "Refreshing product {$productId}");
            if (! $dryRun) {
                if ($queue) {
                    RefreshPublicProductProjection::dispatch($productId);
                } else {
                    $projector->refreshProductGraph($productId);
                }
                $catalogCache->bump();
            }

            return self::SUCCESS;
        }

        $count = 0;
        Product::query()->withTrashed()->orderBy('id')->chunkById($chunk, function ($products) use ($projector, $queue, $dryRun, &$count): void {
            foreach ($products as $product) {
                $count++;
                if ($dryRun) {
                    continue;
                }
                if ($queue) {
                    RefreshPublicProductProjection::dispatch((int) $product->id);
                } else {
                    $projector->refreshProductGraph((int) $product->id);
                }
            }
            $this->info("Processed {$count} products...");
        });

        $this->info($dryRun ? "Dry run: {$count} products." : "Rebuilt projections for {$count} products.");

        if (! $dryRun && $count > 0) {
            $catalogCache->bump();
        }

        return self::SUCCESS;
    }
}
