<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Queries\PublicProductImageQuery;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Enums\ExclusionCode;
use App\Domains\Recommendations\Enums\ReasonCode;
use App\Domains\Recommendations\Enums\RecommendationConfidence;
use App\Domains\Recommendations\Enums\RecommendationGate;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Enums\ScoreDimension;
use App\Domains\Recommendations\Models\ProductCompatibilityRule;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use App\Domains\Recommendations\Models\RecommendationMerchandisingRule;
use App\Domains\Recommendations\Models\RecommendationProfile;
use App\Domains\Recommendations\Support\RecommendationCopy;
use App\Domains\Recommendations\Support\RecommendationLogger;
use Illuminate\Support\Carbon;

/**
 * Context-aware ranking. Legal conclusions are an input. This service never
 * writes legal state and never lets merchandising reverse a hard exclusion.
 */
final class ContextualProductRecommender
{
    public function __construct(
        private readonly CandidateGenerator $candidates,
        private readonly AssignmentResolver $assignments,
        private readonly HardExclusionEngine $exclusions,
        private readonly CompatibilityRuleEvaluator $rules,
        private readonly RecommendationScorer $scorer,
        private readonly MerchandisingAdjuster $merchandising,
        private readonly TieBreaker $tieBreaker,
        private readonly ConfidenceCalculator $confidence,
        private readonly ExplanationBuilder $explanations,
        private readonly RecommendationCopy $copy,
        private readonly LegalOutcomeGate $gate,
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $inventory,
        private readonly PublicProductImageQuery $images,
        private readonly RecommendationLogger $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function recommend(
        DerivedLegalContextData $context,
        RecommendationPlacement $placement,
        string $locale,
        string $currency,
        int $page,
        int $perPage,
        bool $diagnostics = false,
    ): array {
        $started = microtime(true);
        $locale = in_array($locale, ['ka', 'en'], true) ? $locale : 'ka';
        $page = max(1, $page);
        $perPage = min((int) config('recommendations.page_size_max', 10), max(1, $perPage));
        $conclusion = LegalConclusion::tryFrom($context->conclusion) ?? LegalConclusion::Unknown;
        $gate = $this->gate->fromConclusion($conclusion, $context->boundaryUncertain, $context->spatiallyVerified);
        $framing = $this->framing($gate, $placement, $conclusion);
        $profile = $this->profile($placement);
        $warnings = $this->gateWarnings($gate, $framing, $locale);
        $base = $this->envelope($context, $placement, $gate, $profile, $framing, $locale, $warnings);

        if ($currency !== '' && strtoupper($currency) !== (string) config('catalog.public.currency', 'GEL')) {
            $base['warnings'][] = ['code' => 'currency_unsupported', 'text' => $this->copy->line($locale, 'disclaimer')];
            $base['pagination'] = $this->page(0, $page, $perPage);

            return $base;
        }

        if ($profile === null) {
            $this->logger->warning('missing_active_profile', ['placement' => $placement->value]);
            $base['warnings'][] = ['code' => 'missing_profile', 'text' => $this->copy->line($locale, 'empty')];
            $base['pagination'] = $this->page(0, $page, $perPage);

            return $base;
        }

        if ($gate === RecommendationGate::Blocked || $framing === 'suppressed') {
            $base['pagination'] = $this->page(0, $page, $perPage);
            $this->log($started, $placement, $profile, $gate, 0, 0, 0, false, 0);
            if ($diagnostics) {
                $base['diagnostics'] = ['gate' => $gate->value, 'candidates' => [], 'exclusions' => []];
            }

            return $base;
        }

        $codes = $this->codes($context);
        $pool = $this->candidates->collect($codes, $profile->candidate_limit, Carbon::now());
        $ranked = $this->rank($pool['ids'], $context, $profile, $locale, $framing, $gate);
        $eligible = array_values(array_filter($ranked, static fn (array $row): bool => $row['eligible']));
        $sorted = $this->tieBreaker->sort($eligible);
        $total = count($sorted);
        $slice = array_slice($sorted, ($page - 1) * $perPage, min($perPage, $profile->maximum_results));
        $missing = 0;
        $public = [];
        foreach ($slice as $row) {
            if (($row['explanation']['primary'] ?? '') === '') {
                $missing++;
            }
            $public[] = $diagnostics ? $row['public'] + ['diagnostics' => $row['diagnostics']] : $row['public'];
        }
        $base['recommendations'] = $public;
        $base['pagination'] = $this->page($total, $page, min($perPage, $profile->maximum_results));
        $base['profile_version'] = $profile->version;
        if ($public === []) {
            $base['warnings'][] = ['code' => 'empty', 'text' => $this->copy->line($locale, 'empty')];
        }
        $this->log($started, $placement, $profile, $gate, count($pool['ids']), count($ranked) - count($eligible), count($eligible), $pool['sources']['meilisearch_fallback'], $missing);
        if ($diagnostics) {
            $base['diagnostics'] = [
                'candidate_sources' => $pool['sources'],
                'candidates' => $pool['ids'],
                'rows' => array_map(static fn (array $row): array => $row['diagnostics'], $ranked),
                'profile_version' => $profile->version,
                'tie_break' => config('recommendations.tie_break'),
            ];
        }

        return $base;
    }

    /**
     * @param  list<int>  $productIds
     * @return list<array{eligible: bool, public: array<string, mixed>, diagnostics: array<string, mixed>, explanation: array<string, mixed>, pinned: bool, pin_priority: int, final_score: int, confidence_rank: int, in_stock: bool, merchandising_priority: int, published_at: int, slug: string}>
     */
    private function rank(array $productIds, DerivedLegalContextData $context, RecommendationProfile $profile, string $locale, string $framing, RecommendationGate $gate): array
    {
        if ($productIds === []) {
            return [];
        }
        $products = Product::query()
            ->with(['translations', 'brand.translations', 'primaryCategory.translations', 'variants', 'mediaAttachments.asset.derivatives'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $at = Carbon::now();
        $assignmentRows = ProductContextAssignment::query()->with('term')->activeNow($at)->whereIn('product_id', $productIds)->get()->groupBy('product_id');
        $ruleRows = ProductCompatibilityRule::query()->activeNow($at)->where(function ($query) use ($productIds, $products): void {
            $query->whereIn('product_id', $productIds);
            $categories = $products->pluck('primary_category_id')->filter()->all();
            if ($categories !== []) {
                $query->orWhereIn('product_category_id', $categories);
            }
        })->get()->groupBy(fn (ProductCompatibilityRule $rule): string => $rule->product_id === null ? 'category:'.$rule->product_category_id : 'product:'.$rule->product_id);
        $merch = RecommendationMerchandisingRule::query()
            ->where('placement', $profile->placement)
            ->where('status', 'active')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->orderBy('priority')
            ->get();
        $variantIds = [];
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $variantIds[] = $variant->id;
            }
        }
        $listId = $this->pricing->defaultPublicPriceListId();
        $quotes = $listId === null ? [] : $this->pricing->quoteVariants($variantIds, $listId);
        $stock = $this->inventory->forVariants($variantIds);
        $weights = $profile->weightMap();
        $config = $profile->configuration;
        $hideStock = ($config['stock_behavior'] ?? 'hide_out_of_stock') !== 'show_unavailable';
        $maxMerch = min((int) config('recommendations.merchandising_max_points', 12), (int) ($config['merchandising_max_points'] ?? 8));
        $cap = (int) ($config['activity_only_cap'] ?? config('recommendations.activity_only_cap', 60));
        $minimum = (int) $profile->minimum_score;
        $rows = [];

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                continue;
            }
            $loaded = [];
            foreach ($assignmentRows->get($productId, collect()) as $assignment) {
                $term = $assignment->term;
                if ($term === null) {
                    continue;
                }
                $loaded[] = [
                    'term_id' => (int) $term->id,
                    'dimension' => $term->dimension->value,
                    'code' => $term->code,
                    'type' => $assignment->assignment_type->value,
                    'variant_id' => $assignment->product_variant_id === null ? null : (int) $assignment->product_variant_id,
                    'source' => $assignment->source_type->value,
                    'weight' => (float) $assignment->weight,
                ];
            }
            $translation = $product->translation($locale) ?? $product->translation((string) config('catalog.fallback_locale', 'ka'));
            $slug = (string) ($translation->slug ?? '');
            $published = ! $product->trashed() && $product->status === ProductStatus::Active && $slug !== '';
            $productRule = $this->rulePayload($ruleRows->get('product:'.$productId, collect())->all());
            $categoryRule = $this->rulePayload($ruleRows->get('category:'.$product->primary_category_id, collect())->all());
            $ruleEval = $this->rules->evaluate([...$productRule, ...$categoryRule], $this->ruleContext($context));
            $merchRule = $this->merchFor($merch, $product);
            $adjustment = $this->merchandising->apply(0, $merchRule?->adjustment_type->value, (int) ($merchRule->adjustment_value ?? 0), (int) ($merchRule->priority ?? 100), $maxMerch, (bool) ($merchRule->paid_placement ?? false));
            if ($adjustment['adjustment'] !== 0 && abs($adjustment['adjustment']) > $maxMerch) {
                $this->logger->warning('merchandising_limit', ['product_id' => $product->id]);
            }

            $eligibleVariants = [];
            $variantDiagnostics = [];
            foreach ($product->variants as $variant) {
                if (! $variant instanceof ProductVariant || $variant->trashed() || $variant->status !== ProductVariantStatus::Active) {
                    $variantDiagnostics[] = ['sku' => $variant->sku ?? '', 'exclusions' => [ExclusionCode::VariantUnavailable->value]];

                    continue;
                }
                $resolved = $this->assignments->forVariant($loaded, $variant->id);
                $quote = $quotes[$variant->id] ?? null;
                $availability = $stock[$variant->id] ?? null;
                $inStock = $availability?->isInStock() ?? false;
                $commerce = [
                    'published' => $published,
                    'active' => $product->status === ProductStatus::Active,
                    'priced' => $quote !== null,
                    'in_stock' => $inStock,
                    'deleted' => $product->trashed(),
                ];
                $codes = $this->exclusions->exclude(
                    $resolved['rows'],
                    $resolved['conflicts'],
                    $this->exclusionContext($context),
                    $commerce,
                    $ruleEval['exclusions'],
                    $hideStock,
                    $adjustment['excluded'],
                );
                $variantDiagnostics[] = ['sku' => $variant->sku, 'exclusions' => $codes];
                if ($codes !== []) {
                    continue;
                }
                $signals = $this->signals($resolved['rows'], $context, $inStock, $translation?->short_description !== null);
                $eligibleVariants[] = [
                    'variant' => $variant,
                    'rows' => $resolved['rows'],
                    'signals' => $signals,
                    'quote' => $quote,
                    'in_stock' => $inStock,
                    'low_stock' => $availability?->isLowStock ?? false,
                ];
            }

            $productExclusions = $eligibleVariants === []
                ? ($variantDiagnostics[0]['exclusions'] ?? [ExclusionCode::VariantUnavailable->value])
                : [];
            if ($eligibleVariants === []) {
                $rows[] = $this->rejected($product, $slug, $productExclusions, $variantDiagnostics);

                continue;
            }

            $best = $eligibleVariants[0];
            foreach ($eligibleVariants as $candidate) {
                if ($this->variantRank($candidate) > $this->variantRank($best)) {
                    $best = $candidate;
                }
            }
            $recommended = $this->singleWinner($eligibleVariants, $best);
            $signals = $best['signals'];
            $activityOnly = $this->activityOnly($signals);
            $active = $this->activeDimensions($context);
            $scored = $this->scorer->score($signals, $weights, $active, $cap, $ruleEval['penalty'], $activityOnly);
            $adjusted = $this->merchandising->apply($scored['score'], $adjustment['excluded'] ? null : $merchRule?->adjustment_type->value, (int) ($merchRule->adjustment_value ?? 0), (int) ($merchRule->priority ?? 100), $maxMerch, (bool) ($merchRule->paid_placement ?? false));
            $matched = array_values(array_filter($best['rows'], static fn (array $row): bool => AssignmentType::from($row['type'])->isPositive()));
            $confidence = $this->confidence->calculate($matched, $context->completeness, ($translation->short_description ?? null) !== null, $activityOnly);
            if ($context->completeness !== 'high' && $confidence === RecommendationConfidence::High) {
                $confidence = RecommendationConfidence::Medium;
            }
            $warningCodes = $gate === RecommendationGate::InformationOnly ? [ReasonCode::ConditionalRestriction->value] : [];
            if ($framing === 'species_related') {
                $warningCodes[] = ReasonCode::SpeciesRelatedUnlocated->value;
            }
            if ($framing === 'general_discovery') {
                $warningCodes[] = ReasonCode::GeneralCatalogSuggestion->value;
            }
            $explanation = $this->explanations->build($scored['contributions'], $best['in_stock'], $adjusted['promoted'], $framing, $warningCodes);
            $eligible = $confidence !== RecommendationConfidence::Insufficient && $adjusted['final'] >= $minimum && ! $adjusted['excluded'];
            $card = $this->card($product, $locale, $slug, $eligibleVariants, $recommended, $best, $confidence, $explanation, $adjusted, $gate);
            $rows[] = [
                'eligible' => $eligible,
                'public' => $card,
                'explanation' => $explanation,
                'diagnostics' => [
                    'product_id' => $product->id,
                    'slug' => $slug,
                    'exclusions' => $productExclusions,
                    'variants' => $variantDiagnostics,
                    'signals' => $signals,
                    'contributions' => $scored['contributions'],
                    'base_score' => $scored['score'],
                    'adjustment' => $adjusted['adjustment'],
                    'final_score' => $adjusted['final'],
                    'confidence' => $confidence->value,
                    'pinned' => $adjusted['pinned'],
                ],
                'pinned' => $adjusted['pinned'],
                'pin_priority' => $adjusted['pin_priority'],
                'final_score' => $adjusted['final'],
                'confidence_rank' => $confidence->rank(),
                'in_stock' => $best['in_stock'],
                'merchandising_priority' => (int) ($merchRule->priority ?? 100000),
                'published_at' => $product->published_at?->getTimestamp() ?? 0,
                'slug' => $slug,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function singleWinner(array $variants, array $best): ?ProductVariant
    {
        $top = $this->variantRank($best);
        $tied = array_filter($variants, fn (array $variant): bool => $this->variantRank($variant) === $top);
        if (count($tied) !== 1) {
            return null;
        }

        return $best['variant'];
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function variantRank(array $variant): string
    {
        $signals = $variant['signals'];
        $score = ((int) round(($signals['species_exact_match'] ?? 0) * 1000))
            + ((int) round(($signals['required_equipment_match'] ?? 0) * 100))
            + ((int) $variant['in_stock'] * 10);

        return str_pad((string) $score, 8, '0', STR_PAD_LEFT).':'.str_pad((string) (100000 - $variant['variant']->sort_order), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{dimension: string, code: string, type: string, source: string}>  $rows
     * @return array<string, float>
     */
    private function signals(array $rows, DerivedLegalContextData $context, bool $inStock, bool $specs): array
    {
        $signals = [];
        foreach (ScoreDimension::cases() as $dimension) {
            $signals[$dimension->value] = 0.0;
        }
        $requiredHits = 0;
        foreach ($rows as $row) {
            if (! AssignmentType::from($row['type'])->isPositive()) {
                continue;
            }
            $verified = AssignmentSourceType::from($row['source'])->isVerified();
            if ($row['dimension'] === 'activity' && $row['code'] === $context->activity) {
                $signals['activity_match'] = max($signals['activity_match'], $verified ? 1.0 : 0.7);
            }
            if ($row['dimension'] === 'species' && $context->speciesSlug !== null && $row['code'] === $context->speciesSlug) {
                $signals['species_exact_match'] = max($signals['species_exact_match'], $verified ? 1.0 : 0.6);
            }
            if ($row['dimension'] === 'species_category' && $context->speciesCategoryCode !== null && $row['code'] === $context->speciesCategoryCode) {
                $signals['species_category_match'] = 1.0;
            }
            if ($row['dimension'] === 'equipment' && in_array($row['code'], $context->requiredEquipment, true)) {
                $requiredHits++;
            }
            if ($row['dimension'] === 'method' && in_array($row['code'], $context->allowedMethods, true)) {
                $signals['method_match'] = 1.0;
            }
            if ($row['dimension'] === 'season_phase' && $context->seasonPhase !== null && $row['code'] === $context->seasonPhase) {
                $signals['season_phase_match'] = 1.0;
            }
            if ($row['dimension'] === 'region' && $context->regionCode !== null && $row['code'] === $context->regionCode) {
                $signals['region_match'] = 1.0;
            }
            if ($row['dimension'] === 'zone_type' && in_array($row['code'], $context->zoneTypes, true)) {
                $signals['zone_type_match'] = 1.0;
            }
        }
        if ($context->requiredEquipment !== []) {
            $signals['required_equipment_match'] = min(1.0, $requiredHits / count($context->requiredEquipment));
        }
        $signals['specification_completeness'] = $specs ? 1.0 : 0.4;
        $signals['availability'] = $inStock ? 1.0 : 0.0;
        $signals['general_relevance'] = max($signals['activity_match'], $signals['species_category_match']) > 0 ? 0.5 : 0.0;

        return $signals;
    }

    /**
     * @param  array<string, float>  $signals
     */
    private function activityOnly(array $signals): bool
    {
        $keys = [];
        foreach (['activity_match', 'species_exact_match', 'species_category_match', 'required_equipment_match', 'method_match', 'season_phase_match', 'region_match', 'zone_type_match'] as $key) {
            if (($signals[$key] ?? 0) > 0) {
                $keys[] = $key;
            }
        }

        return $keys === ['activity_match'];
    }

    /**
     * @return list<string>
     */
    private function activeDimensions(DerivedLegalContextData $context): array
    {
        $active = ['specification_completeness', 'availability'];
        if ($context->activity !== '') {
            $active[] = 'activity_match';
        }
        if ($context->speciesSlug !== null && $context->speciesSlug !== '') {
            $active[] = 'species_exact_match';
        }
        if ($context->speciesCategoryCode !== null && $context->speciesCategoryCode !== '') {
            $active[] = 'species_category_match';
        }
        if ($context->requiredEquipment !== []) {
            $active[] = 'required_equipment_match';
        }
        if ($context->allowedMethods !== []) {
            $active[] = 'method_match';
        }
        if ($context->seasonPhase !== null && $context->seasonPhase !== '') {
            $active[] = 'season_phase_match';
        }
        if ($context->regionCode !== null && $context->regionCode !== '') {
            $active[] = 'region_match';
        }
        if ($context->zoneTypes !== []) {
            $active[] = 'zone_type_match';
        }

        return $active;
    }

    /**
     * @return array<string, list<string>>
     */
    private function codes(DerivedLegalContextData $context): array
    {
        return array_filter([
            'activity' => $context->activity === '' ? [] : [$context->activity],
            'species' => $context->speciesSlug ? [$context->speciesSlug] : [],
            'species_category' => $context->speciesCategoryCode ? [$context->speciesCategoryCode] : [],
            'equipment' => $context->requiredEquipment,
            'method' => $context->allowedMethods,
            'season_phase' => $context->seasonPhase ? [$context->seasonPhase] : [],
            'region' => $context->regionCode ? [$context->regionCode] : [],
            'zone_type' => $context->zoneTypes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exclusionContext(DerivedLegalContextData $context): array
    {
        return [
            'activity' => $context->activity,
            'species' => $context->speciesSlug,
            'species_category' => $context->speciesCategoryCode,
            'methods' => $context->allowedMethods,
            'prohibited_equipment' => $context->prohibitedEquipment,
            'prohibited_methods' => $context->prohibitedMethods,
            'region' => $context->regionCode,
            'zone_types' => $context->zoneTypes,
            'equipment_codes' => $context->requiredEquipment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleContext(DerivedLegalContextData $context): array
    {
        return [
            'activity' => $context->activity,
            'species' => $context->speciesSlug,
            'species_category' => $context->speciesCategoryCode,
            'equipment' => $context->requiredEquipment,
            'method' => $context->allowedMethods,
            'region' => $context->regionCode,
            'zone_type' => $context->zoneTypes,
            'season_phase' => $context->seasonPhase,
        ];
    }

    /**
     * @param  list<ProductCompatibilityRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function rulePayload(array $rules): array
    {
        $payload = [];
        foreach ($rules as $rule) {
            $payload[] = [
                'type' => $rule->rule_type->value,
                'dimension' => $rule->context_dimension->value,
                'operator' => $rule->operator->value,
                'value' => $rule->string_value,
                'integer_value' => $rule->integer_value,
                'decimal_value' => $rule->decimal_value,
                'boolean_value' => $rule->boolean_value,
                'weight' => (int) $rule->weight,
            ];
        }

        return $payload;
    }

    /**
     * @param  iterable<RecommendationMerchandisingRule>  $rules
     */
    private function merchFor(iterable $rules, Product $product): ?RecommendationMerchandisingRule
    {
        foreach ($rules as $rule) {
            if ($rule->product_id !== null && (int) $rule->product_id === $product->id) {
                return $rule;
            }
            if ($rule->product_category_id !== null && (int) $rule->product_category_id === (int) $product->primary_category_id) {
                return $rule;
            }
        }

        return null;
    }

    private function profile(RecommendationPlacement $placement): ?RecommendationProfile
    {
        return RecommendationProfile::query()
            ->with('weights')
            ->where('placement', $placement)
            ->where('status', 'published')
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            })
            ->orderByDesc('version')
            ->first();
    }

    private function framing(RecommendationGate $gate, RecommendationPlacement $placement, LegalConclusion $conclusion): string
    {
        if ($gate === RecommendationGate::Blocked) {
            return $conclusion === LegalConclusion::Conflict ? 'conflict' : 'blocked';
        }
        if ($gate === RecommendationGate::Unknown) {
            return $placement->showsUnlocatedSpeciesGear() ? 'species_related' : 'suppressed';
        }
        if ($gate === RecommendationGate::InformationOnly) {
            return 'conditional';
        }

        return 'contextual';
    }

    /**
     * @param  list<array{code: string, text: string}>  $warnings
     * @return array<string, mixed>
     */
    private function envelope(DerivedLegalContextData $context, RecommendationPlacement $placement, RecommendationGate $gate, ?RecommendationProfile $profile, string $framing, string $locale, array $warnings): array
    {
        return [
            'context' => [
                'activity' => $context->activity,
                'species_slug' => $context->speciesSlug,
                'species_category_code' => $context->speciesCategoryCode,
                'zone_public_ids' => $context->zonePublicIds,
                'completeness' => $context->completeness,
                'framing' => $framing,
            ],
            'placement' => $placement->value,
            'gate' => $gate->value,
            'profile_version' => $profile?->version,
            'recommendations' => [],
            'pagination' => $this->page(0, 1, 10),
            'warnings' => $warnings,
            'disclaimer' => $this->copy->line($locale, 'disclaimer'),
        ];
    }

    /**
     * @return list<array{code: string, text: string}>
     */
    private function gateWarnings(RecommendationGate $gate, string $framing, string $locale): array
    {
        $code = match ($framing) {
            'blocked' => 'gate_blocked',
            'conflict' => 'gate_conflict',
            'suppressed', 'species_related', 'general_discovery' => $gate === RecommendationGate::Unknown ? 'gate_unknown' : null,
            'conditional' => ReasonCode::ConditionalRestriction->value,
            default => null,
        };
        if ($code === null) {
            return [];
        }

        return [['code' => $code, 'text' => $this->copy->line($locale, $code)]];
    }

    /**
     * @param  list<string>  $exclusions
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function rejected(Product $product, string $slug, array $exclusions, array $variants): array
    {
        return [
            'eligible' => false,
            'public' => [],
            'explanation' => ['primary' => '', 'supporting' => [], 'warnings' => []],
            'diagnostics' => [
                'product_id' => $product->id,
                'slug' => $slug,
                'exclusions' => $exclusions,
                'variants' => $variants,
                'final_score' => 0,
            ],
            'pinned' => false,
            'pin_priority' => 100000,
            'final_score' => 0,
            'confidence_rank' => 0,
            'in_stock' => false,
            'merchandising_priority' => 100000,
            'published_at' => 0,
            'slug' => $slug,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @param  array{primary: string, supporting: list<string>, warnings: list<string>}  $explanation
     * @param  array{adjustment: int, final: int, pinned: bool, pin_priority: int, excluded: bool, promoted: bool, label: string|null}  $adjusted
     * @return array<string, mixed>
     */
    private function card(Product $product, string $locale, string $slug, array $variants, ?ProductVariant $recommended, array $best, RecommendationConfidence $confidence, array $explanation, array $adjusted, RecommendationGate $gate): array
    {
        $quote = $best['quote'];
        $currency = $quote?->currencyCode ?? (string) config('catalog.public.currency', 'GEL');
        $texts = [];
        foreach ([$explanation['primary'], ...$explanation['supporting']] as $code) {
            $texts[] = ['code' => $code, 'text' => $this->copy->line($locale, $code)];
        }
        $primary = $texts[0];
        $supporting = array_slice($texts, 1);
        $warnings = array_map(fn (string $code): array => ['code' => $code, 'text' => $this->copy->line($locale, $code)], $explanation['warnings']);
        $variantCards = [];
        foreach ($variants as $variant) {
            $variantQuote = $variant['quote'];
            $variantCards[] = [
                'variant_id' => $variant['variant']->id,
                'sku' => $variant['variant']->sku,
                'in_stock' => $variant['in_stock'],
                'stock_status' => $variant['in_stock'] ? ($variant['low_stock'] ? 'low_stock' : 'in_stock') : 'out_of_stock',
                'price' => $variantQuote === null ? null : [
                    'amount_minor' => $variantQuote->baseAmountMinor,
                    'final_amount_minor' => $variantQuote->finalAmountMinor,
                    'currency' => $variantQuote->currencyCode,
                ],
            ];
        }
        $purchasable = $recommended !== null && $best['in_stock'] && $quote !== null && $gate !== RecommendationGate::Blocked;

        return [
            'slug' => $slug,
            'name' => (string) ($product->translation($locale)?->name ?? ''),
            'brand' => $product->brand?->translation($locale)?->name,
            'primary_image' => $this->images->url($product),
            'category' => $product->primaryCategory?->translation($locale)?->name,
            'price' => $quote === null ? null : ['amount_minor' => $quote->baseAmountMinor, 'currency' => $currency],
            'sale_price' => $quote !== null && $quote->onSale() ? ['amount_minor' => $quote->finalAmountMinor, 'currency' => $currency] : null,
            'currency' => $currency,
            'stock_status' => $best['in_stock'] ? ($best['low_stock'] ? 'low_stock' : 'in_stock') : 'out_of_stock',
            'eligible_variants' => $variantCards,
            'recommended_variant' => $recommended?->sku,
            'score_band' => $adjusted['final'] >= 70 ? 'high' : ($adjusted['final'] >= 40 ? 'medium' : 'low'),
            'confidence' => $confidence->value,
            'primary_reason' => $primary['code'],
            'primary_reason_text' => $primary['text'],
            'supporting_reasons' => $supporting,
            'matched_signals' => array_values(array_filter(array_column($supporting, 'code'))),
            'unmatched_optional_signals' => [],
            'warnings' => $warnings,
            'is_promoted' => $adjusted['promoted'],
            'promotion_label' => $adjusted['promoted'] ? $this->copy->line($locale, ReasonCode::Promoted->value) : null,
            'product_url' => sprintf((string) config('catalog.public.storefront_paths.product', '/products/%s'), $slug),
            'add_to_cart_eligible' => $purchasable,
        ];
    }

    /**
     * @return array{page: int, per_page: int, total: int, has_more: bool}
     */
    private function page(int $total, int $page, int $perPage): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    private function log(float $started, RecommendationPlacement $placement, ?RecommendationProfile $profile, RecommendationGate $gate, int $candidates, int $excluded, int $eligible, bool $fallback, int $missing): void
    {
        $this->logger->completed([
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'placement' => $placement->value,
            'profile_version' => $profile?->version,
            'gate' => $gate->value,
            'candidates' => $candidates,
            'excluded' => $excluded,
            'eligible' => $eligible,
            'returned' => $eligible,
            'meilisearch_fallback' => $fallback,
            'missing_explanation' => $missing,
        ]);
    }
}
