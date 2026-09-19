<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

/**
 * The product already holds as many non-deleted variants as configuration allows.
 */
final class VariantLimitExceededException extends DomainException implements ProvidesErrorDetails
{
    public function __construct(
        private readonly int $count,
        private readonly int $limit,
    ) {
        parent::__construct(
            "This product would hold {$count} variants, but the limit is {$limit}.",
            'VARIANT_LIMIT_EXCEEDED',
        );
    }

    /**
     * @return array{count: int, limit: int}
     */
    public function errorDetails(): array
    {
        return [
            'count' => $this->count,
            'limit' => $this->limit,
        ];
    }
}
