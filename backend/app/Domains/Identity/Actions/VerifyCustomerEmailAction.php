<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Events\CustomerEmailVerified;
use App\Domains\Identity\Models\User;

/**
 * Marks an email address as verified. Idempotent: replaying a still-valid
 * verification link is a no-op and does not re-dispatch the domain event.
 */
final class VerifyCustomerEmailAction
{
    /**
     * @return bool True when this call performed the verification.
     */
    public function execute(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();

        event(new CustomerEmailVerified($user->id));

        return true;
    }
}
