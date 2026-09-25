<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

use Illuminate\Support\Facades\Log;

final class SpeciesLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('species.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('species.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('species.'.$event, $this->safe($context));
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
            $context['cookies'],
            $context['token'],
            $context['password'],
            $context['snapshot'],
            $context['editorial_notes'],
            $context['editor_note'],
            $context['private_notes'],
            $context['notes_private'],
            $context['original_path'],
            $context['archived_local_path'],
            $context['file'],
            $context['contents'],
        );

        return $context;
    }
}
