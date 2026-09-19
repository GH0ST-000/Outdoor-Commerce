<?php

declare(strict_types=1);

namespace App\Domains\Operations\Actions;

use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Support\Str;

/**
 * Persist a sanitized audit event. Failures throw so privileged callers can
 * keep role/status changes atomic with the audit row.
 */
final class RecordAuditEventAction
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'current_password',
        'cookie',
        'cookies',
        'session',
        'session_id',
        'authorization',
        'two_factor_secret',
        'recovery_codes',
        'mfa_secret',
        'payment',
        'card',
        'cvv',
    ];

    public function execute(AuditEventData $data): AuditLog
    {
        return AuditLog::query()->create([
            'actor_user_id' => $data->actorUserId,
            'event' => $data->event,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'request_id' => $data->requestId,
            'ip_address_hash' => $this->hashIp($data->ipAddress),
            'user_agent' => $this->truncateUserAgent($data->userAgent),
            'old_values' => $this->sanitize($data->oldValues),
            'new_values' => $this->sanitize($data->newValues),
            'metadata' => $this->sanitize($data->metadata),
            'created_at' => now(),
        ]);
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $pepper = (string) config('app.key');

        return hash_hmac('sha256', $ip, $pepper);
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return Str::limit($userAgent, 512, '');
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($normalizedKey, $sensitive)) {
                    continue 2;
                }
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $nested = $this->sanitize($value);
                $clean[$key] = $nested ?? [];

                continue;
            }

            if (is_string($value)) {
                $clean[$key] = Str::limit($value, 500, '');

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
