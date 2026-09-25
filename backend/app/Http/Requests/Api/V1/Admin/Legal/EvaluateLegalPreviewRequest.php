<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\LegalEvaluationFactsData;
use App\Domains\Legal\Enums\LegalActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluateLegalPreviewRequest extends FormRequest
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
            'activity_type' => ['required', Rule::enum(LegalActivityType::class)],
            'jurisdiction_code' => ['required', Rule::in(config('legal.jurisdictions', ['GE']))],
            'occurred_at' => ['required', 'date'],
            'species_id' => ['nullable', 'uuid'],
            'region_code' => ['nullable', 'string', 'max:64'],
            'zone_reference' => ['nullable', 'string', 'max:128'],
            'permit_codes' => ['sometimes', 'array'],
            'permit_codes.*' => ['string', 'max:64'],
            'license_codes' => ['sometimes', 'array'],
            'license_codes.*' => ['string', 'max:64'],
            'equipment_codes' => ['sometimes', 'array'],
            'method_codes' => ['sometimes', 'array'],
            'requested_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function facts(): LegalEvaluationFactsData
    {
        $speciesId = null;
        if ($this->filled('species_id')) {
            $speciesId = Species::query()->where('public_id', $this->string('species_id'))->value('id');
        }

        return new LegalEvaluationFactsData(
            activityType: LegalActivityType::from((string) $this->string('activity_type')),
            jurisdictionCode: (string) $this->string('jurisdiction_code'),
            occurredAt: new \DateTimeImmutable((string) $this->string('occurred_at')),
            speciesId: $speciesId !== null ? (int) $speciesId : null,
            regionCode: $this->input('region_code'),
            zoneReference: $this->input('zone_reference'),
            permitCodes: array_values($this->input('permit_codes', [])),
            licenseCodes: array_values($this->input('license_codes', [])),
            equipmentCodes: array_values($this->input('equipment_codes', [])),
            methodCodes: array_values($this->input('method_codes', [])),
            requestedQuantity: $this->filled('requested_quantity') ? (int) $this->integer('requested_quantity') : null,
        );
    }
}
