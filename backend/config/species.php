<?php

declare(strict_types=1);

return [
    'default_locale' => env('SPECIES_DEFAULT_LOCALE', 'ka'),
    'fallback_locale' => env('SPECIES_FALLBACK_LOCALE', 'ka'),
    'locales' => ['ka', 'en'],

    'pagination' => [
        'default' => 10,
        'max' => 50,
    ],

    'publishing' => [
        // When true, the user who submitted review cannot also publish.
        'require_distinct_reviewer' => (bool) env('SPECIES_REQUIRE_DISTINCT_REVIEWER', false),
        'minimum_verification' => env('SPECIES_MINIMUM_VERIFICATION', 'partially_verified'),
        'require_georgian_translation' => true,
        'require_source' => true,
        'require_media_or_opt_out' => true,
    ],

    'english_fallback' => [
        // Public English pages may fall back to published Georgian copy.
        // Unpublished translations are never used.
        'enabled' => (bool) env('SPECIES_ENGLISH_FALLBACK', true),
    ],

    'cache' => [
        'ttl_seconds' => (int) env('SPECIES_CACHE_TTL', 300),
        'lock_seconds' => 10,
        'stale_while_revalidate_seconds' => 300,
        'version_key' => 'species:public:version',
    ],

    'search' => [
        'schema_version' => 'v1',
        'queue' => env('SPECIES_SEARCH_QUEUE', env('SEARCH_QUEUE', 'catalog')),
        'rebuild_chunk' => (int) env('SPECIES_SEARCH_REBUILD_CHUNK', 100),
    ],

    'limits' => [
        'common_name' => 120,
        'short_name' => 80,
        'summary' => 2000,
        'identification' => 8000,
        'appearance' => 8000,
        'behavior' => 8000,
        'diet' => 4000,
        'habitat_description' => 4000,
        'breeding_notes' => 4000,
        'seasonal_behavior' => 4000,
        'field_notes' => 4000,
        'safety_notes' => 4000,
        'seo_title' => 70,
        'seo_description' => 170,
        'alias_name' => 160,
        'trait_label' => 120,
        'trait_description' => 2000,
        'similar_notes' => 2000,
        'conservation_notes' => 2000,
        'quotation_excerpt' => 280,
        'change_summary' => 240,
        'source_title' => 255,
        'source_publisher' => 255,
        'source_url' => 2048,
        'scientific_name' => 191,
        'canonical_slug' => 191,
    ],

    'scientific_name' => [
        // Uniqueness is enforced on the normalized binomial among all rows,
        // including archived and soft-deleted records, so history cannot recycle names.
        'unique_including_deleted' => true,
    ],

    'legal_information' => [
        'available' => false,
        'message_key' => 'species.legal_information_not_yet_available',
    ],

    'rate_limits' => [
        'public_per_minute' => (int) env('SPECIES_PUBLIC_PER_MINUTE', 120),
        'list_per_minute' => (int) env('SPECIES_LIST_PER_MINUTE', 60),
        'search_per_minute' => (int) env('SPECIES_SEARCH_PER_MINUTE', 20),
    ],
];
