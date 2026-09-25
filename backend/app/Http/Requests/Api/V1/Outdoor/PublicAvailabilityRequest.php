<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Outdoor;

use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Support\SeasonDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicAvailabilityRequest extends FormRequest
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
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'mode' => ['nullable', Rule::enum(AvailabilityMode::class)],
            'region' => ['nullable', 'string', 'max:64'],
            'species' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:64'],
            'jurisdiction' => ['nullable', Rule::in(config('legal.jurisdictions', ['GE']))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function period(): SeasonDateRange
    {
        SeasonDateRange::assertYmd((string) $this->query('from'));
        SeasonDateRange::assertYmd((string) $this->query('to'));
        $timezone = SeasonDateRange::configuredTimezone();
        $range = SeasonDateRange::fromQueryDates((string) $this->query('from'), (string) $this->query('to'), $timezone);
        $max = (int) config('legal.calendar.max_public_days', 366);
        if ($range->durationDays() > $max) {
            throw LegalException::seasonRangeExceeded($max);
        }

        return $range;
    }
}
