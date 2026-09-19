<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\LoginCustomerData;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Events\CustomerLoggedIn;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

final class AuthenticateCustomerAction
{
    public function execute(LoginCustomerData $data): User
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user instanceof User || ! Hash::check($data->password, $user->password)) {
            throw AuthenticationFailedException::invalidCredentials();
        }

        if ($user->status !== UserStatus::Active) {
            throw AuthenticationFailedException::invalidCredentials();
        }

        Auth::guard('web')->login($user, $data->remember);
        Session::regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        RateLimiter::clear($data->email.'|'.request()->ip());

        event(new CustomerLoggedIn($user->id));

        return $user->fresh() ?? $user;
    }
}
