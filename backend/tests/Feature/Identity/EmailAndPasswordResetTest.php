<?php

declare(strict_types=1);

use App\Domains\Identity\Events\CustomerEmailVerified;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Notifications\ResetCustomerPassword;
use App\Domains\Identity\Notifications\VerifyCustomerEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

it('verifies email with a valid signed URL and is idempotent', function (): void {
    Event::fake([CustomerEmailVerified::class]);

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.status', 'success');

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatchedTimes(CustomerEmailVerified::class, 1);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.status', 'already');

    Event::assertDispatchedTimes(CustomerEmailVerified::class, 1);
});

it('rejects invalid verification hashes and signatures', function (): void {
    $user = User::factory()->unverified()->create();

    $badHash = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('other@example.test')],
    );

    $this->getJson($badHash)->assertForbidden();

    $unsigned = '/api/v1/auth/email/verify/'.$user->id.'/'.sha1($user->email);
    $this->getJson($unsigned)->assertForbidden();
});

it('resends verification for unverified users', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user, 'web')
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(202);

    Notification::assertSentTo($user, VerifyCustomerEmail::class);
});

it('returns a generic forgot-password response and resets with a valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.test',
        'password' => 'SecurePass12',
        'remember_token' => 'old-remember-token',
    ]);

    $known = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'reset@example.test',
    ]);

    $unknown = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'missing@example.test',
    ]);

    $known->assertStatus(202)->assertJsonPath('data.status', 'accepted');
    $unknown->assertStatus(202)->assertJsonPath('data.status', 'accepted');
    expect($known->json('data.message'))->toBe($unknown->json('data.message'));

    Notification::assertSentTo($user, ResetCustomerPassword::class);

    $token = Password::createToken($user);
    $oldRemember = $user->remember_token;

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.test',
        'token' => $token,
        'password' => 'NewSecurePass12',
        'password_confirmation' => 'NewSecurePass12',
    ])->assertOk()->assertJsonPath('data.status', 'password_reset');

    $user->refresh();
    expect(Hash::check('NewSecurePass12', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe($oldRemember);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.test',
        'token' => $token,
        'password' => 'AnotherSecure1',
        'password_confirmation' => 'AnotherSecure1',
    ])->assertStatus(422)->assertJsonPath('error.code', 'PASSWORD_RESET_FAILED');
});
