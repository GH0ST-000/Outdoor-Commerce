<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

final class PublicCatalogUrlGenerator
{
    public function categoryPath(string $slug): string
    {
        return sprintf((string) config('catalog.public.storefront_paths.category', '/catalog/%s'), $slug);
    }

    public function brandPath(string $slug): string
    {
        return sprintf((string) config('catalog.public.storefront_paths.brand', '/brands/%s'), $slug);
    }

    public function productPath(string $slug): string
    {
        return sprintf((string) config('catalog.public.storefront_paths.product', '/products/%s'), $slug);
    }

    /**
     * @param  array<string, string|null>  $localeSlugs
     * @return array<string, string>
     */
    public function alternateLocalePaths(array $localeSlugs, string $type): array
    {
        $paths = [];
        foreach ($localeSlugs as $locale => $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $paths[$locale] = match ($type) {
                'brand' => $this->brandPath($slug),
                'product' => $this->productPath($slug),
                default => $this->categoryPath($slug),
            };
        }

        return $paths;
    }
}
