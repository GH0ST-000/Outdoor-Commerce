<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Support\SeasonDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminCalendarEvaluateRequest extends FormRequest
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
            'activity' => ['required', Rule::in([LegalActivityType::Hunting->value, LegalActivityType::Fishing->value])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'mode' => ['nullable', Rule::enum(AvailabilityMode::class)],
            'region' => ['nullable', 'string', 'max:64'],
            'species_id' => ['nullable', 'uuid'],
            'jurisdiction' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function period(): SeasonDateRange
    {
        $range = SeasonDateRange::fromQueryDates((string) $this->input('from'), (string) $this->input('to'), SeasonDateRange::configuredTimezone());
        $max = (int) config('legal.calendar.max_admin_days', 1096);
        if ($range->durationDays() > $max) {
            throw LegalException::seasonRangeExceeded($max);
        }

        return $range;
    }
}
