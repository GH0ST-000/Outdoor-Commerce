<?php

declare(strict_types=1);

return [
    'currency' => env('CHECKOUT_CURRENCY', env('CART_CURRENCY', 'GEL')),
    'default_country' => env('CHECKOUT_DEFAULT_COUNTRY', 'GE'),
    'price_includes_tax' => (bool) env('CHECKOUT_PRICE_INCLUDES_TAX', true),
    'quote_ttl_minutes' => (int) env('CHECKOUT_QUOTE_TTL_MINUTES', 15),
    'session_ttl_hours' => (int) env('CHECKOUT_SESSION_TTL_HOURS', 24),
    'max_active_sessions_per_owner' => (int) env('CHECKOUT_MAX_ACTIVE_SESSIONS', 3),
    'max_quote_refreshes_per_session' => (int) env('CHECKOUT_MAX_QUOTE_REFRESHES', 8),
    'max_line_quantity' => (int) env('CHECKOUT_MAX_LINE_QUANTITY', env('CART_MAX_LINE_QUANTITY', 12)),
    'idempotency_ttl_hours' => (int) env('CHECKOUT_IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_key_max_length' => (int) env('CHECKOUT_IDEMPOTENCY_KEY_MAX_LENGTH', 128),
    'expire_chunk_size' => (int) env('CHECKOUT_EXPIRE_CHUNK_SIZE', 100),
    'max_deadlock_retries' => (int) env('CHECKOUT_MAX_DEADLOCK_RETRIES', 3),
    'fingerprint_secret' => env('CHECKOUT_FINGERPRINT_SECRET', env('APP_KEY')),
    'rate_limits' => [
        'read_per_minute' => (int) env('CHECKOUT_READ_PER_MINUTE', 30),
        'mutate_per_minute' => (int) env('CHECKOUT_MUTATE_PER_MINUTE', 20),
        'quote_per_minute' => (int) env('CHECKOUT_QUOTE_PER_MINUTE', 8),
    ],
];
