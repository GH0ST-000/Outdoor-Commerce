<?php

declare(strict_types=1);

namespace App\Domains\Legal\Actions;

use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\Exceptions\LegalException;

final class VerifyOutdoorContextTokenAction
{
    /**
     * @var list<string>
     */
    private const DROPPED = ['lat', 'lng', 'latitude', 'longitude', 'coordinate', 'coordinates', 'pin'];

    public function execute(string $token): DerivedLegalContextData
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new LegalException('The context token is invalid.', 'CONTEXT_TOKEN_INVALID');
        }
        $json = $this->decode($parts[0]);
        $signature = $this->decode($parts[1]);
        $expected = hash_hmac('sha256', $parts[0], $this->key(), true);
        if (! hash_equals($expected, $signature)) {
            throw new LegalException('The context token is invalid.', 'CONTEXT_TOKEN_INVALID');
        }
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        foreach (self::DROPPED as $key) {
            if (array_key_exists($key, $payload)) {
                throw new LegalException('The context token is invalid.', 'CONTEXT_TOKEN_INVALID');
            }
        }
        if ((int) ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            throw new LegalException('The context token has expired.', 'CONTEXT_TOKEN_EXPIRED');
        }
        unset($payload['exp'], $payload['v']);

        return DerivedLegalContextData::fromArray($payload);
    }

    public function sign(DerivedLegalContextData $context, int $expiresAt): string
    {
        $payload = $context->toArray();
        foreach (self::DROPPED as $key) {
            unset($payload[$key]);
        }
        $payload['v'] = 1;
        $payload['exp'] = $expiresAt;
        ksort($payload);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = $this->encode($json);
        $signature = $this->encode(hash_hmac('sha256', $body, $this->key(), true));

        return $body.'.'.$signature;
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded === false ? $key : $decoded;
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new LegalException('The context token is invalid.', 'CONTEXT_TOKEN_INVALID');
        }

        return $decoded;
    }
}
