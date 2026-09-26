<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Queries;

use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\Media\MediaPresentationService;

final class PublicProductImageQuery
{
    public function __construct(private readonly MediaPresentationService $media) {}

    public function url(Product $product): ?string
    {
        $product->loadMissing(['mediaAttachments.asset.derivatives', 'variants.mediaAttachments.asset.derivatives']);
        $primary = $product->mediaAttachments->firstWhere('is_primary', true) ?? $product->mediaAttachments->first();
        if ($primary instanceof MediaAttachment) {
            $url = $this->media->urlFor($primary, MediaPreset::Card);
            if ($url !== null) {
                return $url;
            }
        }
        foreach ($product->variants as $variant) {
            $attachment = $variant->mediaAttachments->first();
            if ($attachment instanceof MediaAttachment) {
                $url = $this->media->urlFor($attachment, MediaPreset::Card);
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }
}
