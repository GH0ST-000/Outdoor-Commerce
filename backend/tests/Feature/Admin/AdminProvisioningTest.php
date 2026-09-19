<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('creates an administrator interactively without printing the password', function (): void {
    $this->syncAccessControl();

    $this->artisan('admin:create', [
        '--first-name' => 'Luka',
        '--last-name' => 'Admin',
        '--email' => 'admin@example.test',
    ])
        ->expectsQuestion('Password', 'SecurePass12')
        ->expectsQuestion('Confirm password', 'SecurePass12')
        ->expectsOutputToContain('Administrator created: admin@example.test')
        ->doesntExpectOutputToContain('SecurePass12')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.test')->first();
    expect($user)->not->toBeNull();
    expect($user?->hasRole(Role::Admin->value))->toBeTrue();
    expect(Hash::check('SecurePass12', (string) $user?->password))->toBeTrue();
    expect(AuditLog::query()->where('event', AuditEvent::AdministratorCreated->value)->exists())->toBeTrue();
});

it('promotes an existing user only after confirmation', function (): void {
    $this->syncAccessControl();
    $user = User::factory()->create(['email' => 'promote@example.test']);

    $this->artisan('admin:create', [
        '--first-name' => 'Promo',
        '--last-name' => 'User',
        '--email' => 'promote@example.test',
    ])
        ->expectsQuestion('Password', 'SecurePass12')
        ->expectsQuestion('Confirm password', 'SecurePass12')
        ->expectsConfirmation('A user with this email already exists. Promote to administrator?', 'yes')
        ->expectsOutputToContain('Existing user promoted to administrator')
        ->assertSuccessful();

    expect($user->fresh()?->hasRole(Role::Admin->value))->toBeTrue();
    expect(AuditLog::query()->where('event', AuditEvent::AdministratorPromoted->value)->exists())->toBeTrue();
});

it('enforces the password policy', function (): void {
    $this->syncAccessControl();

    $this->artisan('admin:create', [
        '--first-name' => 'Weak',
        '--last-name' => 'Admin',
        '--email' => 'weak@example.test',
    ])
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();
});
