<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Species;

use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesRevision;
use App\Domains\Hunting\Queries\AdminSpeciesListQuery;
use App\Domains\Hunting\Services\KnowledgeSourceService;
use App\Domains\Hunting\Services\RestoreSpeciesRevisionService;
use App\Domains\Hunting\Services\SimilarSpeciesService;
use App\Domains\Hunting\Services\SpeciesAliasService;
use App\Domains\Hunting\Services\SpeciesMediaService;
use App\Domains\Hunting\Services\SpeciesPresenter;
use App\Domains\Hunting\Services\SpeciesTransitionService;
use App\Domains\Hunting\Services\SpeciesWriteService;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Species\StoreSpeciesAliasRequest;
use App\Http\Requests\Api\V1\Admin\Species\StoreSpeciesMediaRequest;
use App\Http\Requests\Api\V1\Admin\Species\StoreSpeciesRequest;
use App\Http\Requests\Api\V1\Admin\Species\StoreSpeciesSimilarRequest;
use App\Http\Requests\Api\V1\Admin\Species\StoreSpeciesSourceRequest;
use App\Http\Requests\Api\V1\Admin\Species\TransitionSpeciesRequest;
use App\Http\Requests\Api\V1\Admin\Species\UpdateSpeciesRequest;
use App\Http\Resources\Api\V1\Admin\Species\AdminSpeciesResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSpeciesController
{
    use AuthorizesRequests;

    public function index(Request $request, AdminSpeciesListQuery $query): JsonResponse
    {
        $this->authorize('viewAny', Species::class);
        $paginator = $query->paginate($request->all());

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Species $species): array => $query->listItem($species))->values(),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'request_id' => $this->requestId($request),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreSpeciesRequest $request, SpeciesWriteService $write, SpeciesPresenter $presenter): JsonResponse
    {
        $this->authorize('create', Species::class);
        /** @var User $actor */
        $actor = $request->user();
        $species = $write->create($request->validated(), $actor);

        return (new AdminSpeciesResource($presenter->admin($this->load($species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $speciesPublicId, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('view', $species);

        return (new AdminSpeciesResource($presenter->admin($this->load($species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(UpdateSpeciesRequest $request, string $speciesPublicId, SpeciesWriteService $write, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('update', $species);
        if ($request->hasTaxonomyChanges()) {
            $this->authorize('manageTaxonomy', $species);
        }
        /** @var User $actor */
        $actor = $request->user();
        $species = $write->update($species, $request->validated(), $actor);

        return (new AdminSpeciesResource($presenter->admin($this->load($species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(Request $request, string $speciesPublicId, SpeciesTransitionService $transitions): JsonResponse
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('delete', $species);
        /** @var User $actor */
        $actor = $request->user();
        if ($species->publication_status->isPublic()) {
            $transitions->archive($species, $actor, (string) $request->input('reason', 'Archived instead of hard delete.'), $this->requestId($request), $request->ip(), $request->userAgent());
        } else {
            $species->delete();
        }

        return response()->json(['data' => ['id' => $speciesPublicId], 'meta' => ['request_id' => $this->requestId($request)]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function submitReview(TransitionSpeciesRequest $request, string $speciesPublicId, SpeciesTransitionService $transitions, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        return $this->runTransition($request, $speciesPublicId, 'review', fn (Species $species, User $actor) => $transitions->submitReview($species, $actor, $this->requestId($request), $request->ip(), $request->userAgent()), $presenter);
    }

    public function publish(TransitionSpeciesRequest $request, string $speciesPublicId, SpeciesTransitionService $transitions, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        return $this->runTransition($request, $speciesPublicId, 'publish', fn (Species $species, User $actor) => $transitions->publish($species, $actor, $this->requestId($request), $request->ip(), $request->userAgent()), $presenter);
    }

    public function unpublish(TransitionSpeciesRequest $request, string $speciesPublicId, SpeciesTransitionService $transitions, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        return $this->runTransition($request, $speciesPublicId, 'update', fn (Species $species, User $actor) => $transitions->unpublish($species, $actor, (string) $request->validated('reason'), $this->requestId($request), $request->ip(), $request->userAgent()), $presenter);
    }

    public function archive(TransitionSpeciesRequest $request, string $speciesPublicId, SpeciesTransitionService $transitions, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        return $this->runTransition($request, $speciesPublicId, 'archive', fn (Species $species, User $actor) => $transitions->archive($species, $actor, (string) $request->validated('reason'), $this->requestId($request), $request->ip(), $request->userAgent()), $presenter);
    }

    public function aliases(StoreSpeciesAliasRequest $request, string $speciesPublicId, SpeciesAliasService $aliases, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('manageAliases', $species);
        $aliases->create($species, $request->validated());

        return (new AdminSpeciesResource($presenter->admin($this->load($species->fresh() ?? $species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function sources(StoreSpeciesSourceRequest $request, string $speciesPublicId, KnowledgeSourceService $sources, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('manageSources', Species::class);
        /** @var User $actor */
        $actor = $request->user();
        $source = $sources->create($request->validated(), $actor);
        $sources->attachToSpecies($species, $source, $request->validated());

        return (new AdminSpeciesResource($presenter->admin($this->load($species->fresh() ?? $species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function similar(StoreSpeciesSimilarRequest $request, string $speciesPublicId, SimilarSpeciesService $similar, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('update', $species);
        $similar->create($species, $request->validated());

        return (new AdminSpeciesResource($presenter->admin($this->load($species->fresh() ?? $species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function media(StoreSpeciesMediaRequest $request, string $speciesPublicId, SpeciesMediaService $media, SpeciesPresenter $presenter): JsonResponse
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('manageMedia', $species);
        /** @var User $actor */
        $actor = $request->user();
        $media->attach($species, $request->uploadedFiles(), $actor, $request->validated(), $this->requestId($request), $request->ip(), $request->userAgent());

        return (new AdminSpeciesResource($presenter->admin($this->load($species->fresh() ?? $species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(202)
            ->header('Cache-Control', 'private, no-store');
    }

    public function revisions(Request $request, string $speciesPublicId): JsonResponse
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('viewRevisions', $species);
        $rows = $species->revisions()->orderByDesc('revision_number')->get()->map(static fn (SpeciesRevision $row): array => [
            'revision_number' => $row->revision_number,
            'change_summary' => $row->change_summary,
            'actor_id' => $row->actor_id,
            'created_at' => $row->created_at?->toIso8601String(),
            'snapshot' => $row->snapshot,
        ]);

        return response()->json([
            'data' => $rows,
            'meta' => ['request_id' => $this->requestId($request)],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function restoreRevision(Request $request, string $speciesPublicId, int $revision, RestoreSpeciesRevisionService $restore, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($speciesPublicId);
        $this->authorize('restoreRevision', $species);
        /** @var User $actor */
        $actor = $request->user();
        $species = $restore->restore($species, $revision, $actor);

        return (new AdminSpeciesResource($presenter->admin($this->load($species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    /**
     * @param  callable(Species, User): Species  $callback
     */
    private function runTransition(Request $request, string $publicId, string $ability, callable $callback, SpeciesPresenter $presenter): AdminSpeciesResource
    {
        $species = $this->find($publicId);
        $this->authorize($ability, $species);
        /** @var User $actor */
        $actor = $request->user();
        $species = $callback($species, $actor);

        return (new AdminSpeciesResource($presenter->admin($this->load($species))))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function find(string $publicId): Species
    {
        $species = Species::withTrashed()->where('public_id', $publicId)->first();
        if ($species === null) {
            throw SpeciesException::notFound();
        }

        return $species;
    }

    private function load(Species $species): Species
    {
        return $species->load([
            'translations',
            'aliases',
            'characteristic',
            'habitatLinks.habitat',
            'identificationTraits',
            'similarFrom.similarSpecies',
            'conservationAssessments',
            'citations.source',
            'mediaAttachments.asset.derivatives',
            'mediaAttachments.translations',
            'revisions',
        ]);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
