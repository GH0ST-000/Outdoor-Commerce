<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\SeasonOverrideType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalSeasonOverrideRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'base_season_definition_id' => [$required, 'uuid'],
            'legal_rule_id' => [$required, 'uuid'],
            'override_type' => [$required, Rule::enum(SeasonOverrideType::class)],
            'start_date' => [$required, 'date_format:Y-m-d'],
            'end_date' => [$required, 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i:s'],
            'timezone' => ['nullable', 'timezone'],
            'jurisdiction_code' => ['nullable', 'string', 'max:16'],
            'region_code' => ['nullable', 'string', 'max:64'],
            'zone_reference' => ['nullable', 'string', 'max:128'],
            'reason' => [$required, 'string', 'max:500'],
            'precedence' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
