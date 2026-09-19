<?php

declare(strict_types=1);

return [
    'locales' => ['ka', 'en'],
    'default_locale' => 'ka',
    'fallback_locale' => 'ka',
    /**
     * Description format: sanitized HTML allowlist (Day 7).
     * Active product means content-ready, not purchasable.
     */
    'description_max_length' => 50000,
    'short_description_max_length' => 500,
    'cache' => [
        'version_key' => 'catalog:cache_version',
    ],
    'variants' => [
        /** Max Cartesian combinations created in one generate call. */
        'max_combinations_per_generation' => 100,
        /** Max non-deleted variants allowed on a single product. */
        'max_variants_per_product' => 500,
        'sku' => [
            'max_length' => 64,
            'prefix' => 'PRD',
            'pattern' => '/^[A-Z0-9_-]+$/',
        ],
        'barcode' => [
            'allowed_lengths' => [8, 12, 13, 14],
        ],
    ],
];
