<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Events\CustomerRegistered;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Notifications\VerifyCustomerEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Luka',
        'last_name' => 'Datunashvili',
        'email' => 'luka@example.test',
        'password' => 'SecurePass12',
        'password_confirmation' => 'SecurePass12',
    ], $overrides);
}

it('registers a customer, authenticates the session, and sends verification', function (): void {
    Notification::fake();
    Event::fake([CustomerRegistered::class]);

    $response = $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'ლუკა',
        'last_name' => 'დათუნაშვილი',
        'email' => 'Luka@Example.TEST',
    ]));

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'luka@example.test')
        ->assertJsonPath('data.first_name', 'ლუკა')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertHeader('X-Request-ID');

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'luka@example.test')->firstOrFail();
    expect(Hash::check('SecurePass12', $user->password))->toBeTrue();
    expect($user->status)->toBe(UserStatus::Active);

    Notification::assertSentTo($user, VerifyCustomerEmail::class);
    Event::assertDispatched(CustomerRegistered::class);
});

it('rejects duplicate emails and weak passwords', function (): void {
    User::factory()->create(['email' => 'luka@example.test']);

    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');

    $this->postJson('/api/v1/auth/register', registerPayload([
        'email' => 'other@example.test',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->assertStatus(422);
});

it('ignores injected role status and verification fields', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registerPayload([
        'status' => 'disabled',
        'role' => 'admin',
        'email_verified_at' => now()->toISOString(),
    ]))->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.email_verified', false);

    expect(User::query()->firstOrFail()->status)->toBe(UserStatus::Active);
});

it('enforces registration rate limiting', function (): void {
    Notification::fake();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/register', registerPayload([
            'email' => "user{$i}@example.test",
        ]))->assertCreated();
        $this->postJson('/api/v1/auth/logout');
    }

    $this->postJson('/api/v1/auth/register', registerPayload([
        'email' => 'overflow@example.test',
    ]))->assertStatus(429)
        ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
});
