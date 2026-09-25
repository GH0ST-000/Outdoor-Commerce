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
        'bog' => [
            'driver' => 'bog',
            'enabled' => (bool) env('BOG_PAYMENT_ENABLED', false),
            'production_allowed' => true,
            'environment' => env('BOG_PAYMENT_ENVIRONMENT', 'test'),
            'client_id' => env('BOG_PAYMENT_CLIENT_ID'),
            'client_secret' => env('BOG_PAYMENT_CLIENT_SECRET'),
            'oauth_url' => env('BOG_PAYMENT_OAUTH_URL', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'),
            'api_base_url' => env('BOG_PAYMENT_API_BASE_URL', 'https://api.bog.ge/payments/v1'),
            'callback_public_key' => env('BOG_PAYMENT_CALLBACK_PUBLIC_KEY'),
            'callback_public_key_path' => env('BOG_PAYMENT_CALLBACK_PUBLIC_KEY_PATH'),
            'callback_public_key_previous' => env('BOG_PAYMENT_CALLBACK_PUBLIC_KEY_PREVIOUS'),
            // Official public key from https://api.bog.ge/docs/en/payments/standard-process/callback reviewed 2026-09-23.
            'documented_callback_public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu4RUyAw3+CdkS3ZNILQhzHI9Hemo+vKB9U2BSabppkKjzjjkf+0Sm76hSMiu/HFtYhqWOESryoCDJoqffY0Q1VNt25aTxbj068QNUtnxQ7KQVLA+pG0smf+EBWlS1vBEAFbIas9d8c9b9sSEkTrrTYQ90WIM8bGB6S/KLVoT1a7SnzabjoLc5Qf/SLDG5fu8dH8zckyeYKdRKSBJKvhxtcBuHV4f7qsynQT+f2UYbESX/TLHwT5qFWZDHZ0YUOUIvb8n7JujVSGZO9/+ll/g4ZIWhC1MlJgPObDwRkRd8NFOopgxMcMsDIZIoLbWKhHVq67hdbwpAq9K9WMmEhPnPwIDAQAB
-----END PUBLIC KEY-----
PEM,
            'callback_url' => env('BOG_PAYMENT_CALLBACK_URL', rtrim($appUrl, '/').'/api/v1/payments/webhooks/bog'),
            'success_url' => env('BOG_PAYMENT_SUCCESS_URL', env('PAYMENT_RETURN_URL', rtrim($frontend, '/').'/payment/return')),
            'fail_url' => env('BOG_PAYMENT_FAILURE_URL', env('PAYMENT_RETURN_URL', rtrim($frontend, '/').'/payment/return')),
            'connect_timeout_seconds' => (float) env('BOG_PAYMENT_CONNECT_TIMEOUT_SECONDS', 5),
            'request_timeout_seconds' => (float) env('BOG_PAYMENT_REQUEST_TIMEOUT_SECONDS', 15),
            'token_refresh_skew_seconds' => (int) env('BOG_PAYMENT_TOKEN_REFRESH_SKEW_SECONDS', 60),
            'default_ttl_minutes' => (int) env('BOG_PAYMENT_DEFAULT_TTL_MINUTES', 15),
            'ttl_safety_margin_minutes' => (int) env('BOG_PAYMENT_TTL_SAFETY_MARGIN_MINUTES', 1),
            'allowed_methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('BOG_PAYMENT_ALLOWED_METHODS', 'card'))))),
            'theme' => env('BOG_PAYMENT_THEME', 'dark'),
            'account_tag' => env('BOG_PAYMENT_ACCOUNT_TAG'),
            'allowed_redirect_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
                'BOG_PAYMENT_ALLOWED_REDIRECT_HOSTS',
                'payment.bog.ge',
            ))))),
            'documentation_reviewed_at' => '2026-09-23',
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
        'bog_hosted_card' => [
            'code' => 'bog_hosted_card',
            'provider' => 'bog',
            'type' => 'hosted_redirect',
            'icon' => 'bog',
            'is_enabled' => (bool) env('BOG_PAYMENT_ENABLED', false),
            'development_only' => false,
            'supported_currencies' => ['GEL', 'USD', 'EUR', 'GBP'],
            'minimum_amount_minor' => 1,
            'maximum_amount_minor' => 10_000_000,
            'sort_order' => 10,
            'names' => [
                'en' => 'Bank of Georgia',
                'ka' => 'საქართველოს ბანკი',
            ],
            'descriptions' => [
                'en' => 'You will be redirected to Bank of Georgia’s secure payment page. Card details stay with the bank.',
                'ka' => 'გადამისამართდები საქართველოს ბანკის უსაფრთხო გადახდის გვერდზე. ბარათის მონაცემები რჩება ბანკთან.',
            ],
        ],
    ],
];
