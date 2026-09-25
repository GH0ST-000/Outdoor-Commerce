<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Hunting\Services\SpeciesSearchSynchronizationService;
use Illuminate\Console\Command;

final class SearchVerifySpeciesCommand extends Command
{
    protected $signature = 'search:verify-species';

    protected $description = 'Verify species search indexes against published MySQL records';

    public function handle(SpeciesSearchSynchronizationService $sync): int
    {
        $result = $sync->verify();
        foreach ($result['counts'] as $locale => $count) {
            $this->line($locale.': '.$count.' documents');
        }

        if (! $result['ok']) {
            $this->error('Species search verification found unexpected counts.');

            return self::FAILURE;
        }

        $this->info('Species search indexes look consistent.');

        return self::SUCCESS;
    }
}
