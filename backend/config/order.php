<?php

declare(strict_types=1);

return [
    'currency' => env('ORDER_CURRENCY', env('CHECKOUT_CURRENCY', 'GEL')),
    'number_prefix' => env('ORDER_NUMBER_PREFIX', 'ORD'),
    'number_suffix_length' => (int) env('ORDER_NUMBER_SUFFIX_LENGTH', 6),
    'number_collision_retries' => (int) env('ORDER_NUMBER_COLLISION_RETRIES', 8),
    'pending_payment_ttl_minutes' => (int) env('ORDER_PENDING_PAYMENT_TTL_MINUTES', 20),
    'idempotency_ttl_hours' => (int) env('ORDER_IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_key_max_length' => (int) env('ORDER_IDEMPOTENCY_KEY_MAX_LENGTH', 128),
    'expire_chunk_size' => (int) env('ORDER_EXPIRE_CHUNK_SIZE', 100),
    'max_deadlock_retries' => (int) env('ORDER_MAX_DEADLOCK_RETRIES', 3),
    'guest_access_ttl_days' => (int) env('ORDER_GUEST_ACCESS_TTL_DAYS', 30),
    'cookie' => [
        'name' => env('ORDER_COOKIE_NAME', 'outdoor_guest_order'),
        'path' => env('ORDER_COOKIE_PATH', '/'),
        'domain' => env('ORDER_COOKIE_DOMAIN'),
        'secure' => env('ORDER_COOKIE_SECURE', env('SESSION_SECURE_COOKIE', false)),
        'same_site' => env('ORDER_COOKIE_SAME_SITE', env('SESSION_SAME_SITE', 'lax')),
        'http_only' => true,
    ],
    'rate_limits' => [
        'read_per_minute' => (int) env('ORDER_READ_PER_MINUTE', 30),
        'create_per_minute' => (int) env('ORDER_CREATE_PER_MINUTE', 8),
        'cancel_per_minute' => (int) env('ORDER_CANCEL_PER_MINUTE', 8),
    ],
];
