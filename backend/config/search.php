<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('SEARCH_ENABLED', true),
    'fallback_enabled' => (bool) env('SEARCH_FALLBACK_ENABLED', true),
    'default_locale' => env('SEARCH_DEFAULT_LOCALE', 'ka'),
    'host' => env('MEILISEARCH_HOST', env('MEILISEARCH_URL', 'http://127.0.0.1:7700')),
    'key' => env('MEILISEARCH_MASTER_KEY', env('MEILISEARCH_KEY')),
    'index_prefix' => env('MEILISEARCH_INDEX_PREFIX', 'outdoor_local'),
    'schema_version' => 'v1',
    'connect_timeout' => (float) env('MEILISEARCH_CONNECT_TIMEOUT', 0.4),
    'request_timeout' => (float) env('MEILISEARCH_REQUEST_TIMEOUT', 1.5),
    'task_wait_ms' => (int) env('MEILISEARCH_TASK_WAIT_MS', 60000),
    'queue' => env('SEARCH_QUEUE', 'catalog'),
    'rebuild_chunk' => (int) env('SEARCH_REBUILD_CHUNK', 200),
    'max_query_length' => (int) env('SEARCH_MAX_QUERY_LENGTH', 100),
    'min_suggest_length' => 2,
    'suggest_limit' => 8,
    'grouped_limit' => 10,
    'max_page' => 100,
    'autocomplete_ttl_seconds' => 20,
    'health_ttl_seconds' => 10,
    'failure_log_seconds' => 30,
    'inventory_debounce_seconds' => 15,
    'rate_limits' => [
        'grouped_per_minute' => (int) env('SEARCH_GROUPED_PER_MINUTE', 30),
        'suggest_per_minute' => (int) env('SEARCH_SUGGEST_PER_MINUTE', 60),
    ],
    'synonyms' => [
        'ka' => [
            ['scope', 'სკოპი', 'ოპტიკა'],
            ['jacket', 'ქურთუკი'],
        ],
        'en' => [
            ['scope', 'optic', 'optics'],
            ['jacket', 'coat'],
        ],
    ],
    'stop_words' => [
        'ka' => [],
        'en' => ['the', 'a', 'an', 'and', 'or', 'for', 'of', 'to'],
    ],
];
