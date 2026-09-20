<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Services\Media\MediaRecoveryService;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MediaRecoverStuckCommand extends Command
{
    protected $signature = 'media:recover-stuck
        {--dry-run : Report stalled assets without requeueing them}
        {--limit= : Override the configured batch size}';

    protected $description = 'Requeue media assets whose processing stalled in pending or processing';

    public function handle(MediaRecoveryService $recovery): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : null;

        $stuck = $recovery->stuck($limit);

        if ($stuck->isEmpty()) {
            $this->info('No stalled media assets found.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "Dry run: {$stuck->count()} stalled asset(s) would be requeued."
            : "Requeueing {$stuck->count()} stalled asset(s).");

        foreach ($stuck as $asset) {
            $this->line(sprintf(
                'asset #%d — %s since %s (attempts: %d)',
                $asset->id,
                $asset->status->value,
                ($asset->processing_started_at ?? $asset->created_at)?->toIso8601String() ?? 'unknown',
                $asset->attempts,
            ));

            if ($dryRun) {
                continue;
            }

            $this->requeue($asset);
        }

        return self::SUCCESS;
    }

    /**
     * Resetting to pending first means a worker that is somehow still alive on the
     * old attempt cannot be confused by the new one: the guarded claim in the job
     * reasserts `processing` before doing any work.
     */
    private function requeue(MediaAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            /** @var MediaAsset|null $locked */
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status->isTerminal()) {
                return;
            }

            $locked->status = MediaStatus::Pending;
            $locked->processing_started_at = null;
            $locked->save();
        });

        ProcessMediaAsset::dispatch($asset->id);
    }
}
