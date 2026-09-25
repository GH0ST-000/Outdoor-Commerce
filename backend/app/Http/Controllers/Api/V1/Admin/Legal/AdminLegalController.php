<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Legal;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalChangeDetection;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Services\LegalAuditRecorder;
use App\Domains\Legal\Services\LegalDocumentService;
use App\Domains\Legal\Services\LegalPresenter;
use App\Domains\Legal\Services\LegalProvisionService;
use App\Domains\Legal\Services\LegalRuleEvaluator;
use App\Domains\Legal\Services\LegalRuleTransitionService;
use App\Domains\Legal\Services\LegalRuleWriteService;
use App\Domains\Legal\Services\LegalSourceMonitor;
use App\Domains\Legal\Services\LegalSourceService;
use App\Domains\Legal\Services\LegalVersionService;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Legal\EvaluateLegalPreviewRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalDocumentRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalProvisionRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalRuleRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalSourceRequest;
use App\Http\Requests\Api\V1\Admin\Legal\StoreLegalVersionRequest;
use App\Http\Requests\Api\V1\Admin\Legal\TransitionLegalRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminLegalController
{
    use AuthorizesRequests;

    public function dashboard(Request $request, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('viewAny', LegalSource::class);

        return $this->ok($request, $presenter->dashboard());
    }

    public function sources(Request $request, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('viewAny', LegalSource::class);
        $query = LegalSource::query()->with('authority')->orderBy('name');
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->string('verification_status'));
        }
        if ($request->filled('jurisdiction_code')) {
            $query->where('jurisdiction_code', $request->string('jurisdiction_code'));
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalSource $source): array => $presenter->sourceAdmin($source))->values()->all(), $paginator);
    }

    public function storeSource(StoreLegalSourceRequest $request, LegalSourceService $sources, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('manageSources', LegalSource::class);
        /** @var User $actor */
        $actor = $request->user();
        $source = $sources->create($request->validated(), $actor);

        return $this->ok($request, $presenter->sourceAdmin($source->load('authority')), 201);
    }

    public function showSource(Request $request, string $source, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('view', $record);

        return $this->ok($request, $presenter->sourceAdmin($record->load('authority')));
    }

    public function updateSource(StoreLegalSourceRequest $request, string $source, LegalSourceService $sources, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('manageSources', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($sources->update($record, $request->validated(), $actor)->load('authority')));
    }

    public function submitSourceReview(Request $request, string $source, LegalSourceService $sources, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('manageSources', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($sources->submitReview($record, $actor)->load('authority')));
    }

    public function verifySource(Request $request, string $source, LegalSourceService $sources, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('verifySources', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($sources->verify($record, $actor)->load('authority')));
    }

    public function rejectSource(TransitionLegalRequest $request, string $source, LegalSourceService $sources, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('verifySources', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($sources->reject($record, $actor, (string) $request->validated('reason'))->load('authority')));
    }

    public function checkSource(Request $request, string $source, LegalSourceMonitor $monitor): JsonResponse
    {
        $record = $this->findSource($source);
        $this->authorize('manageSources', $record);
        /** @var User $actor */
        $actor = $request->user();
        $detection = $monitor->check($record, $actor);

        return $this->ok($request, [
            'changed' => $detection !== null,
            'detection_id' => $detection?->public_id,
        ]);
    }

    public function storeDocument(StoreLegalDocumentRequest $request, LegalDocumentService $documents, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('manageDocuments', LegalDocument::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->documentAdmin($documents->create($request->validated(), $actor)), 201);
    }

    public function documents(Request $request, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('viewAny', LegalDocument::class);
        $paginator = LegalDocument::query()->with(['source', 'currentVersion'])->orderBy('title')->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalDocument $document): array => $presenter->documentAdmin($document))->values()->all(), $paginator);
    }

    public function showDocument(Request $request, string $document, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findDocument($document);
        $this->authorize('view', $record);

        return $this->ok($request, $presenter->documentAdmin($record));
    }

    public function documentVersions(Request $request, string $document, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findDocument($document);
        $this->authorize('view', $record);

        return $this->ok($request, $record->versions()->orderByDesc('id')->get()
            ->map(fn (LegalDocumentVersion $version): array => $presenter->versionAdmin($version))->values()->all());
    }

    public function updateDocument(StoreLegalDocumentRequest $request, string $document, LegalDocumentService $documents, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findDocument($document);
        $this->authorize('manageDocuments', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->documentAdmin($documents->update($record, $request->validated(), $actor)));
    }

    public function storeVersion(StoreLegalVersionRequest $request, string $document, LegalVersionService $versions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findDocument($document);
        $this->authorize('uploadVersions', $record);
        /** @var User $actor */
        $actor = $request->user();
        $file = $request->file('file');
        $uploaded = is_array($file) ? null : $file;

        return $this->ok($request, $presenter->versionAdmin($versions->create($record, $request->validated(), $actor, $uploaded)), 201);
    }

    public function showVersion(Request $request, string $version, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('view', $record);

        return $this->ok($request, $presenter->versionAdmin($record));
    }

    public function submitVersion(Request $request, string $version, LegalVersionService $versions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('uploadVersions', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($versions->submitReview($record, $actor)));
    }

    public function approveVersion(Request $request, string $version, LegalVersionService $versions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('reviewVersions', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($versions->approve($record, $actor)));
    }

    public function rejectVersion(TransitionLegalRequest $request, string $version, LegalVersionService $versions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('reviewVersions', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($versions->reject($record, $actor, (string) $request->validated('reason'))));
    }

    public function downloadVersion(Request $request, string $version, LegalVersionService $versions): StreamedResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('view', $record);
        $versions->assertCanDownload($record);
        $disk = (string) $record->storage_disk;
        $path = (string) $record->storage_path;
        $name = $record->original_filename ?: 'legal-document';

        return Storage::disk($disk)->download($path, $name, [
            'Content-Type' => $record->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function provisions(Request $request, string $version, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('view', $record);
        $items = $record->provisions()->orderBy('sort_order')->get()
            ->map(fn (LegalProvision $provision): array => $presenter->provisionAdmin($provision))->values()->all();

        return $this->ok($request, $items);
    }

    public function showProvision(Request $request, string $provision, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findProvision($provision);
        $this->authorize('view', $record);

        return $this->ok($request, $presenter->provisionAdmin($record));
    }

    public function storeProvision(StoreLegalProvisionRequest $request, string $version, LegalProvisionService $provisions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findVersion($version);
        $this->authorize('manageProvisions', LegalProvision::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->provisionAdmin($provisions->create($record, $request->validated(), $actor)), 201);
    }

    public function updateProvision(StoreLegalProvisionRequest $request, string $provision, LegalProvisionService $provisions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findProvision($provision);
        $this->authorize('manageProvisions', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->provisionAdmin($provisions->update($record, $request->validated(), $actor)));
    }

    public function destroyProvision(Request $request, string $provision, LegalProvisionService $provisions): JsonResponse
    {
        $record = $this->findProvision($provision);
        $this->authorize('manageProvisions', $record);
        /** @var User $actor */
        $actor = $request->user();
        $provisions->delete($record, $actor);

        return $this->ok($request, ['deleted' => true]);
    }

    public function rules(Request $request, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('viewAny', LegalRule::class);
        $query = LegalRule::query()->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->string('activity_type'));
        }
        if ($request->filled('jurisdiction_code')) {
            $query->where('jurisdiction_code', $request->string('jurisdiction_code'));
        }
        $sort = (string) $request->query('sort', 'id');
        $allowedSorts = ['id', 'title', 'status', 'effective_from', 'published_at'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->reorder()->orderBy($sort, $request->query('direction') === 'asc' ? 'asc' : 'desc');
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (LegalRule $rule): array => $presenter->ruleAdmin($rule))->values()->all(), $paginator);
    }

    public function storeRule(StoreLegalRuleRequest $request, LegalRuleWriteService $write, LegalPresenter $presenter): JsonResponse
    {
        $this->authorize('createRules', LegalRule::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($write->create($request->validated(), $actor)), 201);
    }

    public function showRule(Request $request, string $rule, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('view', $record);

        return $this->ok($request, $presenter->ruleAdmin($record));
    }

    public function updateRule(StoreLegalRuleRequest $request, string $rule, LegalRuleWriteService $write, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('updateRules', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($write->update($record, $request->validated(), $actor)));
    }

    public function submitRule(Request $request, string $rule, LegalRuleTransitionService $transitions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('reviewRules', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($transitions->submitReview($record, $actor)));
    }

    public function approveRule(Request $request, string $rule, LegalRuleTransitionService $transitions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('reviewRules', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($transitions->approve($record, $actor)));
    }

    public function publishRule(Request $request, string $rule, LegalRuleTransitionService $transitions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('publish', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($transitions->publish($record, $actor)));
    }

    public function rejectRule(TransitionLegalRequest $request, string $rule, LegalRuleTransitionService $transitions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('reviewRules', $record);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($transitions->reject($record, $actor, (string) $request->validated('reason'))));
    }

    public function supersedeRule(TransitionLegalRequest $request, string $rule, LegalRuleTransitionService $transitions, LegalPresenter $presenter): JsonResponse
    {
        $record = $this->findRule($rule);
        $this->authorize('supersede', LegalRule::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->ruleAdmin($transitions->supersede($record, $actor, (string) $request->validated('reason'))));
    }

    public function previewEvaluate(EvaluateLegalPreviewRequest $request, string $rule, LegalRuleEvaluator $evaluator): JsonResponse
    {
        $this->findRule($rule);
        $this->authorize('viewAny', LegalRule::class);
        $data = $request->facts();

        return $this->ok($request, $evaluator->evaluate($data) + ['preview' => true, 'disclaimer' => (string) config('legal.evaluation.disclaimer_key')]);
    }

    public function previewEvaluateStandalone(EvaluateLegalPreviewRequest $request, LegalRuleEvaluator $evaluator): JsonResponse
    {
        $this->authorize('viewAny', LegalRule::class);

        return $this->ok($request, $evaluator->evaluate($request->facts()) + [
            'preview' => true,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $this->authorize('viewConflicts', LegalConflict::class);
        $paginator = LegalConflict::query()->with(['firstRule', 'secondRule'])->orderByDesc('id')->paginate($this->perPage($request));
        $rows = collect($paginator->items())->map(fn (LegalConflict $conflict): array => [
            'id' => $conflict->public_id,
            'type' => $conflict->conflict_type->value,
            'severity' => $conflict->severity->value,
            'status' => $conflict->status->value,
            'first_rule_id' => $conflict->firstRule?->public_id,
            'second_rule_id' => $conflict->secondRule?->public_id,
            'detected_at' => $conflict->detected_at?->toIso8601String(),
        ])->values()->all();

        return $this->page($request, $rows, $paginator);
    }

    public function showConflict(Request $request, string $conflict): JsonResponse
    {
        $record = LegalConflict::query()->with(['firstRule', 'secondRule'])->where('public_id', $conflict)->first()
            ?? throw LegalException::notFound('Legal conflict');
        $this->authorize('viewConflicts', $record);

        return $this->ok($request, [
            'id' => $record->public_id,
            'type' => $record->conflict_type->value,
            'severity' => $record->severity->value,
            'status' => $record->status->value,
            'first_rule_id' => $record->firstRule?->public_id,
            'second_rule_id' => $record->secondRule?->public_id,
            'detected_at' => $record->detected_at?->toIso8601String(),
            'resolution' => $record->resolution,
            'evidence' => $record->evidence,
        ]);
    }

    public function resolveConflict(TransitionLegalRequest $request, string $conflict, LegalAuditRecorder $audit): JsonResponse
    {
        $record = LegalConflict::query()->where('public_id', $conflict)->first() ?? throw LegalException::notFound('Legal conflict');
        $this->authorize('resolveConflicts', $record);
        /** @var User $actor */
        $actor = $request->user();
        $record->status = LegalConflictStatus::Resolved;
        $record->resolution = (string) $request->validated('reason');
        $record->resolved_by = $actor->id;
        $record->resolved_at = now();
        $record->save();
        $audit->record(AuditEvent::LegalConflictResolved, $actor, 'legal_conflict', $record->public_id, null, ['resolution' => $record->resolution]);

        return $this->ok($request, ['id' => $record->public_id, 'status' => $record->status->value]);
    }

    public function detections(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LegalSource::class);
        $paginator = LegalChangeDetection::query()->with('source')->orderByDesc('id')->paginate($this->perPage($request));
        $rows = collect($paginator->items())->map(fn (LegalChangeDetection $detection): array => [
            'id' => $detection->public_id,
            'source_id' => $detection->source?->public_id,
            'status' => $detection->status->value,
            'signal' => $detection->signal,
            'detected_at' => $detection->detected_at?->toIso8601String(),
        ])->values()->all();

        return $this->page($request, $rows, $paginator);
    }

    public function confirmDetection(TransitionLegalRequest $request, string $detection, LegalSourceMonitor $monitor): JsonResponse
    {
        $record = LegalChangeDetection::query()->where('public_id', $detection)->first() ?? throw LegalException::notFound('Change detection');
        $this->authorize('manageSources', LegalSource::class);
        /** @var User $actor */
        $actor = $request->user();
        $monitor->confirm($record, $actor, (string) $request->validated('reason'));

        return $this->ok($request, ['id' => $record->public_id, 'status' => $record->fresh()?->status->value]);
    }

    public function dismissDetection(TransitionLegalRequest $request, string $detection, LegalSourceMonitor $monitor): JsonResponse
    {
        $record = LegalChangeDetection::query()->where('public_id', $detection)->first() ?? throw LegalException::notFound('Change detection');
        $this->authorize('manageSources', LegalSource::class);
        /** @var User $actor */
        $actor = $request->user();
        $monitor->dismiss($record, $actor, (string) $request->validated('reason'));

        return $this->ok($request, ['id' => $record->public_id, 'status' => $record->fresh()?->status->value]);
    }

    public function authorities(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LegalSource::class);
        $rows = LegalAuthority::query()->orderBy('name')->get()->map(fn (LegalAuthority $authority): array => [
            'id' => $authority->public_id,
            'name' => $authority->name,
            'authority_type' => $authority->authority_type->value,
            'is_fictional' => $authority->is_fictional,
        ])->all();

        return $this->ok($request, $rows);
    }

    public function storeAuthority(Request $request): JsonResponse
    {
        $this->authorize('manageSources', LegalSource::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'authority_type' => ['required', 'string', 'max:32'],
            'jurisdiction_code' => ['nullable', 'string', 'max:16'],
            'official_website_url' => ['nullable', 'url', 'max:2048'],
            'is_fictional' => ['sometimes', 'boolean'],
        ]);
        $authority = LegalAuthority::query()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.substr((string) Str::uuid(), 0, 8),
            'authority_type' => $validated['authority_type'],
            'jurisdiction_code' => $validated['jurisdiction_code'] ?? 'GE',
            'country_code' => 'GE',
            'official_website_url' => $validated['official_website_url'] ?? null,
            'is_fictional' => (bool) ($validated['is_fictional'] ?? true),
            'is_active' => true,
        ]);

        return $this->ok($request, [
            'id' => $authority->public_id,
            'name' => $authority->name,
            'is_fictional' => $authority->is_fictional,
        ], 201);
    }

    private function findSource(string $id): LegalSource
    {
        return LegalSource::query()->where('public_id', $id)->first() ?? throw LegalException::notFound('Legal source');
    }

    private function findDocument(string $id): LegalDocument
    {
        return LegalDocument::query()->where('public_id', $id)->first() ?? throw LegalException::notFound('Legal document');
    }

    private function findVersion(string $id): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->where('public_id', $id)->first() ?? throw LegalException::notFound('Legal version');
    }

    private function findProvision(string $id): LegalProvision
    {
        return LegalProvision::query()->where('public_id', $id)->first() ?? throw LegalException::notFound('Legal provision');
    }

    private function findRule(string $id): LegalRule
    {
        return LegalRule::query()->where('public_id', $id)->first() ?? throw LegalException::notFound('Legal rule');
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
