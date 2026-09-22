<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\PublicApi\Services\PublicMediaPresenter;

final class SnapshotPublicProductMediaAction
{
    public function __construct(private readonly PublicMediaPresenter $media) {}

    /**
     * @param  list<int>  $productIds
     * @return array<int, array{url: string, alt: string|null}>
     */
    public function execute(array $productIds, string $locale): array
    {
        $ids = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $products = Product::query()
            ->with(['readyMediaAttachments.asset.derivatives', 'readyMediaAttachments.translations'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $product = $products->get($id);
            if (! $product instanceof Product) {
                continue;
            }

            $media = $this->media->primaryForProduct($product, $locale);
            $url = '';
            if (is_array($media) && isset($media['sources']) && is_array($media['sources'])) {
                foreach (['avif', 'webp', 'jpg', 'jpeg', 'png'] as $format) {
                    $preset = $media['sources'][$format]['presets']['thumbnail']['url']
                        ?? $media['sources'][$format]['presets']['card']['url']
                        ?? null;
                    if (is_string($preset) && $preset !== '' && ! str_contains($preset, storage_path())) {
                        $url = $preset;
                        break;
                    }
                }
            }

            if ($url === '') {
                continue;
            }

            $out[$id] = [
                'url' => $url,
                'alt' => is_array($media) && is_string($media['alt'] ?? null) ? $media['alt'] : null,
            ];
        }

        return $out;
    }
}
