<?php

declare(strict_types=1);

namespace App\Domains\Payments\Support;

use App\Domains\Payments\Exceptions\PaymentException;

final class PaymentRedirectUrlValidator
{
    public function validate(string $url): string
    {
        $max = max(1, (int) config('payments.max_redirect_url_length', 2048));
        if ($url === '' || strlen($url) > $max) {
            throw PaymentException::invalidRedirect();
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw PaymentException::invalidRedirect();
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw PaymentException::invalidRedirect();
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        $allowedSchemes = config('payments.allowed_redirect_schemes', ['https']);
        if (! is_array($allowedSchemes) || ! in_array($scheme, $allowedSchemes, true)) {
            throw PaymentException::invalidRedirect();
        }

        if (in_array($scheme, ['javascript', 'data', 'file', 'vbscript'], true)) {
            throw PaymentException::invalidRedirect();
        }

        $httpsRequired = (bool) config('payments.https_required', false);
        if ($httpsRequired && $scheme !== 'https') {
            throw PaymentException::invalidRedirect();
        }

        $allowedHosts = config('payments.allowed_redirect_hosts', []);
        if (! is_array($allowedHosts) || $allowedHosts === [] || ! in_array($host, $allowedHosts, true)) {
            throw PaymentException::invalidRedirect();
        }

        return $url;
    }
}
