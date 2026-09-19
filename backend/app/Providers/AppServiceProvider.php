<?php

namespace App\Providers;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Policies\AttributePolicy;
use App\Domains\Catalog\Policies\AttributeValuePolicy;
use App\Domains\Catalog\Policies\ProductPolicy;
use App\Domains\Catalog\Policies\ProductVariantPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeValue::class, AttributeValuePolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);

        // Password policy: min 12, mixed case, numbers.
        // Compromised-password checks run only in production (HIBP).
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers();

            return $this->app->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });

        RateLimiter::for('auth.login', function (Request $request) {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('auth.forgot-password', function (Request $request) {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.reset-password', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('auth.verification-resend', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(3)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('admin.mutations', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(30)->by($userId.'|'.$request->ip());
        });
    }
}
