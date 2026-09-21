<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reclaims storage for assets nothing references any more.
 *
 * Two safety rules are non-negotiable: files are only ever deleted through paths
 * recorded in the database, and only on disks in the media allowlist. The service
 * never walks the filesystem looking for things to remove.
 */
final class MediaCleanupService
{
    public function __construct(
        private readonly MediaStorageService $storage,
    ) {}

    /**
     * An asset is orphaned in one of two ways, and the grace period is measured
     * from whichever event applies:
     *
     * - it was never attached, counting from upload, which protects a batch still
     *   in flight between the POST and the worker picking it up;
     * - every attachment has been removed, counting from the most recent removal,
     *   so an accidental detach stays recoverable for the full window even when the
     *   asset itself is old.
     *
     * @return Collection<int, MediaAsset>
     */
    public function orphans(?int $limit = null): Collection
    {
        $graceHours = (int) config('media.cleanup.grace_period_hours', 24);
        $cutoff = now()->subHours(max(0, $graceHours));

        return MediaAsset::withTrashed()
            ->with('derivatives')
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $neverAttached) use ($cutoff): void {
                        $neverAttached
                            ->whereDoesntHave('attachments', static fn (Builder $attachments): Builder => $attachments->withTrashed())
                            ->where('created_at', '<=', $cutoff);
                    })
                    ->orWhere(function (Builder $detached) use ($cutoff): void {
                        $detached
                            ->whereDoesntHave('attachments')
                            ->whereHas('attachments', static fn (Builder $attachments): Builder => $attachments->onlyTrashed())
                            ->whereDoesntHave(
                                'attachments',
                                static fn (Builder $attachments): Builder => $attachments->withTrashed()
                                    ->where('deleted_at', '>', $cutoff),
                            );
                    });
            })
            ->orderBy('id')
            ->limit($limit ?? (int) config('media.cleanup.batch_size', 200))
            ->get();
    }

    /**
     * @return array{scanned: int, assets: list<array{id: int, uuid: string, files: int}>, deleted_assets: int, deleted_files: int, skipped_paths: int, dry_run: bool}
     */
    public function cleanup(bool $dryRun = true, ?int $limit = null): array
    {
        $orphans = $this->orphans($limit);

        $summary = [
            'scanned' => $orphans->count(),
            'assets' => [],
            'deleted_assets' => 0,
            'deleted_files' => 0,
            'skipped_paths' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($orphans as $asset) {
            $paths = $this->recordedPaths($asset);
            $allowed = array_filter($paths, static fn (array $entry): bool => MediaDisk::isAllowed($entry['disk']));
            $summary['skipped_paths'] += count($paths) - count($allowed);

            $summary['assets'][] = [
                'id' => $asset->id,
                'uuid' => $asset->uuid,
                'files' => count($allowed),
            ];

            if ($dryRun) {
                continue;
            }

            foreach ($allowed as $entry) {
                if ($this->storage->deleteRecordedPath($entry['disk'], $entry['path'])) {
                    $summary['deleted_files']++;
                }
            }

            // Removes the now-empty UUID folders left behind by the file deletes.
            $this->storage->deleteDirectories($asset);

            DB::transaction(function () use ($asset): void {
                $asset->derivatives()->delete();
                $asset->forceDelete();
            });

            $summary['deleted_assets']++;
        }

        return $summary;
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    private function recordedPaths(MediaAsset $asset): array
    {
        $paths = [[
            'disk' => $asset->original_disk,
            'path' => $asset->original_path,
        ]];

        foreach ($asset->derivatives as $derivative) {
            $paths[] = [
                'disk' => $derivative->disk,
                'path' => $derivative->path,
            ];
        }

        return $paths;
    }
}
