<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

enum BankOfGeorgiaErrorCategory: string
{
    case AuthenticationFailed = 'authentication_failed';
    case ConfigurationError = 'configuration_error';
    case InvalidRequest = 'invalid_request';
    case PaymentRejected = 'payment_rejected';
    case RateLimited = 'rate_limited';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderTimeout = 'provider_timeout';
    case MalformedResponse = 'malformed_response';
    case UnknownProviderError = 'unknown_provider_error';
}

final class BankOfGeorgiaErrorClassifier
{
    public function fromHttpStatus(int $status): BankOfGeorgiaErrorCategory
    {
        return match (true) {
            $status === 401, $status === 403 => BankOfGeorgiaErrorCategory::AuthenticationFailed,
            $status === 400, $status === 404, $status === 409, $status === 422 => BankOfGeorgiaErrorCategory::InvalidRequest,
            $status === 429 => BankOfGeorgiaErrorCategory::RateLimited,
            $status === 408, $status >= 500 => BankOfGeorgiaErrorCategory::ProviderUnavailable,
            default => BankOfGeorgiaErrorCategory::UnknownProviderError,
        };
    }

    public function isRetryable(BankOfGeorgiaErrorCategory $category): bool
    {
        return in_array($category, [
            BankOfGeorgiaErrorCategory::RateLimited,
            BankOfGeorgiaErrorCategory::ProviderUnavailable,
            BankOfGeorgiaErrorCategory::ProviderTimeout,
        ], true);
    }
}
