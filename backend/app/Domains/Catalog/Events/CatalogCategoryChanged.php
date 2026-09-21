<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Events;

final readonly class CatalogCategoryChanged
{
    public function __construct(public int $categoryId) {}
}
