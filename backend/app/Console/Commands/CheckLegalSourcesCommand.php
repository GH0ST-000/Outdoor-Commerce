<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Legal\Jobs\CheckLegalSourceJob;
use App\Domains\Legal\Models\LegalSource;
use Illuminate\Console\Command;

final class CheckLegalSourcesCommand extends Command
{
    protected $signature = 'legal:check-sources';

    protected $description = 'Queue checks for verified legal sources. Never publishes rules.';

    public function handle(): int
    {
        $count = 0;
        LegalSource::query()
            ->where('is_active', true)
            ->where('monitor_for_changes', true)
            ->where('verification_status', 'verified')
            ->pluck('id')
            ->each(function (int $id) use (&$count): void {
                CheckLegalSourceJob::dispatch($id);
                $count++;
            });

        $this->info('Queued '.$count.' legal source checks. Detected changes never auto-publish.');

        return self::SUCCESS;
    }
}
