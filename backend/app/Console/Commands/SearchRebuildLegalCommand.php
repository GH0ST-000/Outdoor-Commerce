<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class SearchRebuildLegalCommand extends Command
{
    protected $signature = 'search:rebuild-legal';

    protected $description = 'Rebuild optional legal-document discovery indexes. Evaluation always uses MySQL.';

    public function handle(): int
    {
        if (! (bool) config('legal.search.enabled', false) || ! (bool) config('search.enabled', true)) {
            $this->info('Legal search indexing is disabled. MySQL remains the source of truth for evaluation.');

            return self::SUCCESS;
        }

        $this->warn('Day 25 does not place legal evaluation in Meilisearch. Published document titles remain MySQL-authoritative.');
        $this->info('Optional public legal discovery indexing is deferred until a verified production source set exists.');

        return self::SUCCESS;
    }
}
