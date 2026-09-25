<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use Illuminate\Support\Facades\Log;

final class LegalLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('legal.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('legal.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('legal.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safe(array $context): array
    {
        unset(
            $context['authorization'],
            $context['cookie'],
            $context['token'],
            $context['password'],
            $context['internal_notes'],
            $context['notes'],
            $context['storage_path'],
            $context['file'],
            $context['contents'],
            $context['extracted_text'],
        );

        return $context;
    }
}
