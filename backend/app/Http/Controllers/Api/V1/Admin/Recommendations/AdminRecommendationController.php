<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Recommendations;

use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Enums\ContextDimension;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Jobs\BulkAssignProductContextJob;
use App\Domains\Recommendations\Models\ContextTaxonomyTerm;
use App\Domains\Recommendations\Models\ContextTaxonomyTermTranslation;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use App\Domains\Recommendations\Models\RecommendationMerchandisingRule;
use App\Domains\Recommendations\Models\RecommendationProfile;
use App\Domains\Recommendations\Models\RecommendationSimulation;
use App\Domains\Recommendations\Queries\RecommendationCoverageQuery;
use App\Domains\Recommendations\Services\AssignmentWriteService;
use App\Domains\Recommendations\Services\ContextualProductRecommender;
use App\Domains\Recommendations\Services\MerchandisingWriteService;
use App\Domains\Recommendations\Services\ProfileWorkflowService;
use App\Domains\Recommendations\Services\RecommendationAuditRecorder;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AdminRecommendationController extends Controller
{
    public function profiles(Request $request): JsonResponse
    {
        $perPage = min(10, max(1, (int) $request->integer('per_page', 10)));
        $profiles = RecommendationProfile::query()->orderByDesc('id')->paginate($perPage);

        return $this->ok($request, $profiles);
    }

    public function showProfile(Request $request, string $profile): JsonResponse
    {
        return $this->ok($request, $this->profile($profile)->load('weights'));
    }

    public function storeProfile(Request $request, ProfileWorkflowService $workflow): JsonResponse
    {
        $data = $this->profilePayload($request);
        $profile = $workflow->create($this->actor($request), $data['name'], RecommendationPlacement::from($data['placement']), $data['configuration'], $data['weights']);

        return $this->ok($request, $profile, 201);
    }

    public function updateProfile(Request $request, string $profile, ProfileWorkflowService $workflow): JsonResponse
    {
        $data = $this->profilePayload($request);
        $updated = $workflow->update($this->actor($request), $this->profile($profile), $data['configuration'], $data['weights'], $data['name']);

        return $this->ok($request, $updated);
    }

    public function submit(Request $request, string $profile, ProfileWorkflowService $workflow): JsonResponse
    {
        return $this->ok($request, $workflow->submit($this->actor($request), $this->profile($profile)));
    }

    public function approve(Request $request, string $profile, ProfileWorkflowService $workflow): JsonResponse
    {
        return $this->ok($request, $workflow->approve($this->actor($request), $this->profile($profile)));
    }

    public function publish(Request $request, string $profile, ProfileWorkflowService $workflow): JsonResponse
    {
        return $this->ok($request, $workflow->publish($this->actor($request), $this->profile($profile)));
    }

    public function supersede(Request $request, string $profile, ProfileWorkflowService $workflow): JsonResponse
    {
        return $this->ok($request, $workflow->supersede($this->actor($request), $this->profile($profile)));
    }

    public function coverage(Request $request, RecommendationCoverageQuery $coverage): JsonResponse
    {
        return $this->ok($request, $coverage->execute());
    }

    public function assignments(Request $request, Product $product): JsonResponse
    {
        $rows = ProductContextAssignment::query()->with('term.translations')->where('product_id', $product->id)->orderByDesc('id')->paginate(10);

        return $this->ok($request, $rows);
    }

    public function storeAssignment(Request $request, Product $product, AssignmentWriteService $assignments): JsonResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'uuid'],
            'variant_id' => ['nullable', 'string', 'max:64'],
            'assignment_type' => ['required', 'in:required_match,preferred_match,supported,neutral,excluded'],
            'source_type' => ['required', 'in:manual_verified,manufacturer_specification,catalog_attribute,legal_rule,species_knowledge,system_derived'],
            'source_reference_type' => ['nullable', 'string', 'max:128'],
            'source_reference_id' => ['nullable', 'integer'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:draft,active,archived'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($request, $assignments->upsert($this->actor($request), $product, $data), 201);
    }

    public function destroyAssignment(Request $request, Product $product, string $assignment, AssignmentWriteService $assignments): JsonResponse
    {
        $row = ProductContextAssignment::query()->where('public_id', $assignment)->first();
        if ($row === null) {
            throw RecommendationException::notFound('Assignment');
        }
        $assignments->delete($this->actor($request), $product, $row);

        return $this->ok($request, ['deleted' => true]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer'],
            'confirm' => ['required', 'accepted'],
            'term_id' => ['required', 'uuid'],
            'assignment_type' => ['required', 'in:required_match,preferred_match,supported,neutral,excluded'],
            'source_type' => ['required', 'in:manual_verified,manufacturer_specification,catalog_attribute,legal_rule,species_knowledge,system_derived'],
            'source_reference_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ]);
        $count = count(array_unique($data['product_ids']));
        $input = $data;
        unset($input['product_ids'], $input['confirm']);
        BulkAssignProductContextJob::dispatch($this->actor($request)->id, $data['product_ids'], $input);

        return $this->ok($request, ['affected' => $count, 'queued' => $count > (int) config('recommendations.bulk_queue_threshold', 25)], 202);
    }

    public function merchandising(Request $request): JsonResponse
    {
        return $this->ok($request, RecommendationMerchandisingRule::query()->orderByDesc('id')->paginate(10));
    }

    public function storeMerchandising(Request $request, MerchandisingWriteService $rules): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'placement' => ['required', 'in:outdoor_context_result,species_detail,season_explorer,map_location_result,product_detail_related,cart_contextual'],
            'product_id' => ['nullable', 'integer'],
            'product_category_id' => ['nullable', 'integer'],
            'adjustment_type' => ['required', 'in:boost,demote,pin,exclude'],
            'adjustment_value' => ['required', 'integer', 'min:0', 'max:12'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['nullable', 'in:draft,active,expired,archived'],
            'paid_placement' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
        ]);

        return $this->ok($request, $rules->create($this->actor($request), $data), 201);
    }

    public function simulate(Request $request, ContextualProductRecommender $recommender, RecommendationAuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'placement' => ['required', 'in:outdoor_context_result,species_detail,season_explorer,map_location_result'],
            'conclusion' => ['required', 'in:allowed,prohibited,conditional,unknown,conflict'],
            'spatially_verified' => ['nullable', 'boolean'],
            'boundary_uncertain' => ['nullable', 'boolean'],
            'activity' => ['nullable', 'in:hunting,fishing'],
            'species_slug' => ['nullable', 'string', 'max:160'],
            'species_category_code' => ['nullable', 'string', 'max:64'],
            'zone_public_ids' => ['nullable', 'array', 'max:20'],
            'zone_public_ids.*' => ['string', 'max:64'],
            'zone_types' => ['nullable', 'array', 'max:12'],
            'zone_types.*' => ['string', 'max:64'],
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d'],
            'season_phase' => ['nullable', 'string', 'max:64'],
            'region_code' => ['nullable', 'string', 'max:16'],
            'prohibited_equipment' => ['nullable', 'array', 'max:20'],
            'prohibited_equipment.*' => ['string', 'max:64'],
            'required_equipment' => ['nullable', 'array', 'max:20'],
            'required_equipment.*' => ['string', 'max:64'],
            'prohibited_methods' => ['nullable', 'array', 'max:20'],
            'prohibited_methods.*' => ['string', 'max:64'],
            'allowed_methods' => ['nullable', 'array', 'max:20'],
            'allowed_methods.*' => ['string', 'max:64'],
            'completeness' => ['nullable', 'in:high,partial,insufficient'],
            'locale' => ['nullable', 'in:ka,en'],
        ]);
        $context = new DerivedLegalContextData(
            conclusion: $data['conclusion'],
            boundaryUncertain: (bool) ($data['boundary_uncertain'] ?? false),
            spatiallyVerified: (bool) ($data['spatially_verified'] ?? false),
            activity: (string) ($data['activity'] ?? ''),
            speciesId: null,
            speciesSlug: $data['species_slug'] ?? null,
            speciesCategoryCode: $data['species_category_code'] ?? null,
            zonePublicIds: $data['zone_public_ids'] ?? [],
            zoneTypes: $data['zone_types'] ?? [],
            periodFrom: $data['period_from'] ?? null,
            periodTo: $data['period_to'] ?? null,
            seasonPhase: $data['season_phase'] ?? null,
            regionCode: $data['region_code'] ?? null,
            prohibitedEquipment: $data['prohibited_equipment'] ?? [],
            requiredEquipment: $data['required_equipment'] ?? [],
            prohibitedMethods: $data['prohibited_methods'] ?? [],
            allowedMethods: $data['allowed_methods'] ?? [],
            completeness: $data['completeness'] ?? 'partial',
        );
        $result = $recommender->recommend($context, RecommendationPlacement::from($data['placement']), $data['locale'] ?? 'ka', 'GEL', 1, 10, true);
        $simulation = RecommendationSimulation::query()->create([
            'public_id' => (string) Str::uuid(),
            'recommendation_profile_id' => RecommendationProfile::query()->where('placement', $data['placement'])->where('status', 'published')->value('id'),
            'safe_context_snapshot' => $context->toArray(),
            'result_summary' => [
                'gate' => $result['gate'],
                'count' => count($result['recommendations']),
                'diagnostics' => $result['diagnostics'] ?? [],
            ],
            'executed_by' => $this->actor($request)->id,
            'executed_at' => now(),
        ]);
        $audit->record(AuditEvent::RecommendationSimulated, $this->actor($request), 'recommendation_simulation', $simulation->public_id, null, [
            'conclusion' => LegalConclusion::from($data['conclusion'])->value,
            'placement' => $data['placement'],
        ]);

        return $this->ok($request, $result);
    }

    public function terms(Request $request): JsonResponse
    {
        $terms = ContextTaxonomyTerm::query()->with('translations')->orderBy('dimension')->orderBy('sort_order')->paginate(10);

        return $this->ok($request, $terms);
    }

    public function storeTerm(Request $request, RecommendationAuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'dimension' => ['required', 'in:activity,species,species_category,equipment,method,permit,license,season_phase,region,zone_type,environment,trip_duration,measurement,use_case'],
            'code' => ['required', 'regex:/^[a-z0-9_]+$/'],
            'default_label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'labels' => ['required', 'array'],
            'labels.ka' => ['required', 'string', 'max:160'],
            'labels.en' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'catalog_category_id' => ['nullable', 'integer'],
        ]);
        $term = ContextTaxonomyTerm::query()->create([
            'public_id' => (string) Str::uuid(),
            'dimension' => ContextDimension::from($data['dimension']),
            'code' => $data['code'],
            'default_label' => $data['default_label'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
            'catalog_category_id' => $data['catalog_category_id'] ?? null,
            'created_by' => $this->actor($request)->id,
        ]);
        foreach (['ka', 'en'] as $locale) {
            ContextTaxonomyTermTranslation::query()->create([
                'context_taxonomy_term_id' => $term->id,
                'locale' => $locale,
                'label' => $data['labels'][$locale],
            ]);
        }
        $audit->record(AuditEvent::RecommendationTaxonomySaved, $this->actor($request), 'context_taxonomy_term', $term->public_id, null, [
            'dimension' => $term->dimension->value,
            'code' => $term->code,
        ]);

        return $this->ok($request, $term->load('translations'), 201);
    }

    private function profile(string $publicId): RecommendationProfile
    {
        $profile = RecommendationProfile::query()->where('public_id', $publicId)->first();
        if ($profile === null) {
            throw RecommendationException::notFound('Profile');
        }

        return $profile;
    }

    /**
     * @return array{name: string, placement: string, configuration: array<string, mixed>, weights: array<string, int>}
     */
    private function profilePayload(Request $request): array
    {
        /** @var array{name: string, placement: string, configuration: array<string, mixed>, weights: array<string, int>} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'placement' => ['required', 'in:outdoor_context_result,species_detail,season_explorer,map_location_result,product_detail_related,cart_contextual'],
            'configuration' => ['required', 'array'],
            'weights' => ['required', 'array'],
        ]);
        $weights = [];
        foreach ($data['weights'] as $dimension => $weight) {
            if (! is_string($dimension) || ! is_numeric($weight)) {
                throw RecommendationException::invalid('Weights must be numeric.');
            }
            $weights[$dimension] = (int) $weight;
        }
        $data['weights'] = $weights;
        $data['configuration']['weights'] = $weights;

        return $data;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw RecommendationException::invalid('Authentication is required.');
        }

        return $user;
    }

    private function ok(Request $request, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ], $status);
    }
}
