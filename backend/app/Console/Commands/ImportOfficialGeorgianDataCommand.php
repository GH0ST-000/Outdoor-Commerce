<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Legal\Exceptions\OfficialSnapshotException;
use App\Domains\Legal\Services\OfficialGeorgianSnapshotImporter;
use Illuminate\Console\Command;
use Throwable;

final class ImportOfficialGeorgianDataCommand extends Command
{
    protected $signature = 'official:import-georgia
        {--section=all : sources, species, seasons, limits, restrictions, fishing, spatial, assignments, or all}
        {--root= : Snapshot directory}
        {--dry-run : Verify checksums and count records without writing}';

    protected $description = 'Import the versioned official Georgian snapshot as unreviewed drafts. Nothing is published.';

    public function handle(OfficialGeorgianSnapshotImporter $importer): int
    {
        $root = $this->option('root');
        $root = is_string($root) && $root !== '' ? $root : null;

        try {
            $importer->verifyChecksums($root);
            if ((bool) $this->option('dry-run')) {
                $this->info('Checksums match. Dry run did not write.');

                return self::SUCCESS;
            }
            $counts = $importer->import($root, (string) $this->option('section'));
        } catch (OfficialSnapshotException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $key => $value) {
            $this->line($key.': '.$value);
        }
        $this->info('Imported as in-review drafts. Published rules: 0. Unresolved conflicts were not published.');

        return self::SUCCESS;
    }
}
