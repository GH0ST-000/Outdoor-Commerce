<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Legal;

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalCalendarGenerationRun;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Legal\Services\PeriodAvailabilityEvaluator;
use App\Domains\Legal\Services\SeasonDefinitionTransitionService;
use App\Domains\Legal\Services\SeasonDefinitionWriteService;
use App\Domains\Legal\Services\SeasonOccurrenceGenerator;
use App\Domains\Legal\Services\SeasonOverrideTransitionService;
use App\Domains\Legal\Services\SeasonOverrideWriteService;
use App\Domains\Legal\Services\SeasonPresenter;
use App\Domains\Legal\Services\SeasonProjectionService;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Legal\AdminCalendarEvaluateRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalSeasonOverrideRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalSeasonRequest;
use App\Http\Requests\Api\V1\Admin\Legal\TransitionLegalRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminLegalSeasonController
{
    use AuthorizesRequests;

    public function dashboard(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSeasons', LegalSeasonDefinition::class);

        return $this->ok($request, $presenter->dashboard());
    }

    public function index(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSeasons', LegalSeasonDefinition::class);
        $query = LegalSeasonDefinition::query()->with(['species', 'rule'])->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->string('activity_type'));
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalSeasonDefinition $row): array => $presenter->definitionAdmin($row))->values()->all(), $paginator);
    }

    public function store(StoreLegalSeasonRequest $request, SeasonDefinitionWriteService $writer, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('createSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();
        $definition = $writer->create($request->validated(), $actor);

        return $this->ok($request, $presenter->definitionAdmin($definition), 201);
    }

    public function show(Request $request, string $season, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSeasons', LegalSeasonDefinition::class);

        return $this->ok($request, $presenter->definitionAdmin($this->findSeason($season)));
    }

    public function update(StoreLegalSeasonRequest $request, string $season, SeasonDefinitionWriteService $writer, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('updateSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();
        $definition = $writer->update($this->findSeason($season), $request->validated(), $actor);

        return $this->ok($request, $presenter->definitionAdmin($definition));
    }

    public function submitReview(TransitionLegalRequest $request, string $season, SeasonDefinitionTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->definitionAdmin($transitions->submitReview($this->findSeason($season), $actor)));
    }

    public function approve(TransitionLegalRequest $request, string $season, SeasonDefinitionTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->definitionAdmin($transitions->approve($this->findSeason($season), $actor)));
    }

    public function publish(TransitionLegalRequest $request, string $season, SeasonDefinitionTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('publishSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->definitionAdmin($transitions->publish($this->findSeason($season), $actor)));
    }

    public function reject(TransitionLegalRequest $request, string $season, SeasonDefinitionTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->definitionAdmin($transitions->reject($this->findSeason($season), $actor, $request->string('reason')->toString())));
    }

    public function supersede(TransitionLegalRequest $request, string $season, SeasonDefinitionTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('supersedeSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->definitionAdmin($transitions->supersede($this->findSeason($season), $actor, $request->string('reason')->toString())));
    }

    public function generate(Request $request, string $season, SeasonProjectionService $projections): JsonResponse
    {
        $this->authorize('generateSeasons', LegalSeasonDefinition::class);
        $definition = $this->findSeason($season);
        $result = $projections->generateDefinition($definition);

        return $this->ok($request, $result);
    }

    public function preview(Request $request, string $season, SeasonOccurrenceGenerator $generator, SeasonProjectionService $projections): JsonResponse
    {
        $this->authorize('previewCalendar', LegalSeasonDefinition::class);
        $definition = $this->findSeason($season);
        $horizon = $projections->horizon();
        $result = $generator->generate($definition, $horizon['from_year'], $horizon['through_year'], false);
        $result['skipped_leap_years'] = $generator->skippedLeapYears($definition, $horizon['from_year'], $horizon['through_year']);

        return $this->ok($request, $result);
    }

    public function occurrences(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSeasons', LegalSeasonDefinition::class);
        $query = LegalSeasonOccurrence::query()->with('definition')->orderByDesc('starts_at');
        if ($request->filled('is_current')) {
            $query->where('is_current', $request->boolean('is_current'));
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalSeasonOccurrence $row): array => $presenter->occurrenceAdmin($row))->values()->all(), $paginator);
    }

    public function showOccurrence(Request $request, string $occurrence, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSeasons', LegalSeasonDefinition::class);
        $row = LegalSeasonOccurrence::query()->with('definition')->find($occurrence)
            ?? throw LegalException::notFound('Season occurrence');

        return $this->ok($request, $presenter->occurrenceAdmin($row));
    }

    public function regenerate(Request $request, SeasonProjectionService $projections): JsonResponse
    {
        $this->authorize('generateSeasons', LegalSeasonDefinition::class);
        /** @var User $actor */
        $actor = $request->user();
        $result = $projections->generatePublished(
            triggeredByType: 'admin',
            triggeredById: $actor->id,
            dryRun: $request->boolean('dry_run'),
        );

        return $this->ok($request, $result);
    }

    public function overrides(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewOverrides', LegalSeasonOverride::class);
        $paginator = LegalSeasonOverride::query()->with(['definition', 'rule'])->orderByDesc('id')->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalSeasonOverride $row): array => $presenter->overrideAdmin($row))->values()->all(), $paginator);
    }

    public function storeOverride(StoreLegalSeasonOverrideRequest $request, SeasonOverrideWriteService $writer, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('createOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($writer->create($request->validated(), $actor)), 201);
    }

    public function showOverride(Request $request, string $override, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewOverrides', LegalSeasonOverride::class);

        return $this->ok($request, $presenter->overrideAdmin($this->findOverride($override)));
    }

    public function updateOverride(StoreLegalSeasonOverrideRequest $request, string $override, SeasonOverrideWriteService $writer, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('createOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($writer->update($this->findOverride($override), $request->validated(), $actor)));
    }

    public function submitOverride(TransitionLegalRequest $request, string $override, SeasonOverrideTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($transitions->submitReview($this->findOverride($override), $actor)));
    }

    public function approveOverride(TransitionLegalRequest $request, string $override, SeasonOverrideTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($transitions->approve($this->findOverride($override), $actor)));
    }

    public function publishOverride(TransitionLegalRequest $request, string $override, SeasonOverrideTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('publishOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($transitions->publish($this->findOverride($override), $actor)));
    }

    public function rejectOverride(TransitionLegalRequest $request, string $override, SeasonOverrideTransitionService $transitions, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewOverrides', LegalSeasonOverride::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->overrideAdmin($transitions->reject($this->findOverride($override), $actor, $request->string('reason')->toString())));
    }

    public function generationRuns(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewGenerationRuns', LegalCalendarGenerationRun::class);
        $paginator = LegalCalendarGenerationRun::query()->orderByDesc('id')->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalCalendarGenerationRun $run): array => $presenter->runAdmin($run))->values()->all(), $paginator);
    }

    public function showGenerationRun(Request $request, string $run, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewGenerationRuns', LegalCalendarGenerationRun::class);
        $row = LegalCalendarGenerationRun::query()->find($run)
            ?? throw LegalException::notFound('Generation run');

        return $this->ok($request, $presenter->runAdmin($row));
    }

    public function evaluatePreview(AdminCalendarEvaluateRequest $request, PeriodAvailabilityEvaluator $evaluator): JsonResponse
    {
        $this->authorize('previewCalendar', LegalSeasonDefinition::class);
        $speciesId = null;
        if ($request->filled('species_id')) {
            $speciesId = Species::query()->where('public_id', $request->string('species_id'))->value('id');
        }
        $query = new PeriodAvailabilityQueryData(
            activityType: LegalActivityType::from($request->string('activity')->toString()),
            period: $request->period(),
            mode: AvailabilityMode::tryFrom((string) $request->input('mode', 'timeline')) ?? AvailabilityMode::Timeline,
            jurisdictionCode: (string) ($request->input('jurisdiction') ?: config('legal.default_jurisdiction')),
            regionCode: $request->input('region'),
            speciesId: $speciesId,
            includeTrace: true,
        );

        return $this->ok($request, $evaluator->compute($query, (string) $request->header('X-Locale', 'ka'), 1, 50));
    }

    public function coverage(Request $request, SeasonPresenter $presenter): JsonResponse
    {
        $this->authorize('viewCoverage', LegalSeasonDefinition::class);

        return $this->ok($request, $presenter->coverage());
    }

    private function findSeason(string $id): LegalSeasonDefinition
    {
        return LegalSeasonDefinition::query()->where('public_id', $id)->first()
            ?? throw LegalException::notFound('Season definition');
    }

    private function findOverride(string $id): LegalSeasonOverride
    {
        return LegalSeasonOverride::query()->where('public_id', $id)->first()
            ?? throw LegalException::notFound('Season override');
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->integer('per_page', 10);

        return min(max($requested, 1), (int) config('legal.pagination.max', 50));
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function ok(Request $request, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ], $status)->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  list<mixed>  $data
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    private function page(Request $request, array $data, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
