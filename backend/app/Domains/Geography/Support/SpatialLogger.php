<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use Illuminate\Support\Facades\Log;

final class SpatialLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('spatial.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('spatial.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('spatial.'.$event, $this->safe($context));
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
            $context['storage_path'],
            $context['file'],
            $context['contents'],
            $context['geometry'],
            $context['coordinates'],
            $context['lng'],
            $context['lat'],
            $context['longitude'],
            $context['latitude'],
            $context['query_string'],
        );

        if (isset($context['west'], $context['south'], $context['east'], $context['north'])) {
            $context['bbox_quantized'] = [
                round((float) $context['west'], 2),
                round((float) $context['south'], 2),
                round((float) $context['east'], 2),
                round((float) $context['north'], 2),
            ];
            unset($context['west'], $context['south'], $context['east'], $context['north']);
        }

        return $context;
    }
}
