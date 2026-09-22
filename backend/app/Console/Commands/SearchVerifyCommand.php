<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Search\Services\SearchHealthService;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Console\Command;
use Throwable;

final class SearchVerifyCommand extends Command
{
    protected $signature = 'search:verify {--repair : Reindex missing and delete unexpected documents}';

    protected $description = 'Compare eligible MySQL catalog records with Meilisearch documents';

    public function handle(SearchSynchronizationService $sync, SearchHealthService $health): int
    {
        $snapshot = $health->snapshot();
        $this->info('Search status: '.$snapshot['status']);
        foreach ($snapshot['indexes'] as $key => $uid) {
            $this->line(" {$key}: {$uid}");
        }

        try {
            $report = $sync->verify((bool) $this->option('repair'));
        } catch (Throwable) {
            $this->error('Verification could not reach search infrastructure.');

            return self::FAILURE;
        }

        $this->info('Missing: '.$report['missing'].' Unexpected: '.$report['unexpected']);
        if ($report['locales'] !== []) {
            $this->warn('Affected locales: '.implode(', ', $report['locales']));
        }

        return $report['missing'] === 0 && $report['unexpected'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
