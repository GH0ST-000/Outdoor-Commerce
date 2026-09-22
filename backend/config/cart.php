<?php

declare(strict_types=1);

return [
    'currency' => env('CART_CURRENCY', 'GEL'),
    'max_line_quantity' => (int) env('CART_MAX_LINE_QUANTITY', 12),
    'max_unique_lines' => (int) env('CART_MAX_UNIQUE_LINES', 50),
    'guest_ttl_days' => (int) env('CART_GUEST_TTL_DAYS', 30),
    'authenticated_ttl_days' => (int) env('CART_AUTHENTICATED_TTL_DAYS', 90),
    'idempotency_ttl_hours' => (int) env('CART_IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_key_max_length' => (int) env('CART_IDEMPOTENCY_KEY_MAX_LENGTH', 128),
    'expire_chunk_size' => (int) env('CART_EXPIRE_CHUNK_SIZE', 200),
    'max_deadlock_retries' => (int) env('CART_MAX_DEADLOCK_RETRIES', 3),
    'cookie' => [
        'name' => env('CART_COOKIE_NAME', 'outdoor_guest_cart'),
        'path' => env('CART_COOKIE_PATH', '/'),
        'domain' => env('CART_COOKIE_DOMAIN'),
        'secure' => env('CART_COOKIE_SECURE', env('SESSION_SECURE_COOKIE', false)),
        'same_site' => env('CART_COOKIE_SAME_SITE', env('SESSION_SAME_SITE', 'lax')),
        'http_only' => true,
    ],
    'rate_limits' => [
        'read_per_minute' => (int) env('CART_READ_PER_MINUTE', 60),
        'mutate_per_minute' => (int) env('CART_MUTATE_PER_MINUTE', 30),
    ],
];
