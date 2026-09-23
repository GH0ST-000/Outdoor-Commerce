<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Support\QuoteFingerprint;

final class VerifyCheckoutQuoteIntegrityAction
{
    public function execute(CheckoutQuote $quote, CheckoutSession $session): void
    {
        $expected = QuoteFingerprint::fromStoredQuote($quote, $session);
        if (! hash_equals($quote->quote_fingerprint, $expected)) {
            throw CheckoutException::quoteIntegrityFailed();
        }
    }
}
