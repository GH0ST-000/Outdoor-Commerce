<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\NativeStatus;
use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\TaxonomicRank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpeciesRequest extends FormRequest
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
            'scientific_name' => ['required', 'string', 'max:191'],
            'scientific_name_authorship' => ['nullable', 'string', 'max:191'],
            'taxonomic_rank' => ['required', Rule::enum(TaxonomicRank::class)],
            'kingdom' => ['required', 'string', 'max:64'],
            'phylum' => ['nullable', 'string', 'max:64'],
            'class_name' => ['nullable', 'string', 'max:64'],
            'order_name' => ['nullable', 'string', 'max:64'],
            'family' => ['nullable', 'string', 'max:64'],
            'genus' => ['nullable', 'string', 'max:64'],
            'species_epithet' => ['nullable', 'string', 'max:64'],
            'canonical_slug' => ['nullable', 'string', 'max:191'],
            'domain_type' => ['required', Rule::enum(SpeciesDomainType::class)],
            'activity_type' => ['required', Rule::enum(SpeciesActivityType::class)],
            'native_status' => ['nullable', Rule::enum(NativeStatus::class)],
            'no_media_required' => ['sometimes', 'boolean'],
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
            'citation_source_ids' => ['sometimes', 'array'],
            'citation_source_ids.*' => ['integer', 'distinct', 'exists:knowledge_sources,id'],
        ];
    }
}
