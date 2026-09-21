<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Media;

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;

/**
 * An encoded rendition held in memory until the job persists it.
 */
final readonly class MediaRenditionData
{
    public function __construct(
        public MediaPreset $preset,
        public MediaFormat $format,
        public int $width,
        public int $height,
        public string $contents,
    ) {}

    public function byteSize(): int
    {
        return strlen($this->contents);
    }
}
