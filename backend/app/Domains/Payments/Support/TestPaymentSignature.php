<?php

declare(strict_types=1);

namespace App\Domains\Payments\Support;

final class TestPaymentSignature
{
    public function sign(string $rawBody, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public function verify(string $rawBody, string $signature, int $timestamp, string $secret, int $toleranceSeconds): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }

        $now = time();
        if (abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = $this->sign($rawBody, $timestamp, $secret);

        return hash_equals($expected, $signature);
    }
}
