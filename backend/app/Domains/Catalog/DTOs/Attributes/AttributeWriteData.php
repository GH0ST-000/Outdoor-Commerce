<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;

final readonly class AttributeWriteData
{
    /**
     * @param  list<AttributeTranslationData>  $translations
     */
    public function __construct(
        public ?string $code,
        public ?AttributeType $type,
        public ?AttributeStatus $status,
        public ?bool $isFilterable,
        public ?int $sortOrder,
        public array $translations,
        public bool $syncTranslations = true,
    ) {}
}
