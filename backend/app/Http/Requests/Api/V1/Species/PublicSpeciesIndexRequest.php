<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Species;

use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Support\SpeciesLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicSpeciesIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', Rule::in(SpeciesLocales::all())],
            'activity_type' => ['nullable', Rule::enum(SpeciesActivityType::class)],
            'domain_type' => ['nullable', Rule::enum(SpeciesDomainType::class)],
            'taxonomy' => ['nullable', 'string', 'max:64'],
            'habitat' => ['nullable', 'string', 'max:64'],
            'conservation_status' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'scientific'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $locale = (string) ($this->validated('locale') ?: $this->header('X-Locale', SpeciesLocales::default()));
        if (! SpeciesLocales::isSupported($locale)) {
            $locale = SpeciesLocales::default();
        }

        return [
            'q' => $this->validated('q'),
            'locale' => $locale,
            'activity_type' => $this->validated('activity_type'),
            'domain_type' => $this->validated('domain_type'),
            'taxonomy' => $this->validated('taxonomy'),
            'habitat' => $this->validated('habitat'),
            'conservation_status' => $this->validated('conservation_status'),
            'sort' => $this->validated('sort') ?? 'name',
            'page' => (int) ($this->validated('page') ?? 1),
            'per_page' => (int) ($this->validated('per_page') ?? 10),
        ];
    }
}
