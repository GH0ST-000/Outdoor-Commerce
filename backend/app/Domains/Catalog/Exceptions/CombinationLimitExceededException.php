<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

/**
 * A generation request asked for more combinations than one call may create.
 */
final class CombinationLimitExceededException extends DomainException implements ProvidesErrorDetails
{
    public function __construct(
        private readonly int $count,
        private readonly int $limit,
    ) {
        parent::__construct(
            "Requested {$count} combinations, but at most {$limit} may be generated in one call.",
            'COMBINATION_LIMIT_EXCEEDED',
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
