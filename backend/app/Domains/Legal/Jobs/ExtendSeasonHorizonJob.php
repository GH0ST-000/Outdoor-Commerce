<?php

declare(strict_types=1);

namespace App\Domains\Legal\Jobs;

use App\Domains\Legal\Services\SeasonProjectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExtendSeasonHorizonJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue((string) config('legal.calendar.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'legal-calendar-extend-horizon';
    }

    public function handle(SeasonProjectionService $projections): void
    {
        $projections->generatePublished();
    }
}
