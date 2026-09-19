<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Attributes;

use App\Domains\Catalog\Enums\AttributeValueStatus;

final readonly class AttributeValueWriteData
{
    /**
     * @param  list<AttributeTranslationData>  $translations
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $code,
        public ?AttributeValueStatus $status,
        public ?int $sortOrder,
        public ?string $colorHex,
        public ?array $metadata,
        public array $translations,
        public bool $syncTranslations = true,
        public bool $colorHexProvided = false,
        public bool $metadataProvided = false,
    ) {}
}
