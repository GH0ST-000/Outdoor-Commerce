<?php

declare(strict_types=1);

/**
 * Day 10 inventory ledger configuration.
 *
 * MySQL is the stock authority. Redis must never be used to authorize reservations
 * or mutate balances — only optional short-lived availability cache.
 */
return [

    'reservation_ttl_minutes' => (int) env('INVENTORY_RESERVATION_TTL_MINUTES', 15),

    'allow_negative_stock' => (bool) env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),

    'max_deadlock_retries' => (int) env('INVENTORY_MAX_DEADLOCK_RETRIES', 3),

    'expire_chunk_size' => (int) env('INVENTORY_EXPIRE_CHUNK_SIZE', 100),

    'note_max_length' => (int) env('INVENTORY_NOTE_MAX_LENGTH', 1000),

    'idempotency_key_max_length' => 128,

    'cache' => [
        'version_key' => 'inventory:cache_version',
        'availability_ttl_seconds' => (int) env('INVENTORY_AVAILABILITY_CACHE_TTL', 30),
    ],

];
