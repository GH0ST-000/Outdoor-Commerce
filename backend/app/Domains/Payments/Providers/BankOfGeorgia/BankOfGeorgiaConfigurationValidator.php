<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Exceptions\PaymentException;
use Illuminate\Contracts\Foundation\Application;

final class BankOfGeorgiaConfigurationValidator
{
    /**
     * @var list<string>
     */
    private const ALLOWED_METHODS = ['card'];

    /**
     * @var list<string>
     */
    private const ALLOWED_API_HOSTS = ['api.bog.ge'];

    /**
     * @var list<string>
     */
    private const ALLOWED_OAUTH_HOSTS = ['oauth2.bog.ge'];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('payments.providers.bog.enabled', false);
    }

    public function isReady(): bool
    {
        return $this->issues() === [];
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        if (! $this->isEnabled()) {
            return ['provider_disabled'];
        }

        $issues = [];
        $clientId = trim((string) config('payments.providers.bog.client_id', ''));
        $clientSecret = (string) config('payments.providers.bog.client_secret', '');
        if ($clientId === '') {
            $issues[] = 'client_id';
        }
        if ($clientSecret === '') {
            $issues[] = 'client_secret';
        }

        $oauth = $this->validateHttpsUrl((string) config('payments.providers.bog.oauth_url', ''), self::ALLOWED_OAUTH_HOSTS);
        if ($oauth !== null) {
            $issues[] = $oauth;
        }

        $api = $this->validateHttpsUrl((string) config('payments.providers.bog.api_base_url', ''), self::ALLOWED_API_HOSTS);
        if ($api !== null) {
            $issues[] = $api;
        }

        $callback = $this->validatePublicUrl((string) config('payments.providers.bog.callback_url', ''), 'callback_url');
        if ($callback !== null) {
            $issues[] = $callback;
        }

        $success = $this->validatePublicUrl((string) config('payments.providers.bog.success_url', ''), 'success_url');
        if ($success !== null) {
            $issues[] = $success;
        }

        $failure = $this->validatePublicUrl((string) config('payments.providers.bog.fail_url', ''), 'fail_url');
        if ($failure !== null) {
            $issues[] = $failure;
        }

        if ($this->publicKeys() === []) {
            $issues[] = 'callback_public_key';
        }

        foreach ($this->allowedMethods() as $method) {
            if (! in_array($method, self::ALLOWED_METHODS, true)) {
                $issues[] = 'unsupported_payment_method:'.$method;
            }
        }

        $theme = (string) config('payments.providers.bog.theme', 'light');
        if (! in_array($theme, ['light', 'dark'], true)) {
            $issues[] = 'theme';
        }

        return array_values(array_unique($issues));
    }

    public function assertReady(): void
    {
        if (! $this->isReady()) {
            throw PaymentException::configurationInvalid();
        }
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        $configured = config('payments.providers.bog.allowed_methods', ['card']);
        if (! is_array($configured) || $configured === []) {
            return ['card'];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $method): string => strtolower(trim((string) $method)),
            $configured,
        )));
    }

    /**
     * @return list<string>
     */
    public function publicKeys(): array
    {
        $keys = [];
        $inline = trim((string) config('payments.providers.bog.callback_public_key', ''));
        if ($inline !== '') {
            $keys[] = $this->normalizePem($inline);
        }

        $path = trim((string) config('payments.providers.bog.callback_public_key_path', ''));
        if ($path !== '' && is_file($path) && is_readable($path)) {
            $contents = file_get_contents($path);
            if (is_string($contents) && trim($contents) !== '') {
                $keys[] = $this->normalizePem($contents);
            }
        }

        $previous = trim((string) config('payments.providers.bog.callback_public_key_previous', ''));
        if ($previous !== '') {
            $keys[] = $this->normalizePem($previous);
        }

        $documented = trim((string) config('payments.providers.bog.documented_callback_public_key', ''));
        if ($documented !== '' && $keys === []) {
            $keys[] = $this->normalizePem($documented);
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    private function validateHttpsUrl(string $url, array $allowedHosts): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'invalid_url';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        if ($scheme !== 'https') {
            return 'https_required';
        }
        if (! in_array($host, $allowedHosts, true)) {
            return 'host_not_allowed';
        }

        return null;
    }

    private function validatePublicUrl(string $url, string $name): ?string
    {
        if ($url === '') {
            return $name;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $name;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $httpsRequired = (bool) config('payments.https_required', false) || $this->app->environment('production');
        if ($httpsRequired && $scheme !== 'https') {
            return $name.'_https_required';
        }
        if (! in_array($scheme, ['https', 'http'], true)) {
            return $name;
        }

        return null;
    }

    private function normalizePem(string $pem): string
    {
        $trimmed = trim(str_replace('\\n', "\n", $pem));
        if (! str_contains($trimmed, 'BEGIN PUBLIC KEY')) {
            $trimmed = "-----BEGIN PUBLIC KEY-----\n".trim($trimmed)."\n-----END PUBLIC KEY-----";
        }

        return $trimmed;
    }
}
