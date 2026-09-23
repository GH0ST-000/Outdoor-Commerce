<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Cart\Contracts\CartOwnerResolver;
use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Shared\Support\Clock;

final class LockCheckoutQuoteForOrderAction
{
    public function __construct(
        private readonly CartOwnerResolver $carts,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{session: CheckoutSession, quote: CheckoutQuote}
     */
    public function execute(CheckoutActorData $actor, string $sessionPublicId, string $quotePublicId): array
    {
        $session = CheckoutSession::query()
            ->where('public_id', $sessionPublicId)
            ->lockForUpdate()
            ->first();

        if ($session === null) {
            throw CheckoutException::sessionNotFound();
        }

        $this->assertOwnership($session, $actor);

        if ($session->expires_at !== null && $session->expires_at->lte($this->clock->now())
            && $session->status !== CheckoutSessionStatus::Converted
            && $session->status !== CheckoutSessionStatus::Cancelled) {
            $session->status = CheckoutSessionStatus::Expired;
            $session->save();
        }

        if ($session->status === CheckoutSessionStatus::Cancelled) {
            throw CheckoutException::sessionNotFound();
        }

        $quote = CheckoutQuote::query()
            ->where('public_id', $quotePublicId)
            ->lockForUpdate()
            ->first();

        if ($quote === null || $quote->checkout_session_id !== $session->id) {
            throw CheckoutException::quoteNotFound();
        }

        $quote->load(['lines', 'adjustments']);

        return ['session' => $session, 'quote' => $quote];
    }

    private function assertOwnership(CheckoutSession $session, CheckoutActorData $actor): void
    {
        if ($actor->userId() !== null) {
            if ($session->user_id !== $actor->userId()) {
                throw CheckoutException::sessionNotFound();
            }

            return;
        }

        $hash = $this->carts->hashGuestToken($actor->cartActor->rawGuestToken);
        if ($hash === null || $session->guest_token_hash !== $hash || $session->user_id !== null) {
            throw CheckoutException::sessionNotFound();
        }
    }
}
