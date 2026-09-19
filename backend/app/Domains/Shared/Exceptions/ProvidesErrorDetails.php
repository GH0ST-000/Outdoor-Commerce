<?php

declare(strict_types=1);

namespace App\Domains\Shared\Exceptions;

/**
 * Domain exceptions that carry structured details for the API error envelope.
 */
interface ProvidesErrorDetails
{
    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array;
}
