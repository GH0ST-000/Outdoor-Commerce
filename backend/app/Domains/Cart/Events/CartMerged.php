<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartMerged
{
    public function __construct(
        public string $destinationPublicId,
        public string $sourcePublicId,
        public bool $quantitiesAdjusted,
    ) {}
}
