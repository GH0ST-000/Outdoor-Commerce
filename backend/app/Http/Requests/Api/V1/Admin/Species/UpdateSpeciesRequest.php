<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\ActivityPattern;
use App\Domains\Hunting\Enums\ConservationAssessmentScope;
use App\Domains\Hunting\Enums\HabitatCode;
use App\Domains\Hunting\Enums\HabitatImportance;
use App\Domains\Hunting\Enums\IdentificationTraitCategory;
use App\Domains\Hunting\Enums\MeasurementDepthUnit;
use App\Domains\Hunting\Enums\MeasurementLengthUnit;
use App\Domains\Hunting\Enums\MeasurementTemperatureUnit;
use App\Domains\Hunting\Enums\MeasurementWeightUnit;
use App\Domains\Hunting\Enums\MigrationPattern;
use App\Domains\Hunting\Enums\NativeStatus;
use App\Domains\Hunting\Enums\SocialBehavior;
use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Enums\TaxonomicRank;
use App\Domains\Hunting\Enums\WaterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSpeciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_version' => ['required', 'integer', 'min:1'],
            'scientific_name' => ['sometimes', 'string', 'max:191'],
            'scientific_name_authorship' => ['nullable', 'string', 'max:191'],
            'taxonomic_rank' => ['sometimes', Rule::enum(TaxonomicRank::class)],
            'kingdom' => ['sometimes', 'string', 'max:64'],
            'phylum' => ['nullable', 'string', 'max:64'],
            'class_name' => ['nullable', 'string', 'max:64'],
            'order_name' => ['nullable', 'string', 'max:64'],
            'family' => ['nullable', 'string', 'max:64'],
            'genus' => ['nullable', 'string', 'max:64'],
            'species_epithet' => ['nullable', 'string', 'max:64'],
            'domain_type' => ['sometimes', Rule::enum(SpeciesDomainType::class)],
            'activity_type' => ['sometimes', Rule::enum(SpeciesActivityType::class)],
            'native_status' => ['nullable', Rule::enum(NativeStatus::class)],
            'verification_status' => ['sometimes', Rule::enum(SpeciesVerificationStatus::class)],
            'no_media_required' => ['sometimes', 'boolean'],
            'change_summary' => ['nullable', 'string', 'max:240'],
            'translations' => ['sometimes', 'array', 'max:2'],
            'translations.*.locale' => ['required_with:translations', 'in:ka,en'],
            'translations.*.common_name' => ['required_with:translations', 'string', 'max:120'],
            'translations.*.short_name' => ['nullable', 'string', 'max:80'],
            'translations.*.summary' => ['required_with:translations', 'string', 'max:2000'],
            'translations.*.identification' => ['nullable', 'string', 'max:8000'],
            'translations.*.appearance' => ['nullable', 'string', 'max:8000'],
            'translations.*.behavior' => ['nullable', 'string', 'max:8000'],
            'translations.*.diet' => ['nullable', 'string', 'max:4000'],
            'translations.*.habitat_description' => ['nullable', 'string', 'max:4000'],
            'translations.*.breeding_notes' => ['nullable', 'string', 'max:4000'],
            'translations.*.seasonal_behavior' => ['nullable', 'string', 'max:4000'],
            'translations.*.field_notes' => ['nullable', 'string', 'max:4000'],
            'translations.*.safety_notes' => ['nullable', 'string', 'max:4000'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:70'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:170'],
            'translations.*.content_status' => ['sometimes', Rule::enum(SpeciesContentStatus::class)],
            'characteristics' => ['sometimes', 'array'],
            'characteristics.average_length_min' => ['nullable', 'numeric', 'min:0'],
            'characteristics.average_length_max' => ['nullable', 'numeric', 'min:0'],
            'characteristics.length_unit' => ['nullable', Rule::enum(MeasurementLengthUnit::class)],
            'characteristics.average_weight_min' => ['nullable', 'numeric', 'min:0'],
            'characteristics.average_weight_max' => ['nullable', 'numeric', 'min:0'],
            'characteristics.weight_unit' => ['nullable', Rule::enum(MeasurementWeightUnit::class)],
            'characteristics.lifespan_years_min' => ['nullable', 'numeric', 'min:0'],
            'characteristics.lifespan_years_max' => ['nullable', 'numeric', 'min:0'],
            'characteristics.activity_pattern' => ['nullable', Rule::enum(ActivityPattern::class)],
            'characteristics.social_behavior' => ['nullable', Rule::enum(SocialBehavior::class)],
            'characteristics.migration_pattern' => ['nullable', Rule::enum(MigrationPattern::class)],
            'characteristics.water_type' => ['nullable', Rule::enum(WaterType::class)],
            'characteristics.preferred_depth_min' => ['nullable', 'numeric'],
            'characteristics.preferred_depth_max' => ['nullable', 'numeric'],
            'characteristics.depth_unit' => ['nullable', Rule::enum(MeasurementDepthUnit::class)],
            'characteristics.temperature_range_min' => ['nullable', 'numeric'],
            'characteristics.temperature_range_max' => ['nullable', 'numeric'],
            'characteristics.temperature_unit' => ['nullable', Rule::enum(MeasurementTemperatureUnit::class)],
            'habitats' => ['sometimes', 'array'],
            'habitats.*.code' => ['required_with:habitats', Rule::enum(HabitatCode::class)],
            'habitats.*.importance' => ['sometimes', Rule::enum(HabitatImportance::class)],
            'habitats.*.notes' => ['nullable', 'string', 'max:500'],
            'habitats.*.source_id' => ['nullable'],
            'habitats.*.source_public_id' => ['nullable', 'uuid'],
            'identification_traits' => ['sometimes', 'array'],
            'identification_traits.*.locale' => ['required_with:identification_traits', 'in:ka,en'],
            'identification_traits.*.category' => ['required_with:identification_traits', Rule::enum(IdentificationTraitCategory::class)],
            'identification_traits.*.label' => ['required_with:identification_traits', 'string', 'max:120'],
            'identification_traits.*.description' => ['required_with:identification_traits', 'string', 'max:2000'],
            'identification_traits.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'identification_traits.*.source_id' => ['nullable'],
            'conservation' => ['sometimes', 'array'],
            'conservation.*.assessment_system' => ['required_with:conservation', 'string', 'max:64'],
            'conservation.*.status_code' => ['required_with:conservation', 'string', 'max:64'],
            'conservation.*.assessment_scope' => ['required_with:conservation', Rule::enum(ConservationAssessmentScope::class)],
            'conservation.*.assessed_at' => ['nullable', 'date'],
            'conservation.*.source_id' => ['nullable'],
            'conservation.*.source_public_id' => ['nullable', 'uuid'],
            'conservation.*.notes' => ['nullable', 'string', 'max:2000'],
            'citation_source_ids' => ['sometimes', 'array'],
            'citation_source_ids.*' => ['integer', 'distinct', 'exists:knowledge_sources,id'],
        ];
    }

    public function hasTaxonomyChanges(): bool
    {
        return $this->hasAny([
            'scientific_name',
            'taxonomic_rank',
            'kingdom',
            'phylum',
            'class_name',
            'order_name',
            'family',
            'genus',
            'species_epithet',
            'scientific_name_authorship',
        ]);
    }
}
