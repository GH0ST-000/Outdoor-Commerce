<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum CheckoutRestrictionOutcome: string
{
    case Allowed = 'allowed';
    case ManualReviewRequired = 'manual_review_required';
    case CheckoutBlocked = 'checkout_blocked';
}
