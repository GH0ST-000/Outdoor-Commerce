<?php

declare(strict_types=1);

namespace App\Domains\Geography\Jobs;

use App\Domains\Geography\Models\SpatialImport;
use App\Domains\Geography\Services\SpatialImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ImportSpatialDatasetVersionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $importId)
    {
        $this->onQueue((string) config('spatial.import.queue', 'default'));
    }

    public function handle(SpatialImportService $imports): void
    {
        $import = SpatialImport::query()->find($this->importId);
        if ($import === null) {
            return;
        }
        $imports->run($import);
    }
}
