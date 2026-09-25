<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Legal\Services\SeasonProjectionService;
use Illuminate\Console\Command;

final class GenerateLegalCalendarCommand extends Command
{
    protected $signature = 'legal-calendar:generate
        {--from= : Opening season year to start from}
        {--through= : Opening season year to generate through}
        {--jurisdiction= : Optional jurisdiction filter}
        {--dry-run : Resolve occurrences without writing}';

    protected $description = 'Generate season occurrences from published definitions. Never publishes definitions.';

    public function handle(SeasonProjectionService $projections): int
    {
        $result = $projections->generatePublished(
            fromYear: $this->option('from') !== null ? (int) $this->option('from') : null,
            throughYear: $this->option('through') !== null ? (int) $this->option('through') : null,
            jurisdiction: $this->option('jurisdiction') !== null ? (string) $this->option('jurisdiction') : null,
            dryRun: (bool) $this->option('dry-run'),
            triggeredByType: 'command',
        );
        $this->info('Calendar generation '.$result['status'].' processed '.($result['definitions_processed'] ?? 0).' definitions.');

        return self::SUCCESS;
    }
}
