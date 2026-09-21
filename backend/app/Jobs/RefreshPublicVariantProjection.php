<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class RefreshPublicVariantProjection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 60];

    public function __construct(
        public readonly int $variantId,
    ) {
        $this->onQueue((string) config('catalog.public.projections.queue', 'catalog'));
    }

    public function uniqueId(): string
    {
        return 'public-variant-proj:'.$this->variantId;
    }

    public function handle(PublicCatalogProjector $projector): void
    {
        $projector->refreshVariant($this->variantId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(PublicCatalogProjector::class)->logFailure('refresh_variant', [
                'variant_id' => $this->variantId,
            ], $exception);
        }
    }
}
