<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class GuestCartCookie
{
    public function rawToken(Request $request): ?string
    {
        $value = $request->cookie($this->name());

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function queue(string $rawToken): void
    {
        cookie()->queue($this->make($rawToken));
    }

    public function forget(Response $response): void
    {
        $response->headers->clearCookie(
            $this->name(),
            $this->path(),
            $this->domain(),
            (bool) config('cart.cookie.secure'),
            true,
            $this->sameSite(),
        );
    }

    public function apply(Response $response, ?string $issuedToken, bool $forget): Response
    {
        if ($issuedToken !== null && $issuedToken !== '') {
            $response->headers->setCookie($this->make($issuedToken));
        }

        if ($forget) {
            $this->forget($response);
        }

        return $response;
    }

    private function make(string $rawToken): Cookie
    {
        $minutes = max(1, (int) config('cart.guest_ttl_days', 30) * 24 * 60);

        return cookie(
            $this->name(),
            $rawToken,
            $minutes,
            $this->path(),
            $this->domain(),
            (bool) config('cart.cookie.secure'),
            true,
            false,
            $this->sameSite(),
        );
    }

    private function name(): string
    {
        return (string) config('cart.cookie.name', 'outdoor_guest_cart');
    }

    private function path(): string
    {
        return (string) config('cart.cookie.path', '/');
    }

    private function domain(): ?string
    {
        $domain = config('cart.cookie.domain');

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    private function sameSite(): string
    {
        $value = (string) config('cart.cookie.same_site', 'lax');

        return in_array($value, ['lax', 'strict', 'none'], true) ? $value : 'lax';
    }
}
