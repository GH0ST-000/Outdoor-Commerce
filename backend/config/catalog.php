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
    /**
     * Day 12 public catalog API.
     *
     * Prices and availability in list responses come from rebuildable projections.
     * Product detail re-quotes pricing and availability from the source domains.
     */
    'public' => [
        'currency' => 'GEL',
        'require_ready_media' => (bool) env('CATALOG_PUBLIC_REQUIRE_READY_MEDIA', true),
        'expose_exact_quantity' => false,
        'include_category_descendants' => true,
        'max_category_depth' => 12,
        'pagination' => [
            'default_per_page' => 10,
            'max_per_page' => (int) env('CATALOG_PUBLIC_MAX_PER_PAGE', 48),
        ],
        'filters' => [
            'max_brands' => 20,
            'max_attribute_groups' => 8,
            'max_values_per_attribute' => 20,
            'max_query_length' => 80,
        ],
        'cache' => [
            'ttl_seconds' => (int) env('CATALOG_PUBLIC_CACHE_TTL', 60),
            'stale_while_revalidate_seconds' => (int) env('CATALOG_PUBLIC_SWR', 300),
            'lock_seconds' => 10,
            'prefix' => 'public-catalog',
        ],
        'http' => [
            'max_age_seconds' => 60,
            'stale_while_revalidate_seconds' => 300,
        ],
        'rate_limits' => [
            'browse_per_minute' => 120,
            'list_per_minute' => 60,
            'facets_per_minute' => 30,
            'search_per_minute' => 20,
        ],
        'projections' => [
            'chunk' => 500,
            'queue' => env('CATALOG_PUBLIC_PROJECTION_QUEUE', 'catalog'),
        ],
        'storefront_paths' => [
            'category' => '/catalog/%s',
            'brand' => '/brands/%s',
            'product' => '/products/%s',
        ],
    ],
];
