<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class RefreshPromotionCatalogProjections implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $promotionId,
        public readonly int $afterId = 0,
        public readonly int $chunk = 250,
    ) {
        $this->onQueue((string) config('catalog.public.projections.queue', 'catalog'));
    }

    public function handle(PublicCatalogPricing $pricing, PublicCatalogProjector $projector): void
    {
        $ids = $pricing->variantIdsTargetedByPromotion($this->promotionId, $this->afterId, $this->chunk);
        $productIds = [];

        foreach ($ids as $variantId) {
            $row = $projector->refreshVariantWithoutProduct($variantId);
            $productIds[(int) $row->product_id] = true;
        }

        foreach (array_keys($productIds) as $productId) {
            if ((int) $productId > 0) {
                $projector->refreshProduct((int) $productId);
            }
        }

        if (count($ids) === $this->chunk) {
            self::dispatch($this->promotionId, (int) max($ids), $this->chunk);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(PublicCatalogProjector::class)->logFailure('refresh_promotion', [
                'promotion_id' => $this->promotionId,
                'after_id' => $this->afterId,
            ], $exception);
        }
    }
}
