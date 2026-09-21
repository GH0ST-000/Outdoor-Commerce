<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Media;

use App\Domains\Catalog\Enums\MediaFormat;

/**
 * Result of sniffing an upload. Everything here is derived from the bytes on
 * disk; nothing is taken from the client-supplied name or content type.
 */
final readonly class MediaFileInspectionData
{
    public function __construct(
        public string $absolutePath,
        public MediaFormat $format,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public int $byteSize,
        public string $checksum,
        public ?string $sanitizedFilename,
    ) {}
}
