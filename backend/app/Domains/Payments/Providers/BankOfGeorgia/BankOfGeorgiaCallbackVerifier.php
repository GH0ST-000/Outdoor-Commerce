<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Support\PaymentLogger;

/**
 * SHA256withRSA verification of the exact raw callback bytes.
 * Official header: Callback-Signature. Reviewed 2026-09-23.
 *
 * @see https://api.bog.ge/docs/en/payments/standard-process/callback
 */
final class BankOfGeorgiaCallbackVerifier
{
    public function __construct(
        private readonly BankOfGeorgiaConfigurationValidator $config,
        private readonly PaymentLogger $logger,
    ) {}

    public function verify(string $rawBody, string $signatureHeader): void
    {
        if ($rawBody === '') {
            $this->logger->warning('bog_callback_signature_failure', ['provider' => 'bog', 'reason' => 'empty_body']);
            throw PaymentException::signatureInvalid();
        }

        $signature = trim($signatureHeader);
        if ($signature === '') {
            $this->logger->warning('bog_callback_signature_failure', ['provider' => 'bog', 'reason' => 'missing']);
            throw PaymentException::signatureInvalid();
        }

        $decoded = base64_decode($signature, true);
        if ($decoded === false || $decoded === '') {
            $this->logger->warning('bog_callback_signature_failure', ['provider' => 'bog', 'reason' => 'malformed_base64']);
            throw PaymentException::signatureInvalid();
        }

        foreach ($this->config->publicKeys() as $pem) {
            $key = openssl_pkey_get_public($pem);
            if ($key === false) {
                continue;
            }
            $ok = openssl_verify($rawBody, $decoded, $key, OPENSSL_ALGO_SHA256);
            if ($ok === 1) {
                $this->logger->info('bog_callback_signature_ok', ['provider' => 'bog']);

                return;
            }
        }

        $this->logger->warning('bog_callback_signature_failure', ['provider' => 'bog', 'reason' => 'mismatch']);
        throw PaymentException::signatureInvalid();
    }
}
