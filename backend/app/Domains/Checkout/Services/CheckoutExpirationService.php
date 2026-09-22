<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Events\CheckoutQuoteExpired;
use App\Domains\Checkout\Events\CheckoutSessionExpired;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Support\CheckoutLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class CheckoutExpirationService
{
    public function __construct(
        private readonly CheckoutReservationService $reservations,
        private readonly Clock $clock,
        private readonly CheckoutLogger $logger,
    ) {}

    public function enforceSession(CheckoutSession $session): CheckoutSession
    {
        $now = $this->clock->now();
        if ($session->status === CheckoutSessionStatus::Expired
            || $session->status === CheckoutSessionStatus::Cancelled
            || $session->status === CheckoutSessionStatus::Converted
        ) {
            if ($session->currentQuote !== null) {
                $this->enforceQuote($session->currentQuote);
            }

            return $session;
        }

        if ($session->expires_at !== null && $session->expires_at->lessThanOrEqualTo($now)) {
            $this->expireSession($session, 'ttl');
        }

        if ($session->currentQuote !== null) {
            $this->enforceQuote($session->currentQuote);
            $session->unsetRelation('currentQuote');
        }

        return $session->fresh() ?? $session;
    }

    public function enforceQuote(CheckoutQuote $quote): CheckoutQuote
    {
        if ($quote->status !== CheckoutQuoteStatus::Active) {
            return $quote;
        }

        if ($quote->expires_at->greaterThan($this->clock->now())) {
            return $quote;
        }

        $this->expireQuote($quote, 'ttl');

        return $quote->fresh() ?? $quote;
    }

    public function expireDueQuotes(?int $chunkSize = null): int
    {
        $chunkSize = $chunkSize ?? (int) config('checkout.expire_chunk_size', 100);
        $ids = CheckoutQuote::query()
            ->where('status', CheckoutQuoteStatus::Active)
            ->where('expires_at', '<=', $this->clock->now())
            ->orderBy('id')
            ->limit($chunkSize)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count): void {
                $quote = CheckoutQuote::query()->whereKey($id)->lockForUpdate()->first();
                if ($quote instanceof CheckoutQuote && $quote->status === CheckoutQuoteStatus::Active) {
                    $this->expireQuote($quote, 'scheduler');
                    $count++;
                }
            });
        }

        return $count;
    }

    public function expireDueSessions(?int $chunkSize = null): int
    {
        $chunkSize = $chunkSize ?? (int) config('checkout.expire_chunk_size', 100);
        $ids = CheckoutSession::query()
            ->whereIn('status', [
                CheckoutSessionStatus::Draft,
                CheckoutSessionStatus::Ready,
                CheckoutSessionStatus::Quoted,
            ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $this->clock->now())
            ->orderBy('id')
            ->limit($chunkSize)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count): void {
                $session = CheckoutSession::query()->whereKey($id)->lockForUpdate()->first();
                if ($session instanceof CheckoutSession && $session->status->isMutable()) {
                    $this->expireSession($session, 'scheduler');
                    $count++;
                }
            });
        }

        return $count;
    }

    public function expireQuote(CheckoutQuote $quote, string $reason): void
    {
        if ($quote->status === CheckoutQuoteStatus::Consumed || $quote->status === CheckoutQuoteStatus::Cancelled) {
            return;
        }

        if ($quote->status === CheckoutQuoteStatus::Expired) {
            return;
        }

        $this->reservations->releaseQuote($quote, 'quote_expired');
        $quote->status = CheckoutQuoteStatus::Expired;
        $quote->save();

        $this->logger->info('quote_expired', [
            'quote_public_id' => $quote->public_id,
            'reason' => $reason,
        ]);

        $sessionPublicId = $quote->session->public_id;
        DB::afterCommit(function () use ($quote, $sessionPublicId): void {
            event(new CheckoutQuoteExpired($sessionPublicId, $quote->public_id));
        });
    }

    public function expireSession(CheckoutSession $session, string $reason): void
    {
        $session->loadMissing('quotes');
        foreach ($session->quotes as $quote) {
            if ($quote->status === CheckoutQuoteStatus::Active) {
                $this->expireQuote($quote, 'session_expired');
            }
        }

        $session->status = CheckoutSessionStatus::Expired;
        $session->current_quote_id = $session->current_quote_id;
        $session->save();

        $this->logger->info('session_expired', [
            'session_public_id' => $session->public_id,
            'reason' => $reason,
        ]);

        DB::afterCommit(function () use ($session): void {
            event(new CheckoutSessionExpired($session->public_id));
        });
    }
}
