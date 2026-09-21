<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class RefreshPublicProductProjection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 60];

    public function __construct(
        public readonly int $productId,
    ) {
        $this->onQueue((string) config('catalog.public.projections.queue', 'catalog'));
    }

    public function uniqueId(): string
    {
        return 'public-product-proj:'.$this->productId;
    }

    public function handle(PublicCatalogProjector $projector): void
    {
        $projector->refreshProductGraph($this->productId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(PublicCatalogProjector::class)->logFailure('refresh_product', [
                'product_id' => $this->productId,
            ], $exception);
        }
    }
}
