<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Legal\Jobs\ExtendSeasonHorizonJob;
use Illuminate\Console\Command;

final class ExtendLegalCalendarHorizonCommand extends Command
{
    protected $signature = 'legal-calendar:extend-horizon';

    protected $description = 'Queue rolling projection-horizon extension for published seasons.';

    public function handle(): int
    {
        ExtendSeasonHorizonJob::dispatch();
        $this->info('Queued calendar horizon extension.');

        return self::SUCCESS;
    }
}
