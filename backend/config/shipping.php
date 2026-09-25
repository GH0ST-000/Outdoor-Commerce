<?php

declare(strict_types=1);

return [
    'number_prefix' => env('SHIPMENT_NUMBER_PREFIX', 'SHP'),
    'number_suffix_length' => (int) env('SHIPMENT_NUMBER_SUFFIX_LENGTH', 4),
    'number_collision_retries' => (int) env('SHIPMENT_NUMBER_COLLISION_RETRIES', 8),
    'idempotency_ttl_hours' => (int) env('SHIPMENT_IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_key_max_length' => (int) env('SHIPMENT_IDEMPOTENCY_KEY_MAX_LENGTH', 128),
    'max_deadlock_retries' => (int) env('SHIPMENT_MAX_DEADLOCK_RETRIES', 3),
    'tracking_number_max_length' => (int) env('SHIPMENT_TRACKING_NUMBER_MAX_LENGTH', 64),
    'tracking_url_max_length' => (int) env('SHIPMENT_TRACKING_URL_MAX_LENGTH', 2048),
    'note_max_length' => (int) env('SHIPMENT_NOTE_MAX_LENGTH', 1000),
    'webhook_max_bytes' => (int) env('SHIPMENT_WEBHOOK_MAX_BYTES', 65536),
    'https_required' => env('SHIPMENT_HTTPS_REQUIRED', env('APP_ENV') === 'production'),
    'allowed_tracking_schemes' => ['https', 'http'],
    'allowed_tracking_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SHIPMENT_ALLOWED_TRACKING_HOSTS',
        'tracking.example.com',
    ))))),
    'reconcile_chunk_size' => (int) env('SHIPMENT_RECONCILE_CHUNK_SIZE', 50),
    'reconcile_min_age_seconds' => (int) env('SHIPMENT_RECONCILE_MIN_AGE_SECONDS', 60),
    'stale' => [
        'preparing_minutes' => (int) env('SHIPMENT_STALE_PREPARING_MINUTES', 24 * 60),
        'ready_for_dispatch_minutes' => (int) env('SHIPMENT_STALE_READY_FOR_DISPATCH_MINUTES', 12 * 60),
        'shipped_minutes' => (int) env('SHIPMENT_STALE_SHIPPED_MINUTES', 7 * 24 * 60),
        'exception_minutes' => (int) env('SHIPMENT_STALE_EXCEPTION_MINUTES', 48 * 60),
        'ready_for_pickup_minutes' => (int) env('SHIPMENT_STALE_READY_FOR_PICKUP_MINUTES', 7 * 24 * 60),
    ],
    'rate_limits' => [
        'admin_read_per_minute' => (int) env('SHIPMENT_ADMIN_READ_PER_MINUTE', 60),
        'admin_mutate_per_minute' => (int) env('SHIPMENT_ADMIN_MUTATE_PER_MINUTE', 30),
        'customer_read_per_minute' => (int) env('SHIPMENT_CUSTOMER_READ_PER_MINUTE', 30),
        'webhook_per_minute' => (int) env('SHIPMENT_WEBHOOK_PER_MINUTE', 120),
    ],
    'providers' => [
        'manual' => [
            'driver' => 'manual',
            'enabled' => (bool) env('SHIPMENT_MANUAL_PROVIDER_ENABLED', true),
            'claims_synchronization' => false,
        ],
    ],
];
