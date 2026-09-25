<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Support\SpeciesLogger;

final class SpeciesPublicationStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['in_review'],
        'in_review' => ['draft', 'published'],
        'published' => ['in_review', 'archived'],
        'archived' => ['draft'],
    ];

    public function __construct(private readonly SpeciesLogger $logger) {}

    public function canTransition(SpeciesPublicationStatus $from, SpeciesPublicationStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertTransition(SpeciesPublicationStatus $from, SpeciesPublicationStatus $to): void
    {
        if ($this->canTransition($from, $to)) {
            return;
        }

        $this->logger->warning('invalid_transition', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        throw SpeciesException::invalidTransition($from->value, $to->value);
    }
}
