<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Data;

final readonly class CategorySearchDocument
{
    public function __construct(
        public string $id,
        public int $categoryId,
        public string $locale,
        public string $name,
        public string $slug,
        public string $path,
        public int $productCount,
        public string $documentVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'locale' => $this->locale,
            'name' => $this->name,
            'slug' => $this->slug,
            'path' => $this->path,
            'product_count' => $this->productCount,
            'document_version' => $this->documentVersion,
        ];
    }
}
