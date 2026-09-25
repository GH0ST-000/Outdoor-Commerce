<?php

declare(strict_types=1);

namespace App\Domains\Legal\Jobs;

use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Services\SeasonProjectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateSeasonOccurrencesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public int $definitionId)
    {
        $this->onQueue((string) config('legal.calendar.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'legal-season-generate-'.$this->definitionId;
    }

    public function handle(SeasonProjectionService $projections): void
    {
        $definition = LegalSeasonDefinition::query()->find($this->definitionId);
        if ($definition === null) {
            return;
        }
        $projections->generateDefinition($definition);
    }
}
