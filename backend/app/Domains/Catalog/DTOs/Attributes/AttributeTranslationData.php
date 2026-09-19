<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Attributes;

final readonly class AttributeTranslationData
{
    public function __construct(
        public string $locale,
        public string $name,
        public ?string $description = null,
    ) {}
}
