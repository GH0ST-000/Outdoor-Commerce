<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use App\Domains\Legal\Exceptions\LegalException;

/**
 * SSRF-resistant HTTPS retrieval for verified official domains only.
 */
final class LegalUrlGuard
{
    /**
     * @var list<string>
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public function assertRegisteredHttps(string $url, ?string $allowedDomain): string
    {
        return $this->assertHttpsShape($url, $allowedDomain);
    }

    public function assertAllowedHttps(string $url, ?string $allowedDomain): string
    {
        $parts = $this->parsedHttps($url, $allowedDomain);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $ips = gethostbynamel($host) ?: [];
        if ($ips === []) {
            throw LegalException::urlRejected('The official host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw LegalException::urlRejected();
            }
        }

        return $url;
    }

    public function assertHttpsShape(string $url, ?string $allowedDomain): string
    {
        $this->parsedHttps($url, $allowedDomain);

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedHttps(string $url, ?string $allowedDomain): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            throw LegalException::urlRejected('Only HTTPS official URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw LegalException::urlRejected('URLs must not contain credentials.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.local')) {
            throw LegalException::urlRejected();
        }

        if (! (bool) config('legal.retrieval.allow_custom_ports', false) && isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw LegalException::urlRejected('Custom ports are not allowed.');
        }

        if ($allowedDomain !== null && $allowedDomain !== '') {
            $allowed = strtolower(ltrim($allowedDomain, '.'));
            if ($host !== $allowed && ! str_ends_with($host, '.'.$allowed)) {
                throw LegalException::urlRejected('The URL host is not on the approved official domain.');
            }
        }

        return $parts;
    }

    public function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return true;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return true;
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false
            || str_starts_with($ip, '169.254.')
            || str_starts_with($ip, '127.')
            || $ip === '::1'
            || $ip === '0.0.0.0';
    }
}
