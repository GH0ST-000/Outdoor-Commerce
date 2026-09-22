<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class GuestOrderCookie
{
    public function rawToken(Request $request): ?string
    {
        $value = $request->cookie($this->name());

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function apply(Response $response, ?string $issuedToken, bool $forget): Response
    {
        if ($issuedToken !== null && $issuedToken !== '') {
            $response->headers->setCookie($this->make($issuedToken));
        }

        if ($forget) {
            $response->headers->clearCookie(
                $this->name(),
                $this->path(),
                $this->domain(),
                (bool) config('order.cookie.secure'),
                true,
                $this->sameSite(),
            );
        }

        return $response;
    }

    private function make(string $rawToken): Cookie
    {
        $minutes = max(1, (int) config('order.guest_access_ttl_days', 30) * 24 * 60);

        return cookie(
            $this->name(),
            $rawToken,
            $minutes,
            $this->path(),
            $this->domain(),
            (bool) config('order.cookie.secure'),
            true,
            false,
            $this->sameSite(),
        );
    }

    public function name(): string
    {
        return (string) config('order.cookie.name', 'outdoor_guest_order');
    }

    private function path(): string
    {
        return (string) config('order.cookie.path', '/');
    }

    private function domain(): ?string
    {
        $domain = config('order.cookie.domain');

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    private function sameSite(): string
    {
        $value = (string) config('order.cookie.same_site', 'lax');

        return in_array($value, ['lax', 'strict', 'none'], true) ? $value : 'lax';
    }
}
