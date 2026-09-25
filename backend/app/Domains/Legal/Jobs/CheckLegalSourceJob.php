<?php

declare(strict_types=1);

namespace App\Domains\Legal\Jobs;

use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Services\LegalSourceMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckLegalSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public int $sourceId)
    {
        $this->onQueue((string) config('legal.monitoring.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'legal-source-check-'.$this->sourceId;
    }

    public function handle(LegalSourceMonitor $monitor): void
    {
        $source = LegalSource::query()->find($this->sourceId);
        if ($source === null) {
            return;
        }

        $monitor->check($source);
    }
}
