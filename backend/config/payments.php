<?php

declare(strict_types=1);

$frontend = (string) env('FRONTEND_URL', 'http://localhost:3000');
$frontendHost = parse_url($frontend, PHP_URL_HOST);
$appUrl = (string) env('APP_URL', 'http://localhost:8000');

return [
    'currency' => env('PAYMENT_CURRENCY', env('ORDER_CURRENCY', 'GEL')),
    'idempotency_ttl_hours' => (int) env('PAYMENT_IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_key_max_length' => (int) env('PAYMENT_IDEMPOTENCY_KEY_MAX_LENGTH', 128),
    'max_deadlock_retries' => (int) env('PAYMENT_MAX_DEADLOCK_RETRIES', 3),
    'max_redirect_url_length' => (int) env('PAYMENT_MAX_REDIRECT_URL_LENGTH', 2048),
    'webhook_max_bytes' => (int) env('PAYMENT_WEBHOOK_MAX_BYTES', 65536),
    'webhook_timestamp_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
    'reconcile_chunk_size' => (int) env('PAYMENT_RECONCILE_CHUNK_SIZE', 50),
    'reconcile_min_age_seconds' => (int) env('PAYMENT_RECONCILE_MIN_AGE_SECONDS', 30),
    'provider_connect_timeout_seconds' => (float) env('PAYMENT_PROVIDER_CONNECT_TIMEOUT', 2.0),
    'provider_request_timeout_seconds' => (float) env('PAYMENT_PROVIDER_REQUEST_TIMEOUT', 8.0),
    'return_url' => env('PAYMENT_RETURN_URL', rtrim($frontend, '/').'/payment/return'),
    'app_url' => $appUrl,
    'frontend_url' => $frontend,
    'https_required' => env('PAYMENT_HTTPS_REQUIRED', env('APP_ENV') === 'production'),
    'allowed_redirect_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'PAYMENT_ALLOWED_REDIRECT_HOSTS',
        implode(',', array_filter([$frontendHost, 'localhost', '127.0.0.1', 'payments.test'])),
    ))))),
    'allowed_redirect_schemes' => ['https', 'http'],
    'rate_limits' => [
        'methods_per_minute' => (int) env('PAYMENT_METHODS_PER_MINUTE', 30),
        'mutate_per_minute' => (int) env('PAYMENT_MUTATE_PER_MINUTE', 10),
        'read_per_minute' => (int) env('PAYMENT_READ_PER_MINUTE', 30),
        'webhook_per_minute' => (int) env('PAYMENT_WEBHOOK_PER_MINUTE', 120),
        'simulate_per_minute' => (int) env('PAYMENT_SIMULATE_PER_MINUTE', 20),
    ],
    'test' => [
        'enabled' => (bool) env('PAYMENT_TEST_PROVIDER_ENABLED', false),
        'secret' => env('PAYMENT_TEST_WEBHOOK_SECRET', 'test-payment-webhook-secret-change-me'),
        'scenario' => env('PAYMENT_TEST_SCENARIO', 'success'),
        'hosted_path' => '/payment/test',
    ],
    'providers' => [
        'test' => [
            'driver' => 'test',
            'enabled' => (bool) env('PAYMENT_TEST_PROVIDER_ENABLED', false),
            'production_allowed' => false,
        ],
    ],
    'methods' => [
        'test_hosted_redirect' => [
            'code' => 'test_hosted_redirect',
            'provider' => 'test',
            'type' => 'hosted_redirect',
            'icon' => 'test',
            'is_enabled' => (bool) env('PAYMENT_TEST_PROVIDER_ENABLED', false),
            'development_only' => true,
            'supported_currencies' => ['GEL'],
            'minimum_amount_minor' => 1,
            'maximum_amount_minor' => 10_000_000,
            'sort_order' => 100,
            'names' => [
                'en' => 'Test hosted payment',
                'ka' => 'ტესტური გადახდა',
            ],
            'descriptions' => [
                'en' => 'Development-only test provider. Not a real bank. No card details are collected.',
                'ka' => 'მხოლოდ განვითარების ტესტური პროვაიდერი. ეს ბანკი არ არის. ბარათის მონაცემები არ გროვდება.',
            ],
        ],
    ],
];
