<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Products;

final readonly class ProductTranslationData
{
    public function __construct(
        public string $locale,
        public string $name,
        public string $slug,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
    ) {}
}
