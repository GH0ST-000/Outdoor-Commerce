<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Queries;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Models\ContextTaxonomyTerm;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use App\Domains\Recommendations\Models\RecommendationMerchandisingRule;
use App\Domains\Recommendations\Models\RecommendationProfile;
use Illuminate\Support\Facades\DB;

final class RecommendationCoverageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $activeProducts = Product::query()->where('status', ProductStatus::Active)->whereNull('deleted_at');
        $activeIds = (clone $activeProducts)->pluck('id');
        $mapped = ProductContextAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->whereIn('product_id', $activeIds)
            ->distinct()
            ->pluck('product_id');
        $activityMapped = ProductContextAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->whereIn('product_id', $activeIds)
            ->whereHas('term', fn ($query) => $query->where('dimension', 'activity'))
            ->distinct()
            ->pluck('product_id');
        $lowConfidence = ProductContextAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->whereIn('source_type', [AssignmentSourceType::ManufacturerSpecification, AssignmentSourceType::SystemDerived])
            ->distinct()
            ->count('product_id');
        $contradictions = DB::table('product_context_assignments as excluded')
            ->join('product_context_assignments as positive', function ($join): void {
                $join->on('excluded.product_id', '=', 'positive.product_id')
                    ->on('excluded.context_taxonomy_term_id', '=', 'positive.context_taxonomy_term_id');
            })
            ->where('excluded.assignment_type', AssignmentType::Excluded->value)
            ->where('excluded.status', AssignmentStatus::Active->value)
            ->whereIn('positive.assignment_type', [
                AssignmentType::RequiredMatch->value,
                AssignmentType::PreferredMatch->value,
                AssignmentType::Supported->value,
            ])
            ->where('positive.status', AssignmentStatus::Active->value)
            ->distinct()
            ->count('excluded.product_id');
        $requiredTerms = ContextTaxonomyTerm::query()->where('dimension', 'equipment')->where('is_active', true)->get();
        $uncovered = [];
        foreach ($requiredTerms as $term) {
            $count = ProductContextAssignment::query()
                ->where('context_taxonomy_term_id', $term->id)
                ->where('status', AssignmentStatus::Active)
                ->whereIn('assignment_type', [AssignmentType::RequiredMatch->value, AssignmentType::PreferredMatch->value, AssignmentType::Supported->value])
                ->count();
            if ($count === 0) {
                $uncovered[] = $term->code;
            }
        }

        return [
            'products_without_activity' => $activeIds->count() - $activityMapped->count(),
            'products_without_assignments' => $activeIds->count() - $mapped->count(),
            'contradictory_assignments' => $contradictions,
            'low_confidence_sources' => $lowConfidence,
            'equipment_terms_without_products' => $uncovered,
            'active_profiles' => RecommendationProfile::query()->where('status', 'published')->get(['placement', 'version', 'slug']),
            'scheduled_merchandising' => RecommendationMerchandisingRule::query()->where('status', 'active')->where('ends_at', '>=', now())->count(),
            'expiring_assignments' => ProductContextAssignment::query()
                ->where('status', AssignmentStatus::Active)
                ->whereNotNull('effective_until')
                ->whereBetween('effective_until', [now(), now()->addDays(14)])
                ->count(),
        ];
    }
}
