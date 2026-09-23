<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class SearchQueryException extends DomainException
{
    public static function invalid(string $message = 'The search query is invalid.'): self
    {
        return new self($message, 'SEARCH_QUERY_INVALID');
    }
}
