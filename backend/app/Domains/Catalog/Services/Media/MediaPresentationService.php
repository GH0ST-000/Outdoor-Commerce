<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\MediaDerivative;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\CatalogLocales;

/**
 * Builds the responsive manifest consumed by the admin console and (from Day 12)
 * the storefront.
 *
 * `sources` only ever appears for ready assets, and only derivative URLs are
 * included — the private original disk and path never leave the server.
 */
final class MediaPresentationService
{
    public function __construct(
        private readonly MediaUrlService $urls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function manifest(MediaAttachment $attachment, ?string $locale = null): array
    {
        $locale ??= CatalogLocales::default();
        $attachment->loadMissing(['asset.derivatives', 'translations']);
        $asset = $attachment->asset;

        return [
            'id' => $attachment->id,
            'asset_id' => $attachment->media_asset_id,
            'role' => $attachment->role->value,
            'status' => $asset?->status->value,
            'is_primary' => $attachment->is_primary,
            'sort_order' => $attachment->sort_order,
            'original_filename' => $asset?->original_filename,
            'mime_type' => $asset?->mime_type,
            'byte_size' => $asset?->byte_size,
            'width' => $asset?->width,
            'height' => $asset?->height,
            'failure_code' => $asset?->failure_code,
            'focal_point' => $this->focalPoint($attachment),
            'alt_text' => $attachment->altText($locale),
            'caption' => $attachment->caption($locale),
            'translations' => $this->translations($attachment),
            'sources' => $asset?->isReady() === true ? $this->sources($attachment) : null,
            'created_at' => $attachment->created_at?->toIso8601String(),
            'updated_at' => $attachment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{srcset: string, presets: array<string, array{url: string, width: int, height: int, byte_size: int}>}>
     */
    public function sources(MediaAttachment $attachment): array
    {
        $attachment->loadMissing('asset.derivatives');
        $asset = $attachment->asset;

        if ($asset === null) {
            return [];
        }

        $order = array_map(static fn (MediaPreset $preset): string => $preset->value, MediaPreset::ordered());

        /** @var array<string, array{srcset: string, presets: array<string, array{url: string, width: int, height: int, byte_size: int}>}> $sources */
        $sources = [];

        foreach ($asset->derivatives as $derivative) {
            $url = $this->urls->forDerivative($derivative);

            if ($url === null) {
                continue;
            }

            $sources[$derivative->format->value]['presets'][$derivative->preset->value] = [
                'url' => $url,
                'width' => $derivative->width,
                'height' => $derivative->height,
                'byte_size' => $derivative->byte_size,
            ];
        }

        foreach ($sources as $format => $bucket) {
            $presets = $bucket['presets'];
            uksort($presets, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

            $sources[$format]['presets'] = $presets;
            $sources[$format]['srcset'] = implode(', ', array_map(
                static fn (array $entry): string => $entry['url'].' '.$entry['width'].'w',
                array_values($presets),
            ));
        }

        return $sources;
    }

    /**
     * Best single URL for a given preset, preferring the modern format and falling
     * back through the raster formats.
     */
    public function urlFor(MediaAttachment $attachment, MediaPreset $preset): ?string
    {
        $attachment->loadMissing('asset.derivatives');
        $asset = $attachment->asset;

        if ($asset === null || ! $asset->isReady()) {
            return null;
        }

        foreach ([MediaFormat::Webp, MediaFormat::Jpeg, MediaFormat::Png, MediaFormat::Avif] as $format) {
            $derivative = $asset->derivatives->first(
                static fn (MediaDerivative $candidate): bool => $candidate->preset === $preset
                    && $candidate->format === $format,
            );

            if ($derivative !== null) {
                return $this->urls->forDerivative($derivative);
            }
        }

        return null;
    }

    /**
     * @return array{x: float, y: float}|null
     */
    private function focalPoint(MediaAttachment $attachment): ?array
    {
        if ($attachment->focal_point_x === null || $attachment->focal_point_y === null) {
            return null;
        }

        return [
            'x' => (float) $attachment->focal_point_x,
            'y' => (float) $attachment->focal_point_y,
        ];
    }

    /**
     * @return array<string, array{alt_text: string|null, caption: string|null}>
     */
    private function translations(MediaAttachment $attachment): array
    {
        $translations = [];

        foreach ($attachment->translations as $translation) {
            $translations[$translation->locale] = [
                'alt_text' => $translation->alt_text,
                'caption' => $translation->caption,
            ];
        }

        return $translations;
    }

    /**
     * Display gallery for a variant with product fallback.
     *
     * Order: variant primary (as first when present) then remaining ready variant
     * attachments by sort_order; if the variant has no ready media, the product
     * gallery is returned instead. Attachments are never duplicated across owners.
     *
     * @return list<array<string, mixed>>
     */
    public function displayGalleryForVariant(
        ProductVariant $variant,
        ?Product $product = null,
        ?string $locale = null,
    ): array {
        $variant->loadMissing(['readyMediaAttachments.asset.derivatives', 'readyMediaAttachments.translations']);

        $variantReady = $variant->readyMediaAttachments
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        if ($variantReady->isNotEmpty()) {
            $primary = $variantReady->firstWhere('is_primary', true);
            $ordered = $primary === null
                ? $variantReady
                : $variantReady
                    ->reject(static fn (MediaAttachment $row): bool => $row->id === $primary->id)
                    ->prepend($primary)
                    ->values();

            return $ordered
                ->map(fn (MediaAttachment $attachment): array => $this->manifest($attachment, $locale))
                ->all();
        }

        $product ??= $variant->product;
        if ($product === null) {
            return [];
        }

        return $this->displayGalleryForProduct($product, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayGalleryForProduct(Product $product, ?string $locale = null): array
    {
        $product->loadMissing(['readyMediaAttachments.asset.derivatives', 'readyMediaAttachments.translations']);

        $ready = $product->readyMediaAttachments
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        if ($ready->isEmpty()) {
            return [];
        }

        $primary = $ready->firstWhere('is_primary', true);
        $ordered = $primary === null
            ? $ready
            : $ready
                ->reject(static fn (MediaAttachment $row): bool => $row->id === $primary->id)
                ->prepend($primary)
                ->values();

        return $ordered
            ->map(fn (MediaAttachment $attachment): array => $this->manifest($attachment, $locale))
            ->all();
    }
}
