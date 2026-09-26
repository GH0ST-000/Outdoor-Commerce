<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Spatial;

use App\Domains\Geography\DTOs\PointLookupQueryData;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Jobs\ImportSpatialDatasetVersionJob;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialImport;
use App\Domains\Geography\Models\SpatialImportError;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Services\GeoJsonValidator;
use App\Domains\Geography\Services\SpatialAuditRecorder;
use App\Domains\Geography\Services\SpatialDatasetWriteService;
use App\Domains\Geography\Services\SpatialFileIngestionService;
use App\Domains\Geography\Services\SpatialImportService;
use App\Domains\Geography\Services\SpatialPresenter;
use App\Domains\Geography\Services\SpatialPublicationService;
use App\Domains\Geography\Services\SpatialQueryService;
use App\Domains\Geography\Services\SpatialSourceWriteService;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\DTOs\SpatialEvaluationQueryData;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Services\SpatialLegalEvaluator;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Spatial\AssignSpatialRuleRequest;
use App\Http\Requests\Api\V1\Admin\Spatial\StoreSpatialDatasetRequest;
use App\Http\Requests\Api\V1\Admin\Spatial\StoreSpatialSourceRequest;
use App\Http\Requests\Api\V1\Admin\Spatial\UploadSpatialDatasetVersionRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminSpatialController
{
    use AuthorizesRequests;

    public function dashboard(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);

        return $this->ok($request, $presenter->dashboard());
    }

    public function coverage(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);

        return $this->ok($request, $presenter->coverage());
    }

    public function sources(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSources', SpatialSource::class);
        $query = SpatialSource::query()->orderByDesc('id');
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->string('verification_status'));
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (SpatialSource $row): array => $presenter->sourceAdmin($row))->values()->all(), $paginator);
    }

    public function storeSource(StoreSpatialSourceRequest $request, SpatialSourceWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('manageSources', SpatialSource::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($writer->create($request->validated(), $actor)), 201);
    }

    public function showSource(Request $request, string $source, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewSources', SpatialSource::class);

        return $this->ok($request, $presenter->sourceAdmin($this->findSource($source)));
    }

    public function updateSource(StoreSpatialSourceRequest $request, string $source, SpatialSourceWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('manageSources', SpatialSource::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($writer->update($this->findSource($source), $request->validated(), $actor)));
    }

    public function verifySource(Request $request, string $source, SpatialSourceWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('verifySources', SpatialSource::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->sourceAdmin($writer->verify($this->findSource($source), $actor)));
    }

    public function datasets(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);
        $query = SpatialDataset::query()->with('source')->orderByDesc('id');
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (SpatialDataset $row): array => $presenter->datasetAdmin($row))->values()->all(), $paginator);
    }

    public function storeDataset(StoreSpatialDatasetRequest $request, SpatialDatasetWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('manageDatasets', SpatialDataset::class);
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $source = $this->findSource((string) $payload['spatial_source_id']);
        $payload['spatial_source_id'] = $source->id;

        return $this->ok($request, $presenter->datasetAdmin($writer->createDataset($payload, $actor)), 201);
    }

    public function showDataset(Request $request, string $dataset, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);

        return $this->ok($request, $presenter->datasetAdmin($this->findDataset($dataset)));
    }

    public function updateDataset(StoreSpatialDatasetRequest $request, string $dataset, SpatialDatasetWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('manageDatasets', SpatialDataset::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->datasetAdmin($writer->updateDataset($this->findDataset($dataset), $request->validated(), $actor)));
    }

    public function versions(Request $request, string $dataset, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);
        $record = $this->findDataset($dataset);
        $paginator = SpatialDatasetVersion::query()->where('spatial_dataset_id', $record->id)->orderByDesc('id')->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (SpatialDatasetVersion $row): array => $presenter->versionAdmin($row))->values()->all(), $paginator);
    }

    public function storeVersion(UploadSpatialDatasetVersionRequest $request, string $dataset, SpatialDatasetWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('uploadVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();
        $file = $request->file('file');
        if ($file === null) {
            throw SpatialException::fileRejected('A GeoJSON file is required.');
        }

        return $this->ok($request, $presenter->versionAdmin($writer->uploadVersion($this->findDataset($dataset), $file, $request->validated(), $actor)), 201);
    }

    public function showVersion(Request $request, string $version, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);

        return $this->ok($request, $presenter->versionAdmin($this->findVersion($version)));
    }

    public function downloadVersion(Request $request, string $version, SpatialAuditRecorder $audit): StreamedResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);
        $record = $this->findVersion($version);
        /** @var User $actor */
        $actor = $request->user();
        $audit->record(AuditEvent::SpatialSourceFileDownloaded, $actor, 'spatial_dataset_version', $record->public_id);

        return Storage::disk($record->storage_disk)->download($record->storage_path, $record->original_filename, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function previewVersion(Request $request, string $version, SpatialDatasetWriteService $writer): JsonResponse
    {
        $this->authorize('validateVersions', SpatialDatasetVersion::class);

        return $this->ok($request, $writer->preview($this->findVersion($version)));
    }

    public function validateVersion(Request $request, string $version, SpatialDatasetWriteService $writer, GeoJsonValidator $validator, SpatialFileIngestionService $files, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('validateVersions', SpatialDatasetVersion::class);
        $record = $this->findVersion($version);
        $contents = $files->read($record->storage_disk, $record->storage_path);
        $parsed = $validator->parse($contents, (string) $record->source_crs);
        $record->validation_summary = [
            'feature_count' => $parsed['feature_count'],
            'vertex_count' => $parsed['vertex_count'],
            'geometry_types' => $parsed['geometry_types'],
            'property_keys' => $parsed['property_keys'],
            'bounds' => $parsed['bounds'],
            'warnings' => $parsed['warnings'],
        ];
        $record->review_status = $record->review_status === SpatialReviewStatus::Draft
            ? SpatialReviewStatus::Validating
            : $record->review_status;
        $record->save();

        return $this->ok($request, $presenter->versionAdmin($record));
    }

    public function mapVersion(Request $request, string $version, SpatialDatasetWriteService $writer, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('importVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();
        $mapping = $request->validate([
            'external_identifier' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:64'],
            'zone_type' => ['nullable', 'string', 'max:64'],
            'region_code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->ok($request, $presenter->versionAdmin($writer->saveMapping($this->findVersion($version), $mapping, $actor)));
    }

    public function importVersion(Request $request, string $version, SpatialImportService $imports, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('importVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();
        $record = $this->findVersion($version);
        $import = $imports->start($record, $actor);
        if (app()->environment('testing')) {
            $imports->run($import);
        } else {
            ImportSpatialDatasetVersionJob::dispatch($import->id);
        }

        return $this->ok($request, $presenter->importAdmin($import->refresh()));
    }

    public function submitVersion(Request $request, string $version, SpatialPublicationService $publication, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($publication->submitReview($this->findVersion($version), $actor)));
    }

    public function approveVersion(Request $request, string $version, SpatialPublicationService $publication, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($publication->approve($this->findVersion($version), $actor)));
    }

    public function publishVersion(Request $request, string $version, SpatialPublicationService $publication, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('publishVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->versionAdmin($publication->publish($this->findVersion($version), $actor)));
    }

    public function rejectVersion(Request $request, string $version, SpatialPublicationService $publication, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('reviewVersions', SpatialDatasetVersion::class);
        /** @var User $actor */
        $actor = $request->user();
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->ok($request, $presenter->versionAdmin($publication->reject($this->findVersion($version), $actor, $reason)));
    }

    public function zones(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewZones', SpatialZone::class);
        $query = SpatialZone::query()->with('translations')->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $paginator = $query->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (SpatialZone $row): array => $presenter->zoneAdmin($row))->values()->all(), $paginator);
    }

    public function showZone(Request $request, string $zone, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewZones', SpatialZone::class);

        return $this->ok($request, $presenter->zoneAdmin($this->findZone($zone)));
    }

    public function geometryVersions(Request $request, string $zone): JsonResponse
    {
        $this->authorize('viewZones', SpatialZone::class);
        $record = $this->findZone($zone);
        $rows = SpatialZoneGeometryVersion::query()
            ->where('spatial_zone_id', $record->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (SpatialZoneGeometryVersion $row): array => [
                'id' => $row->public_id,
                'status' => $row->status->value,
                'validation_status' => $row->validation_status->value,
                'checksum' => $row->geometry_checksum,
                'vertex_count' => $row->vertex_count,
                'bounding_box' => $row->boundingBox()->toArray(),
                'effective_from' => $row->effective_from->toIso8601String(),
                'published_at' => $row->published_at?->toIso8601String(),
                'srid' => 4326,
            ])
            ->all();

        return $this->ok($request, $rows);
    }

    public function zoneRules(Request $request, string $zone, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewZones', SpatialZone::class);
        $record = $this->findZone($zone);
        $record->load(['assignments.rule']);

        return $this->ok($request, $record->assignments->map(fn (LegalRuleSpatialZone $row): array => $presenter->assignmentPublic($row))->values()->all());
    }

    public function assignRule(AssignSpatialRuleRequest $request, string $zone, SpatialPublicationService $publication, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('assignRules', SpatialZone::class);
        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($request, $presenter->assignmentPublic($publication->assignRule($this->findZone($zone), $request->validated(), $actor)), 201);
    }

    public function imports(Request $request, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);
        $paginator = SpatialImport::query()->orderByDesc('id')->paginate($this->perPage($request));

        return $this->page($request, collect($paginator->items())->map(fn (SpatialImport $row): array => $presenter->importAdmin($row))->values()->all(), $paginator);
    }

    public function showImport(Request $request, string $import, SpatialPresenter $presenter): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);

        return $this->ok($request, $presenter->importAdmin($this->findImport($import)));
    }

    public function conflicts(Request $request): JsonResponse
    {
        $this->authorize('viewConflicts', SpatialDataset::class);
        $paginator = LegalConflict::query()
            ->with(['firstRule', 'secondRule'])
            ->where('detected_by', 'spatial_evaluator')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));
        $rows = collect($paginator->items())->map(static fn (LegalConflict $conflict): array => [
            'id' => $conflict->public_id,
            'type' => $conflict->conflict_type->value,
            'severity' => $conflict->severity->value,
            'status' => $conflict->status->value,
            'first_rule_id' => $conflict->firstRule?->public_id,
            'second_rule_id' => $conflict->secondRule?->public_id,
            'detected_at' => $conflict->detected_at?->toIso8601String(),
            'evidence' => $conflict->evidence,
        ])->values()->all();

        return $this->page($request, $rows, $paginator);
    }

    public function importErrors(Request $request, string $import): JsonResponse
    {
        $this->authorize('viewDatasets', SpatialDataset::class);
        $record = $this->findImport($import);
        $errors = $record->errors()->orderBy('id')->limit(200)->get()->map(fn (SpatialImportError $row): array => [
            'source_feature_identifier' => $row->source_feature_identifier,
            'error_code' => $row->error_code,
            'message' => $row->message,
            'context' => $row->context,
            'resolution_status' => $row->resolution_status->value,
        ])->all();

        return $this->ok($request, $errors);
    }

    public function evaluatePreview(Request $request, SpatialLegalEvaluator $evaluator): JsonResponse
    {
        $this->authorize('previewEvaluate', SpatialDataset::class);
        $data = $request->validate([
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'activity' => ['required', 'string'],
            'at' => ['nullable', 'date'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'jurisdiction' => ['nullable', 'string', 'max:16'],
        ]);

        return $this->ok($request, $evaluator->evaluate(new SpatialEvaluationQueryData(
            longitude: (float) $data['lng'],
            latitude: (float) $data['lat'],
            occurredAt: isset($data['at']) ? Carbon::parse($data['at']) : now(),
            activityType: LegalActivityType::from($data['activity']),
            jurisdictionCode: $data['jurisdiction'] ?? (string) config('spatial.default_jurisdiction'),
            from: $data['from'] ?? null,
            to: $data['to'] ?? null,
        )));
    }

    public function lookupPreview(Request $request, SpatialQueryService $queries): JsonResponse
    {
        $this->authorize('previewEvaluate', SpatialDataset::class);
        $data = $request->validate([
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'at' => ['nullable', 'date'],
            'jurisdiction' => ['nullable', 'string', 'max:16'],
        ]);
        $locale = (string) $request->header('X-Locale', 'ka');

        return $this->ok($request, $queries->lookup(new PointLookupQueryData(
            longitude: (float) $data['lng'],
            latitude: (float) $data['lat'],
            at: isset($data['at']) ? Carbon::parse($data['at']) : now(),
            jurisdictionCode: $data['jurisdiction'] ?? (string) config('spatial.default_jurisdiction'),
        ), $locale));
    }

    private function findSource(string $id): SpatialSource
    {
        return SpatialSource::query()->where('public_id', $id)->first()
            ?? throw SpatialException::notFound('Spatial source');
    }

    private function findDataset(string $id): SpatialDataset
    {
        return SpatialDataset::query()->where('public_id', $id)->first()
            ?? throw SpatialException::notFound('Spatial dataset');
    }

    private function findVersion(string $id): SpatialDatasetVersion
    {
        return SpatialDatasetVersion::query()->where('public_id', $id)->first()
            ?? throw SpatialException::notFound('Spatial dataset version');
    }

    private function findZone(string $id): SpatialZone
    {
        return SpatialZone::query()->where('public_id', $id)->first()
            ?? throw SpatialException::notFound('Spatial zone');
    }

    private function findImport(string $id): SpatialImport
    {
        return SpatialImport::query()->where('public_id', $id)->first()
            ?? throw SpatialException::notFound('Spatial import');
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->integer('per_page', 10);

        return min(max($requested, 1), (int) config('spatial.pagination.max', 50));
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
