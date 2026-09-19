<?php

declare(strict_types=1);

use App\Domains\Identity\Events\CustomerLoggedIn;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Event;

it('logs in an active customer and updates last_login_at', function (): void {
    Event::fake([CustomerLoggedIn::class]);

    $user = User::factory()->create([
        'email' => 'luka@example.test',
        'password' => 'SecurePass12',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'Luka@Example.TEST',
        'password' => 'SecurePass12',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.email', 'luka@example.test')
        ->assertJsonMissingPath('data.password')
        ->assertHeader('X-Request-ID');

    $this->assertAuthenticated();
    expect($user->fresh()?->last_login_at)->not->toBeNull();
    Event::assertDispatched(CustomerLoggedIn::class);
});

it('rejects invalid credentials and disabled users with the same generic error', function (): void {
    User::factory()->create([
        'email' => 'luka@example.test',
        'password' => 'SecurePass12',
    ]);

    $unknown = $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.test',
        'password' => 'SecurePass12',
    ]);

    $wrong = $this->postJson('/api/v1/auth/login', [
        'email' => 'luka@example.test',
        'password' => 'WrongPassword1',
    ]);

    $disabled = User::factory()->disabled()->create([
        'email' => 'disabled@example.test',
        'password' => 'SecurePass12',
    ]);

    $disabledResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'disabled@example.test',
        'password' => 'SecurePass12',
    ]);

    foreach ([$unknown, $wrong, $disabledResponse] as $response) {
        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('error.message', 'These credentials do not match our records.');
    }

    expect($disabled->fresh()?->last_login_at)->toBeNull();
});

it('logs out and rejects subsequent me requests', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->assertGuest('web');

    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHORIZED');
});

it('returns the current user for active sessions and rejects disabled sessions', function (): void {
    $user = User::factory()->create([
        'email' => 'me@example.test',
    ]);

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'me@example.test')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    $this->app['auth']->forgetGuards();

    $disabled = User::factory()->disabled()->create();

    $this->actingAs($disabled, 'web')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});
