<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Support\SeasonDateRange;
use DateTimeImmutable;

final class AvailabilityTimelineBuilder
{
    /**
     * @param  list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>  $intervals
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>
     */
    public function segment(SeasonDateRange $period, array $intervals): array
    {
        $boundaries = [$period->startsAt->getTimestamp(), $period->endsAtExclusive->getTimestamp()];
        foreach ($intervals as $interval) {
            $start = max($interval['start']->getTimestamp(), $period->startsAt->getTimestamp());
            $end = min($interval['end']->getTimestamp(), $period->endsAtExclusive->getTimestamp());
            if ($end <= $start) {
                continue;
            }
            $boundaries[] = $start;
            $boundaries[] = $end;
        }
        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries);

        $segments = [];
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $fromTs = $boundaries[$i];
            $toTs = $boundaries[$i + 1];
            if ($toTs <= $fromTs) {
                continue;
            }
            $from = (new DateTimeImmutable('@'.$fromTs))->setTimezone($period->startsAt->getTimezone());
            $to = (new DateTimeImmutable('@'.$toTs))->setTimezone($period->startsAt->getTimezone());
            $covering = [];
            foreach ($intervals as $interval) {
                if ($interval['start'] < $to && $interval['end'] > $from) {
                    $covering[] = $interval;
                }
            }
            $segments[] = [
                'start' => $from,
                'end' => $to,
                'state' => $this->combine($covering),
                'evidence' => $this->evidence($covering),
            ];
        }

        return $this->mergeAdjacent($segments);
    }

    /**
     * @param  list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>  $segments
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>
     */
    public function mergeAdjacent(array $segments): array
    {
        $merged = [];
        foreach ($segments as $segment) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null
                && $last['state'] === $segment['state']
                && $last['end'] == $segment['start']
                && $this->sameEvidence($last['evidence'], $segment['evidence'])) {
                $merged[array_key_last($merged)]['end'] = $segment['end'];

                continue;
            }
            $merged[] = $segment;
        }

        return $merged;
    }

    /**
     * @param  list<array{state: CalendarAvailabilityState, evidence: array<string, mixed>}>  $covering
     */
    private function combine(array $covering): CalendarAvailabilityState
    {
        if ($covering === []) {
            return CalendarAvailabilityState::Unknown;
        }

        $states = array_map(static fn (array $row): CalendarAvailabilityState => $row['state'], $covering);
        if (in_array(CalendarAvailabilityState::Conflict, $states, true)) {
            return CalendarAvailabilityState::Conflict;
        }

        $hasOpen = in_array(CalendarAvailabilityState::Open, $states, true)
            || in_array(CalendarAvailabilityState::Conditional, $states, true);
        $hasClosed = in_array(CalendarAvailabilityState::Closed, $states, true);
        if ($hasOpen && $hasClosed) {
            $explicit = false;
            foreach ($covering as $row) {
                if (($row['evidence']['precedence_explicit'] ?? false) === true) {
                    $explicit = true;
                    break;
                }
            }
            if (! $explicit) {
                return CalendarAvailabilityState::Conflict;
            }
            foreach ($covering as $row) {
                if ($row['state'] === CalendarAvailabilityState::Closed && ($row['evidence']['precedence_explicit'] ?? false) === true) {
                    return CalendarAvailabilityState::Closed;
                }
            }
        }
        if ($hasClosed) {
            return CalendarAvailabilityState::Closed;
        }
        if (in_array(CalendarAvailabilityState::Conditional, $states, true)) {
            return CalendarAvailabilityState::Conditional;
        }
        if (in_array(CalendarAvailabilityState::Open, $states, true)) {
            return CalendarAvailabilityState::Open;
        }

        return CalendarAvailabilityState::Unknown;
    }

    /**
     * @param  list<array{evidence: array<string, mixed>}>  $covering
     * @return array<string, mixed>
     */
    private function evidence(array $covering): array
    {
        $definitionIds = [];
        $overrideIds = [];
        $ruleIds = [];
        $citations = [];
        $limits = [];
        $conditions = [];
        $maxVerified = null;
        $precedenceExplicit = false;
        foreach ($covering as $row) {
            $evidence = $row['evidence'];
            foreach ($evidence['definition_ids'] ?? [] as $id) {
                $definitionIds[] = $id;
            }
            foreach ($evidence['override_ids'] ?? [] as $id) {
                $overrideIds[] = $id;
            }
            foreach ($evidence['rule_ids'] ?? [] as $id) {
                $ruleIds[] = $id;
            }
            foreach ($evidence['citations'] ?? [] as $citation) {
                $citations[] = $citation;
            }
            foreach ($evidence['limits'] ?? [] as $limit) {
                $limits[] = $limit;
            }
            foreach ($evidence['conditions'] ?? [] as $condition) {
                $conditions[] = $condition;
            }
            $verified = $evidence['last_verified_at'] ?? null;
            if (is_string($verified) && ($maxVerified === null || $verified > $maxVerified)) {
                $maxVerified = $verified;
            }
            $precedenceExplicit = $precedenceExplicit || (bool) ($evidence['precedence_explicit'] ?? false);
        }

        return [
            'definition_ids' => array_values(array_unique($definitionIds)),
            'override_ids' => array_values(array_unique($overrideIds)),
            'rule_ids' => array_values(array_unique($ruleIds)),
            'citations' => $citations,
            'limits' => $limits,
            'conditions' => $conditions,
            'last_verified_at' => $maxVerified,
            'precedence_explicit' => $precedenceExplicit,
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function sameEvidence(array $left, array $right): bool
    {
        return ($left['definition_ids'] ?? []) === ($right['definition_ids'] ?? [])
            && ($left['override_ids'] ?? []) === ($right['override_ids'] ?? [])
            && ($left['rule_ids'] ?? []) === ($right['rule_ids'] ?? []);
    }

    public static function stateFromEffect(LegalRuleEffect $effect): CalendarAvailabilityState
    {
        return match ($effect) {
            LegalRuleEffect::Allow => CalendarAvailabilityState::Open,
            LegalRuleEffect::Prohibit => CalendarAvailabilityState::Closed,
            LegalRuleEffect::Condition, LegalRuleEffect::Require, LegalRuleEffect::Limit => CalendarAvailabilityState::Conditional,
        };
    }
}
