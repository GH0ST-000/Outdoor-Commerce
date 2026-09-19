<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Ends the current stateful session and makes the old session cookie useless.
 */
final class LogoutCustomerAction
{
    public function execute(): void
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');

        $guard->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
