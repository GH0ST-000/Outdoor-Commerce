<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Exceptions;

use RuntimeException;

final class SearchUnavailableException extends RuntimeException
{
    public static function infrastructure(string $reason = 'Search is temporarily unavailable.'): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'SEARCH_UNAVAILABLE';
    }
}
