<?php

declare(strict_types=1);

use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Services\SpeciesPublicationStateMachine;
use App\Domains\Hunting\Support\SpeciesLogger;

it('allows only documented publication transitions', function (): void {
    $machine = new SpeciesPublicationStateMachine(new SpeciesLogger);

    expect($machine->canTransition(SpeciesPublicationStatus::Draft, SpeciesPublicationStatus::InReview))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::InReview, SpeciesPublicationStatus::Draft))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::InReview, SpeciesPublicationStatus::Published))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::Published, SpeciesPublicationStatus::InReview))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::Published, SpeciesPublicationStatus::Archived))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::Archived, SpeciesPublicationStatus::Draft))->toBeTrue()
        ->and($machine->canTransition(SpeciesPublicationStatus::Draft, SpeciesPublicationStatus::Published))->toBeFalse()
        ->and($machine->canTransition(SpeciesPublicationStatus::Archived, SpeciesPublicationStatus::Published))->toBeFalse();
});
