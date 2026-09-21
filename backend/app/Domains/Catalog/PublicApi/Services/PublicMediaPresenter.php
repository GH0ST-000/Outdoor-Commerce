<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Media\MediaPresentationService;

/**
 * Public-safe media manifests. Private originals, failure internals, and
 * all-locale translation bags never leave this presenter.
 */
final class PublicMediaPresenter
{
    public function __construct(
        private readonly MediaPresentationService $presentation,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function compact(MediaAttachment $attachment, string $locale): ?array
    {
        $manifest = $this->presentation->manifest($attachment, $locale);
        if (($manifest['status'] ?? null) !== 'ready' || ($manifest['sources'] ?? null) === null) {
            return null;
        }

        $width = $manifest['width'] ?? null;
        $height = $manifest['height'] ?? null;

        return [
            'alt' => $manifest['alt_text'],
            'caption' => $manifest['caption'],
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => is_numeric($width) && is_numeric($height) && (int) $height > 0
                ? round((int) $width / (int) $height, 4)
                : null,
            'dominant_color' => null,
            'focal_point' => $manifest['focal_point'],
            'sources' => $manifest['sources'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function galleryForProduct(Product $product, string $locale): array
    {
        return $this->mapReady($this->presentation->displayGalleryForProduct($product, $locale), $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function galleryForVariant(ProductVariant $variant, Product $product, string $locale): array
    {
        return $this->mapReady($this->presentation->displayGalleryForVariant($variant, $product, $locale), $locale);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function primaryForProduct(Product $product, string $locale): ?array
    {
        $gallery = $this->galleryForProduct($product, $locale);

        return $gallery[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $manifests
     * @return list<array<string, mixed>>
     */
    private function mapReady(array $manifests, string $locale): array
    {
        unset($locale);
        $out = [];
        foreach ($manifests as $manifest) {
            if (($manifest['status'] ?? null) !== 'ready' || ($manifest['sources'] ?? null) === null) {
                continue;
            }
            $width = $manifest['width'] ?? null;
            $height = $manifest['height'] ?? null;
            $out[] = [
                'alt' => $manifest['alt_text'] ?? null,
                'caption' => $manifest['caption'] ?? null,
                'width' => $width,
                'height' => $height,
                'aspect_ratio' => is_numeric($width) && is_numeric($height) && (int) $height > 0
                    ? round((int) $width / (int) $height, 4)
                    : null,
                'dominant_color' => null,
                'focal_point' => $manifest['focal_point'] ?? null,
                'sources' => $manifest['sources'],
            ];
        }

        return $out;
    }
}
