<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Legal\Support\SeasonSchedule;

final class SeasonDefinitionValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertPayload(array $payload, LegalRule $rule): void
    {
        $activity = LegalActivityType::from((string) $payload['activity_type']);
        if ($rule->activity_type !== $activity) {
            throw LegalException::seasonInvalid('Season activity must match the linked legal rule.');
        }

        $type = SeasonType::from((string) $payload['season_type']);
        $this->assertTypeMatchesEffect($type, $rule->effect);

        $schedule = SeasonScheduleType::from((string) $payload['schedule_type']);
        $precision = SeasonBoundaryPrecision::from((string) ($payload['boundary_precision'] ?? 'date'));
        $timezone = (string) ($payload['timezone'] ?? SeasonDateRange::configuredTimezone());
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw LegalException::seasonInvalid('Unknown time zone.');
        }

        if ($schedule === SeasonScheduleType::FixedRange) {
            if (empty($payload['start_date']) || empty($payload['end_date'])) {
                throw LegalException::seasonInvalid('Fixed-range seasons require start_date and end_date.');
            }
            SeasonDateRange::fromInclusiveDates(
                (string) $payload['start_date'],
                (string) $payload['end_date'],
                $timezone,
                $precision,
                isset($payload['start_time']) ? SeasonDateRange::normalizeTime((string) $payload['start_time']) : null,
                isset($payload['end_time']) ? SeasonDateRange::normalizeTime((string) $payload['end_time']) : null,
            );
        } else {
            foreach (['start_month', 'start_day', 'end_month', 'end_day'] as $field) {
                if (! isset($payload[$field])) {
                    throw LegalException::seasonInvalid('Annual seasons require month and day components.');
                }
            }
            SeasonSchedule::assertMonthDay((int) $payload['start_month'], (int) $payload['start_day']);
            SeasonSchedule::assertMonthDay((int) $payload['end_month'], (int) $payload['end_day']);
        }

        if ($precision === SeasonBoundaryPrecision::DateTime) {
            if (empty($payload['start_time']) || empty($payload['end_time'])) {
                throw LegalException::seasonInvalid('Datetime precision requires start_time and end_time.');
            }
        }

        $first = isset($payload['first_season_year']) ? (int) $payload['first_season_year'] : null;
        $last = isset($payload['last_season_year']) ? (int) $payload['last_season_year'] : null;
        if ($first !== null && $last !== null && $first > $last) {
            throw LegalException::seasonInvalid('first_season_year must not exceed last_season_year.');
        }
    }

    public function assertPublishable(LegalSeasonDefinition $definition): void
    {
        $definition->loadMissing(['rule.citations.provision.version.document.source']);
        $rule = $definition->rule;
        if ($rule === null || $rule->status !== LegalRuleStatus::Published) {
            throw LegalException::publicationInvalid(['season' => 'A published season requires a published legal rule.']);
        }
        app(LegalCitationIntegrityValidator::class)->assertPublishable($rule);
        $this->assertTypeMatchesEffect($definition->season_type, $rule->effect);
        $this->assertPayload([
            'activity_type' => $definition->activity_type->value,
            'season_type' => $definition->season_type->value,
            'schedule_type' => $definition->schedule_type->value,
            'boundary_precision' => $definition->boundary_precision->value,
            'timezone' => $definition->timezone,
            'start_date' => $definition->start_date?->toDateString(),
            'end_date' => $definition->end_date?->toDateString(),
            'start_month' => $definition->start_month,
            'start_day' => $definition->start_day,
            'end_month' => $definition->end_month,
            'end_day' => $definition->end_day,
            'start_time' => $definition->start_time,
            'end_time' => $definition->end_time,
            'first_season_year' => $definition->first_season_year,
            'last_season_year' => $definition->last_season_year,
        ], $rule);
    }

    private function assertTypeMatchesEffect(SeasonType $type, LegalRuleEffect $effect): void
    {
        if ($type->isPermission() && ! in_array($effect, [LegalRuleEffect::Allow, LegalRuleEffect::Condition, LegalRuleEffect::Require], true)) {
            throw LegalException::seasonInvalid('Opening seasons must link to an allow, condition, or require rule.');
        }
        if ($type->isProhibition() && ! in_array($effect, [LegalRuleEffect::Prohibit, LegalRuleEffect::Limit, LegalRuleEffect::Condition], true)) {
            throw LegalException::seasonInvalid('Closure and restriction seasons must link to a prohibit, limit, or condition rule.');
        }
    }
}
