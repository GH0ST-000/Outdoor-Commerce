<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Events;

final readonly class CatalogProductChanged
{
    public function __construct(public int $productId) {}
}
