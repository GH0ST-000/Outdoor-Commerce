<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\CalendarGenerationStatus;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalCalendarGenerationRun;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Support\LegalLogger;
use App\Domains\Legal\Support\SeasonDateRange;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class SeasonProjectionService
{
    public function __construct(
        private readonly SeasonOccurrenceGenerator $generator,
        private readonly LegalPublicCache $cache,
        private readonly LegalLogger $logger,
    ) {}

    /**
     * @return array{from_year: int, through_year: int}
     */
    public function horizon(?int $aroundYear = null): array
    {
        $year = $aroundYear ?? (int) (new \DateTimeImmutable('now', new \DateTimeZone(SeasonDateRange::configuredTimezone())))->format('Y');
        $past = (int) config('legal.calendar.horizon_past_years', 1);
        $future = (int) config('legal.calendar.horizon_future_years', 3);

        return [
            'from_year' => $year - $past,
            'through_year' => $year + $future,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateDefinition(LegalSeasonDefinition $definition, ?int $fromYear = null, ?int $throughYear = null, bool $persist = true): array
    {
        $horizon = $this->horizon();
        $fromYear ??= $horizon['from_year'];
        $throughYear ??= $horizon['through_year'];

        $lock = Cache::lock('legal.calendar.generate.'.$definition->id, (int) config('legal.calendar.lock_seconds', 120));
        if (! $lock->get()) {
            $this->logger->warning('generation_lock_busy', ['definition_id' => $definition->public_id]);

            return $this->generator->generate($definition, $fromYear, $throughYear, false);
        }

        try {
            $started = microtime(true);
            $result = $this->generator->generate($definition, $fromYear, $throughYear, $persist);
            $this->logger->info('occurrences_generated', [
                'definition_id' => $definition->public_id,
                'from_year' => $fromYear,
                'through_year' => $throughYear,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'invalidated' => $result['invalidated'],
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
            if ($persist) {
                $this->cache->bump();
            }

            return $result + ['from_year' => $fromYear, 'through_year' => $throughYear];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePublished(?int $fromYear = null, ?int $throughYear = null, ?string $jurisdiction = null, bool $dryRun = false, string $triggeredByType = 'system', ?int $triggeredById = null): array
    {
        $horizon = $this->horizon();
        $fromYear ??= $horizon['from_year'];
        $throughYear ??= $horizon['through_year'];

        $lock = Cache::lock('legal.calendar.generate-horizon', (int) config('legal.calendar.lock_seconds', 120));
        if (! $lock->get()) {
            $this->logger->warning('horizon_lock_busy', []);

            return ['status' => 'busy'];
        }

        $run = LegalCalendarGenerationRun::query()->create([
            'started_at' => now(),
            'from_year' => $fromYear,
            'through_year' => $throughYear,
            'status' => CalendarGenerationStatus::Running,
            'triggered_by_type' => $triggeredByType,
            'triggered_by_id' => $triggeredById,
            'jurisdiction_code' => $jurisdiction,
            'dry_run' => $dryRun,
        ]);

        $created = $updated = $invalidated = $failures = $processed = 0;
        $error = null;

        try {
            $query = LegalSeasonDefinition::query()->published();
            if ($jurisdiction !== null) {
                $query->where('jurisdiction_code', $jurisdiction);
            }
            $query->orderBy('id')->chunkById(50, function ($definitions) use ($fromYear, $throughYear, $dryRun, &$created, &$updated, &$invalidated, &$failures, &$processed): void {
                foreach ($definitions as $definition) {
                    $processed++;
                    try {
                        $result = $this->generateDefinition($definition, $fromYear, $throughYear, ! $dryRun);
                        $created += $result['created'];
                        $updated += $result['updated'];
                        $invalidated += $result['invalidated'];
                    } catch (Throwable $exception) {
                        $failures++;
                        $this->logger->error('generation_definition_failed', [
                            'definition_id' => $definition->public_id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

            $run->fill([
                'completed_at' => now(),
                'definitions_processed' => $processed,
                'occurrences_created' => $created,
                'occurrences_updated' => $updated,
                'occurrences_invalidated' => $invalidated,
                'failures' => $failures,
                'status' => $failures > 0
                    ? ($processed > $failures ? CalendarGenerationStatus::PartiallyFailed : CalendarGenerationStatus::Failed)
                    : CalendarGenerationStatus::Completed,
                'error_summary' => $failures > 0 ? $failures.' definition(s) failed during generation.' : null,
            ]);
            $run->save();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $run->fill([
                'completed_at' => now(),
                'status' => CalendarGenerationStatus::Failed,
                'error_summary' => 'Generation run failed.',
                'failures' => $failures + 1,
                'definitions_processed' => $processed,
            ]);
            $run->save();
            $this->logger->error('generation_run_failed', ['run_id' => $run->id, 'message' => $error]);
        } finally {
            $lock->release();
        }

        return [
            'run_id' => $run->id,
            'status' => $run->status->value,
            'from_year' => $fromYear,
            'through_year' => $throughYear,
            'definitions_processed' => $processed,
            'occurrences_created' => $created,
            'occurrences_updated' => $updated,
            'occurrences_invalidated' => $invalidated,
            'failures' => $failures,
        ];
    }

    public function invalidateForDefinition(LegalSeasonDefinition $definition): int
    {
        $count = LegalSeasonOccurrence::query()
            ->where('season_definition_id', $definition->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
        $this->cache->bump();
        $this->logger->info('occurrences_invalidated', [
            'definition_id' => $definition->public_id,
            'count' => $count,
        ]);

        return $count;
    }

    public function invalidateForRule(LegalRule $rule): int
    {
        $ids = LegalSeasonDefinition::query()->where('legal_rule_id', $rule->id)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        $count = LegalSeasonOccurrence::query()
            ->whereIn('season_definition_id', $ids)
            ->where('is_current', true)
            ->update(['is_current' => false]);
        LegalSeasonDefinition::query()
            ->whereIn('id', $ids)
            ->where('status', LegalRuleStatus::Published)
            ->update(['status' => LegalRuleStatus::Superseded->value]);
        $this->cache->bump();

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyProjections(): array
    {
        $stale = LegalSeasonOccurrence::query()
            ->current()
            ->whereHas('definition', function ($query): void {
                $query->where('status', '!=', LegalRuleStatus::Published);
            })
            ->count();
        $horizon = $this->horizon();
        $publishedWithoutFuture = LegalSeasonDefinition::query()
            ->published()
            ->where(function ($query) use ($horizon): void {
                $query->whereNull('generated_through_year')
                    ->orWhere('generated_through_year', '<', $horizon['through_year']);
            })
            ->count();

        if ($stale > 0) {
            $this->logger->warning('stale_projections', ['count' => $stale]);
        }
        if ($publishedWithoutFuture > 0) {
            $this->logger->warning('horizon_nearing_expiration', ['definitions' => $publishedWithoutFuture]);
        }

        return [
            'stale_current_occurrences' => $stale,
            'published_definitions_short_horizon' => $publishedWithoutFuture,
            'horizon' => $horizon,
        ];
    }
}
