<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentAttemptStatusHistory;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;

final class PaymentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'created' => ['pending', 'requires_action', 'failed', 'unknown', 'cancelled', 'expired'],
        'requires_action' => ['processing', 'succeeded', 'failed', 'cancelled', 'expired', 'unknown', 'manual_review'],
        'pending' => ['processing', 'succeeded', 'failed', 'cancelled', 'expired', 'unknown', 'requires_action', 'manual_review'],
        'processing' => ['succeeded', 'failed', 'unknown', 'manual_review', 'cancelled', 'expired'],
        'unknown' => ['pending', 'processing', 'succeeded', 'failed', 'manual_review', 'cancelled', 'expired', 'requires_action'],
        'succeeded' => [],
        'failed' => ['manual_review', 'succeeded'],
        'cancelled' => ['manual_review', 'succeeded'],
        'expired' => ['manual_review', 'succeeded'],
        'manual_review' => ['succeeded'],
    ];

    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function canTransition(PaymentAttemptStatus $from, PaymentAttemptStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function transition(
        PaymentAttempt $attempt,
        PaymentAttemptStatus $to,
        ?string $reason = null,
        ?array $metadata = null,
    ): bool {
        $from = $attempt->status;
        if ($from === $to) {
            return false;
        }

        if ($from === PaymentAttemptStatus::Succeeded) {
            $this->logger->warning('state_transition_ignored_after_success', [
                'payment_attempt_public_id' => $attempt->public_id,
                'from' => $from->value,
                'to' => $to->value,
                'reason_code' => $reason,
            ]);

            return false;
        }

        if (! $this->canTransition($from, $to)) {
            $this->logger->warning('state_transition_rejected', [
                'payment_attempt_public_id' => $attempt->public_id,
                'from' => $from->value,
                'to' => $to->value,
                'reason_code' => $reason,
            ]);
            throw PaymentException::invalidTransition($from->value, $to->value);
        }

        $now = $this->clock->now();
        $attempt->status = $to;
        $attempt->version = $attempt->version + 1;

        match ($to) {
            PaymentAttemptStatus::Succeeded => $attempt->succeeded_at = $attempt->succeeded_at ?? $now,
            PaymentAttemptStatus::Failed => $attempt->failed_at = $now,
            PaymentAttemptStatus::Cancelled => $attempt->cancelled_at = $now,
            PaymentAttemptStatus::Expired => $attempt->expired_at = $now,
            default => null,
        };

        $attempt->save();

        PaymentAttemptStatusHistory::query()->create([
            'payment_attempt_id' => $attempt->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason_code' => $reason,
            'metadata' => $metadata,
            'created_at' => $now,
        ]);

        $this->logger->info('state_transition', [
            'payment_attempt_public_id' => $attempt->public_id,
            'from' => $from->value,
            'to' => $to->value,
            'reason_code' => $reason,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordInitial(PaymentAttempt $attempt, ?string $reason = null, ?array $metadata = null): void
    {
        PaymentAttemptStatusHistory::query()->create([
            'payment_attempt_id' => $attempt->id,
            'from_status' => null,
            'to_status' => $attempt->status,
            'reason_code' => $reason,
            'metadata' => $metadata,
            'created_at' => $this->clock->now(),
        ]);
    }
}
