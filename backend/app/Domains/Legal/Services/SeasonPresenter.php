<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalCalendarGenerationRun;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Legal\Support\SeasonDateRange;

final class SeasonPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function speciesPublic(Species $species, string $locale): array
    {
        $species->loadMissing('translations');
        $translation = $species->translation($locale);

        return [
            'id' => $species->public_id,
            'slug' => $species->canonical_slug,
            'common_name' => $translation?->common_name,
            'scientific_name' => $species->scientific_name,
            'media' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definitionAdmin(LegalSeasonDefinition $definition): array
    {
        $definition->loadMissing(['species', 'rule']);

        return [
            'id' => $definition->public_id,
            'species' => [
                'id' => $definition->species?->public_id,
                'scientific_name' => $definition->species?->scientific_name,
                'slug' => $definition->species?->canonical_slug,
            ],
            'legal_rule' => [
                'id' => $definition->rule?->public_id,
                'title' => $definition->rule?->title,
                'status' => $definition->rule?->status->value,
                'effect' => $definition->rule?->effect->value,
            ],
            'activity_type' => $definition->activity_type->value,
            'season_type' => $definition->season_type->value,
            'schedule_type' => $definition->schedule_type->value,
            'jurisdiction_code' => $definition->jurisdiction_code,
            'region_code' => $definition->region_code,
            'zone_reference' => $definition->zone_reference,
            'timezone' => $definition->timezone,
            'boundary_precision' => $definition->boundary_precision->value,
            'start_date' => $definition->start_date?->toDateString(),
            'end_date' => $definition->end_date?->toDateString(),
            'start_month' => $definition->start_month,
            'start_day' => $definition->start_day,
            'end_month' => $definition->end_month,
            'end_day' => $definition->end_day,
            'start_time' => $definition->start_time,
            'end_time' => $definition->end_time,
            'first_season_year' => $definition->first_season_year,
            'last_season_year' => $definition->last_season_year,
            'crosses_calendar_year' => $definition->crosses_calendar_year,
            'status' => $definition->status->value,
            'verification_level' => $definition->verification_level->value,
            'generated_through_year' => $definition->generated_through_year,
            'reviewed_at' => $definition->reviewed_at?->toIso8601String(),
            'published_at' => $definition->published_at?->toIso8601String(),
            'content_version' => $definition->content_version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function occurrenceAdmin(LegalSeasonOccurrence $occurrence): array
    {
        $occurrence->loadMissing('definition');

        return [
            'id' => $occurrence->id,
            'season_definition_id' => $occurrence->definition?->public_id,
            'species_id' => $occurrence->species_id,
            'activity_type' => $occurrence->activity_type->value,
            'season_year' => $occurrence->season_year,
            'jurisdiction_code' => $occurrence->jurisdiction_code,
            'region_code' => $occurrence->region_code,
            'starts_at' => $occurrence->starts_at->toIso8601String(),
            'ends_at_exclusive' => $occurrence->ends_at_exclusive->toIso8601String(),
            'local_start_date' => $occurrence->local_start_date->toDateString(),
            'local_end_date_inclusive' => $occurrence->local_end_date_inclusive->toDateString(),
            'effect' => $occurrence->effect->value,
            'generation_version' => $occurrence->generation_version,
            'is_current' => $occurrence->is_current,
            'definition_updated_at' => $occurrence->definition_updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overrideAdmin(LegalSeasonOverride $override): array
    {
        $override->loadMissing(['definition', 'rule']);

        return [
            'id' => $override->public_id,
            'base_season_definition_id' => $override->definition?->public_id,
            'legal_rule' => [
                'id' => $override->rule?->public_id,
                'title' => $override->rule?->title,
                'status' => $override->rule?->status->value,
            ],
            'override_type' => $override->override_type->value,
            'starts_at' => $override->starts_at->toIso8601String(),
            'ends_at_exclusive' => $override->ends_at_exclusive->toIso8601String(),
            'jurisdiction_code' => $override->jurisdiction_code,
            'region_code' => $override->region_code,
            'reason' => $override->reason,
            'precedence' => $override->precedence,
            'status' => $override->status->value,
            'reviewed_at' => $override->reviewed_at?->toIso8601String(),
            'published_at' => $override->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runAdmin(LegalCalendarGenerationRun $run): array
    {
        return [
            'id' => $run->id,
            'started_at' => $run->started_at->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'from_year' => $run->from_year,
            'through_year' => $run->through_year,
            'status' => $run->status->value,
            'definitions_processed' => $run->definitions_processed,
            'occurrences_created' => $run->occurrences_created,
            'occurrences_updated' => $run->occurrences_updated,
            'occurrences_invalidated' => $run->occurrences_invalidated,
            'failures' => $run->failures,
            'triggered_by_type' => $run->triggered_by_type,
            'error_summary' => $run->error_summary,
            'dry_run' => $run->dry_run,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $tz = new \DateTimeZone(SeasonDateRange::configuredTimezone());
        $now = new \DateTimeImmutable('now', $tz);
        $soon = $now->modify('+14 days');

        return [
            'published_hunting' => LegalSeasonDefinition::query()->published()->where('activity_type', 'hunting')->count(),
            'published_fishing' => LegalSeasonDefinition::query()->published()->where('activity_type', 'fishing')->count(),
            'drafts' => LegalSeasonDefinition::query()->where('status', LegalRuleStatus::Draft)->count(),
            'awaiting_review' => LegalSeasonDefinition::query()->where('status', LegalRuleStatus::InReview)->count(),
            'opening_soon' => LegalSeasonOccurrence::query()->current()->where('effect', 'allow')
                ->where('starts_at', '>', $now)->where('starts_at', '<=', $soon)->count(),
            'closing_soon' => LegalSeasonOccurrence::query()->current()->where('effect', 'allow')
                ->where('ends_at_exclusive', '>', $now)->where('ends_at_exclusive', '<=', $soon)->count(),
            'failed_generation_runs' => LegalCalendarGenerationRun::query()->whereIn('status', ['failed', 'partially_failed'])->count(),
            'open_calendar_conflicts' => LegalConflict::query()->where('evidence', 'like', 'season_calendar%')
                ->whereIn('status', ['open', 'under_review'])->count(),
            'recent_overrides' => LegalSeasonOverride::query()->published()->latest('published_at')->limit(5)->get()
                ->map(fn (LegalSeasonOverride $override): array => [
                    'id' => $override->public_id,
                    'type' => $override->override_type->value,
                    'reason' => $override->reason,
                ])->all(),
            'horizon' => app(SeasonProjectionService::class)->horizon(),
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        $species = Species::query()->published()->orderBy('scientific_name')->get(['id', 'public_id', 'scientific_name', 'canonical_slug', 'activity_type']);
        $definitions = LegalSeasonDefinition::query()->published()->get()->groupBy('species_id');
        $rows = [];
        foreach ($species as $record) {
            $defs = $definitions->get($record->id, collect());
            $rows[] = [
                'species_id' => $record->public_id,
                'scientific_name' => $record->scientific_name,
                'slug' => $record->canonical_slug,
                'activity_type' => $record->activity_type->value,
                'has_verified_season' => $defs->isNotEmpty(),
                'season_count' => $defs->count(),
                'last_verified_at' => $defs->max('reviewed_at'),
                'missing_data' => $defs->isEmpty(),
            ];
        }

        return [
            'species_with_seasons' => count(array_filter($rows, fn (array $row): bool => $row['has_verified_season'])),
            'species_without_seasons' => count(array_filter($rows, fn (array $row): bool => ! $row['has_verified_season'])),
            'rows' => $rows,
            'note' => 'Missing season records mean unknown, not closed.',
        ];
    }
}
