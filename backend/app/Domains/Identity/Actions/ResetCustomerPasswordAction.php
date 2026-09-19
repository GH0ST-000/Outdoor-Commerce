<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class ResetCustomerPasswordAction
{
    /**
     * @return string Password broker status constant
     */
    public function execute(string $email, string $token, string $password): string
    {
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                Event::dispatch(new PasswordReset($user));

                if (Auth::id() === $user->id) {
                    Auth::guard('web')->logout();
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new AuthenticationFailedException(
                'Unable to reset the password with the provided details.',
                'PASSWORD_RESET_FAILED',
            );
        }

        return $status;
    }
}
