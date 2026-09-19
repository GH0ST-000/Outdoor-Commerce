<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\RegisterCustomerData;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Events\CustomerRegistered;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

final class RegisterCustomerAction
{
    public function execute(RegisterCustomerData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User;
            $user->first_name = $data->firstName;
            $user->last_name = $data->lastName;
            $user->email = $data->email;
            $user->phone = $data->phone;
            $user->preferred_locale = $data->preferredLocale ?? 'ka';
            $user->password = $data->password;
            $user->status = UserStatus::Active;
            $user->email_verified_at = null;
            $user->save();

            return $user;
        });

        Auth::guard('web')->login($user);
        Session::regenerate();

        $user->sendEmailVerificationNotification();
        event(new CustomerRegistered($user->id));

        return $user->fresh() ?? $user;
    }
}
