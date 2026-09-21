<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

final class PublicSeoComposer
{
    /**
     * @param  array<string, string>  $alternatePaths
     * @param  array<string, mixed>|null  $openGraphMedia
     * @return array<string, mixed>
     */
    public function compose(
        string $title,
        ?string $description,
        string $canonicalPath,
        array $alternatePaths,
        ?array $openGraphMedia = null,
        string $robots = 'index,follow',
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'canonical_path' => $canonicalPath,
            'alternate_locale_paths' => $alternatePaths,
            'open_graph_media' => $openGraphMedia,
            'robots' => $robots,
        ];
    }

    public function fallbackTitle(string $name): string
    {
        return $name;
    }

    public function fallbackDescription(?string $short): ?string
    {
        if ($short === null || trim($short) === '') {
            return null;
        }

        return mb_substr(trim($short), 0, 170);
    }
}
