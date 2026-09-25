<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Legal\Services\SeasonProjectionService;
use Illuminate\Console\Command;

final class VerifyLegalCalendarProjectionsCommand extends Command
{
    protected $signature = 'legal-calendar:verify-projections';

    protected $description = 'Report stale season occurrences and short projection horizons. Never publishes.';

    public function handle(SeasonProjectionService $projections): int
    {
        $result = $projections->verifyProjections();
        $this->info('Stale current occurrences: '.$result['stale_current_occurrences']);
        $this->info('Published definitions short of horizon: '.$result['published_definitions_short_horizon']);

        return self::SUCCESS;
    }
}
