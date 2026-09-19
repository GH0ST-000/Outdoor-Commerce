<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Actions\CreateAdministratorAction;
use App\Domains\Identity\DTOs\CreateAdministratorData;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

final class CreateAdministratorCommand extends Command
{
    protected $signature = 'admin:create
                            {--first-name= : First name (non-interactive)}
                            {--last-name= : Last name (non-interactive)}
                            {--email= : Email (non-interactive)}
                            {--promote : Promote an existing active user without prompting}';

    protected $description = 'Create or promote an administrator securely';

    public function handle(CreateAdministratorAction $action): int
    {
        $firstName = $this->option('first-name') ?: $this->ask('First name');
        $lastName = $this->option('last-name') ?: $this->ask('Last name');
        $email = $this->option('email') ?: $this->ask('Email');

        if (! is_string($firstName) || ! is_string($lastName) || ! is_string($email)) {
            $this->error('First name, last name, and email are required.');

            return self::FAILURE;
        }

        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        if (! is_string($password) || $password === '') {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('Password confirmation does not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => $password,
            ],
            [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $normalizedEmail = EmailNormalizer::normalize($email);
        $existing = User::query()->where('email', $normalizedEmail)->first();
        $confirmPromote = false;

        if ($existing !== null) {
            $confirmPromote = (bool) $this->option('promote')
                || $this->confirm('A user with this email already exists. Promote to administrator?');

            if (! $confirmPromote) {
                $this->warn('Aborted. No changes were made.');

                return self::FAILURE;
            }
        }

        try {
            $result = $action->execute(
                new CreateAdministratorData(
                    firstName: $firstName,
                    lastName: $lastName,
                    email: $email,
                    password: $password,
                    markEmailVerified: true,
                ),
                confirmPromoteExisting: $confirmPromote,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            unset($password, $confirmation);
        }

        $user = $result['user'];

        if ($result['created']) {
            $this->info("Administrator created: {$user->email}");
        } elseif ($result['promoted']) {
            $this->info("Existing user promoted to administrator: {$user->email}");
        }

        return self::SUCCESS;
    }
}
