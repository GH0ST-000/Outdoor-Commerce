<?php

declare(strict_types=1);

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaStatusMapper;

$mapper = new BankOfGeorgiaStatusMapper;

it('maps official Bank of Georgia status keys explicitly', function () use ($mapper): void {
    expect($mapper->toNormalizedStatus('created'))->toBe(PaymentAttemptStatus::RequiresAction)
        ->and($mapper->toNormalizedStatus('processing'))->toBe(PaymentAttemptStatus::Processing)
        ->and($mapper->toNormalizedStatus('completed'))->toBe(PaymentAttemptStatus::Succeeded)
        ->and($mapper->toNormalizedStatus('rejected'))->toBe(PaymentAttemptStatus::Failed)
        ->and($mapper->toNormalizedStatus('refund_requested'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('refunded'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('refunded_partially'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('auth_requested'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('blocked'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('partial_completed'))->toBe(PaymentAttemptStatus::ManualReview)
        ->and($mapper->toNormalizedStatus('something_new'))->toBe(PaymentAttemptStatus::Unknown);
});

it('never maps an unknown status to success', function () use ($mapper): void {
    expect($mapper->toNormalizedStatus('completed_label'))->not->toBe(PaymentAttemptStatus::Succeeded)
        ->and($mapper->toNormalizedStatus(''))->not->toBe(PaymentAttemptStatus::Succeeded);
});
