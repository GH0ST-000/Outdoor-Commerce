<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Variants;

final readonly class VariantAxesData
{
    /**
     * @param  list<array{attribute_id: int, sort_order: int}>  $axes
     */
    public function __construct(public array $axes) {}
}
