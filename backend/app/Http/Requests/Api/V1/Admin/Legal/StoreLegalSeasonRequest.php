<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalSeasonRequest extends FormRequest
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
            'legal_rule_id' => [$required, 'uuid'],
            'species_id' => [$required, 'uuid'],
            'activity_type' => [$required, Rule::in([LegalActivityType::Hunting->value, LegalActivityType::Fishing->value])],
            'season_type' => [$required, Rule::enum(SeasonType::class)],
            'schedule_type' => [$required, Rule::enum(SeasonScheduleType::class)],
            'jurisdiction_code' => ['nullable', Rule::in(config('legal.jurisdictions', ['GE']))],
            'region_code' => ['nullable', 'string', 'max:64'],
            'zone_reference' => ['nullable', 'string', 'max:128'],
            'timezone' => ['nullable', 'timezone'],
            'boundary_precision' => ['nullable', Rule::enum(SeasonBoundaryPrecision::class)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'end_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_time' => ['nullable', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i:s'],
            'first_season_year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'last_season_year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'crosses_calendar_year' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
