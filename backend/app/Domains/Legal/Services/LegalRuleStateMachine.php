<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Support\LegalLogger;

final class LegalRuleStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['in_review'],
        'in_review' => ['draft', 'approved', 'rejected'],
        'approved' => ['published', 'in_review', 'rejected'],
        'published' => ['superseded', 'archived'],
        'rejected' => ['draft'],
        'superseded' => ['archived'],
        'archived' => [],
    ];

    public function __construct(private readonly LegalLogger $logger) {}

    public function canTransition(LegalRuleStatus $from, LegalRuleStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertTransition(LegalRuleStatus $from, LegalRuleStatus $to): void
    {
        if ($this->canTransition($from, $to)) {
            return;
        }

        $this->logger->warning('invalid_rule_transition', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        throw LegalException::invalidTransition($from->value, $to->value);
    }

    public function assertVersionTransition(LegalReviewStatus $from, LegalReviewStatus $to): void
    {
        $allowed = [
            'draft' => ['in_review'],
            'in_review' => ['draft', 'approved', 'rejected'],
            'approved' => ['superseded', 'archived'],
            'rejected' => ['draft'],
            'superseded' => ['archived'],
            'archived' => [],
        ];

        if (in_array($to->value, $allowed[$from->value] ?? [], true)) {
            return;
        }

        throw LegalException::invalidTransition($from->value, $to->value);
    }
}
