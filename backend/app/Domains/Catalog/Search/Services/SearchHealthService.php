<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use Throwable;

final class SearchHealthService
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SearchIndexManager $indexes,
    ) {}

    /**
     * @return array{status: string, enabled: bool, reachable: bool, indexes: array<string, string>}
     */
    public function snapshot(): array
    {
        $enabled = (bool) config('search.enabled', true);
        $reachable = false;
        if ($enabled) {
            try {
                $reachable = $this->gateway->health();
            } catch (Throwable) {
                $reachable = false;
            }
        }

        return [
            'status' => ! $enabled ? 'disabled' : ($reachable ? 'ok' : 'degraded'),
            'enabled' => $enabled,
            'reachable' => $reachable,
            'indexes' => $this->indexes->activeIndexes(),
        ];
    }
}
