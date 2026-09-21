<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Events;

final readonly class CatalogAttributeChanged
{
    public function __construct(public int $attributeId) {}
}
