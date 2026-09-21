<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Services\Media\MediaCleanupService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Console\Command;

final class MediaCleanupOrphansCommand extends Command
{
    protected $signature = 'media:cleanup-orphans
        {--dry-run : Report what would be removed without deleting anything}
        {--limit= : Override the configured batch size}';

    protected $description = 'Delete media assets that no longer have any attachment, along with their files';

    public function handle(
        MediaCleanupService $cleanup,
        RecordAuditEventAction $recordAuditEvent,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : null;

        $summary = $cleanup->cleanup($dryRun, $limit);

        $this->info($dryRun
            ? "Dry run: {$summary['scanned']} orphaned asset(s) would be deleted."
            : "Deleted {$summary['deleted_assets']} orphaned asset(s) and {$summary['deleted_files']} file(s).");

        foreach ($summary['assets'] as $asset) {
            $this->line(sprintf('asset #%d (%s) — %d file(s)', $asset['id'], $asset['uuid'], $asset['files']));
        }

        if ($summary['skipped_paths'] > 0) {
            $this->warn("Skipped {$summary['skipped_paths']} path(s) recorded on a disk outside the media allowlist.");
        }

        if (! $dryRun && $summary['deleted_assets'] > 0) {
            $recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaOrphanDeleted,
                subjectType: 'media_asset',
                subjectId: 'cleanup',
                metadata: [
                    'scanned' => $summary['scanned'],
                    'deleted_assets' => $summary['deleted_assets'],
                    'deleted_files' => $summary['deleted_files'],
                    'asset_ids' => array_map(static fn (array $asset): int => $asset['id'], $summary['assets']),
                ],
            ));
        }

        return self::SUCCESS;
    }
}
