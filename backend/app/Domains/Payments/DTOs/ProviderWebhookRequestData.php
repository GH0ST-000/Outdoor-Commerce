<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

final readonly class ProviderWebhookRequestData
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $rawBody,
        public array $headers,
        public string $contentType,
    ) {}
}
