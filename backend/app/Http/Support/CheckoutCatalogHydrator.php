<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\PublicApi\Services\PublicMediaPresenter;

final class CheckoutCatalogHydrator
{
    public function __construct(private readonly PublicMediaPresenter $media) {}

    /**
     * @param  array{data: array<string, mixed>}  $payload
     * @return array{data: array<string, mixed>}
     */
    public function present(array $payload, string $locale): array
    {
        $quote = $payload['data']['quote'] ?? $payload['data']['checkout_session']['quote'] ?? null;
        if (! is_array($quote) || ! isset($quote['items']) || ! is_array($quote['items'])) {
            return $payload;
        }

        $productIds = [];
        foreach ($quote['items'] as $item) {
            if (is_array($item) && isset($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
        }

        $products = Product::query()
            ->with(['readyMediaAttachments.asset.derivatives', 'readyMediaAttachments.translations'])
            ->whereIn('id', array_values(array_unique($productIds)))
            ->get()
            ->keyBy('id');

        $map = function (array $item) use ($products, $locale): array {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            if ($product instanceof Product) {
                $media = $this->media->primaryForProduct($product, $locale);
                $url = '';
                if (is_array($media) && isset($media['sources']) && is_array($media['sources'])) {
                    foreach (['avif', 'webp', 'jpg', 'jpeg', 'png'] as $format) {
                        $preset = $media['sources'][$format]['presets']['thumbnail']['url']
                            ?? $media['sources'][$format]['presets']['card']['url']
                            ?? null;
                        if (is_string($preset) && $preset !== '') {
                            $url = $preset;
                            break;
                        }
                    }
                }
                $item['media'] = [
                    'url' => $url,
                    'alt' => is_array($media) && is_string($media['alt'] ?? null) ? $media['alt'] : ($item['name'] ?? null),
                ];
            }

            return $item;
        };

        $quote['items'] = array_map(
            fn (mixed $item): mixed => is_array($item) ? $map($item) : $item,
            $quote['items'],
        );
        $payload['data']['quote'] = $quote;
        if (isset($payload['data']['checkout_session']) && is_array($payload['data']['checkout_session'])) {
            $payload['data']['checkout_session']['quote'] = $quote;
        }

        return $payload;
    }
}
