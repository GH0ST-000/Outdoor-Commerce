<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Media;

final readonly class MediaProcessingResultData
{
    /**
     * @param  list<MediaRenditionData>  $renditions
     * @param  list<string>  $skippedFormats
     */
    public function __construct(
        public int $width,
        public int $height,
        public array $renditions,
        public array $skippedFormats = [],
    ) {}
}
