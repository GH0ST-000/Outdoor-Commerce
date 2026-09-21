<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\PublicApi\Models\CatalogProjectionRefreshState;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class RefreshTimeSensitiveCatalogProjectionsCommand extends Command
{
    protected $signature = 'catalog:refresh-time-sensitive-projections
        {--chunk=250 : Variant chunk size}';

    protected $description = 'Refresh public projections whose scheduled prices or promotions crossed a time boundary';

    public function handle(
        PublicCatalogPricing $pricing,
        PublicCatalogProjector $projector,
        CatalogCache $catalogCache,
        Clock $clock,
    ): int {
        $now = $clock->now();
        $state = CatalogProjectionRefreshState::query()->find('time_sensitive');
        $from = $state?->last_ran_at !== null
            ? CarbonImmutable::instance($state->last_ran_at)->utc()
            : $now->subMinutes(2);

        $chunk = max(1, (int) $this->option('chunk'));
        $refreshed = 0;
        $afterId = 0;

        do {
            $ids = $pricing->variantIdsWithBoundaryBetween($from, $now, $afterId, $chunk);
            $productIds = [];
            foreach ($ids as $variantId) {
                $row = $projector->refreshVariantWithoutProduct($variantId);
                $productIds[(int) $row->product_id] = true;
                $refreshed++;
                $afterId = $variantId;
            }
            foreach (array_keys($productIds) as $productId) {
                if ((int) $productId > 0) {
                    $projector->refreshProduct((int) $productId);
                }
            }
        } while (count($ids) === $chunk);

        CatalogProjectionRefreshState::query()->updateOrCreate(
            ['name' => 'time_sensitive'],
            [
                'last_ran_at' => $now,
                'last_boundary_at' => $pricing->nextBoundaryAt($now),
                'refreshed_variants' => $refreshed,
            ],
        );

        $this->info("Refreshed {$refreshed} time-sensitive variant projection(s).");

        if ($refreshed > 0) {
            $catalogCache->bump();
        }

        return self::SUCCESS;
    }
}
