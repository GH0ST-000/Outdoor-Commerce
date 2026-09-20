<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PriceListActivated
{
    public function __construct(public int $priceListId) {}
}
