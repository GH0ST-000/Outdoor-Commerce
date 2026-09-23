<?php

declare(strict_types=1);

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\PaymentStateMachine;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\SystemClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the documented payment transitions and treats success as terminal', function (): void {
    $machine = new PaymentStateMachine(new SystemClock, new PaymentLogger);
    $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Created]);

    $machine->recordInitial($attempt, 'created');
    expect($machine->canTransition(PaymentAttemptStatus::Created, PaymentAttemptStatus::RequiresAction))->toBeTrue();
    $machine->transition($attempt, PaymentAttemptStatus::RequiresAction, 'provider_create');
    $machine->transition($attempt, PaymentAttemptStatus::Succeeded, 'verified');

    expect($attempt->fresh()?->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($machine->transition($attempt->fresh() ?? $attempt, PaymentAttemptStatus::Failed, 'late_fail'))->toBeFalse()
        ->and($attempt->fresh()?->status)->toBe(PaymentAttemptStatus::Succeeded);

    $other = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Succeeded]);
    expect($machine->canTransition(PaymentAttemptStatus::Succeeded, PaymentAttemptStatus::Failed))->toBeFalse();
    expect(fn () => $machine->transition($other, PaymentAttemptStatus::Failed, 'illegal'))
        ->not->toThrow(PaymentException::class);
});
