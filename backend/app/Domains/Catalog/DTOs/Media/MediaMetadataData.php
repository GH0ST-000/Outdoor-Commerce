<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Media;

/**
 * Partial metadata patch. `null` means "not provided"; use the `*Provided` flags
 * to clear a focal point explicitly.
 */
final readonly class MediaMetadataData
{
    /**
     * @param  array<string, array{alt_text?: string|null, caption?: string|null}>|null  $translations
     */
    public function __construct(
        public ?array $translations = null,
        public ?float $focalPointX = null,
        public ?float $focalPointY = null,
        public bool $focalPointProvided = false,
    ) {}
}
