<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

final readonly class BulkUpsertPricesResult
{
    public function __construct(
        public int $created,
        public int $updated,
    ) {}
}
