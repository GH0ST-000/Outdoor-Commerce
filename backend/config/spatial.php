<?php

declare(strict_types=1);

return [
    'canonical_srid' => 4326,
    'coordinate_order' => 'longitude,latitude',
    'default_jurisdiction' => env('SPATIAL_DEFAULT_JURISDICTION', env('LEGAL_DEFAULT_JURISDICTION', 'GE')),
    'test_jurisdiction' => 'XX',

    'pagination' => [
        'default' => 10,
        'max' => 50,
    ],

    'publishing' => [
        'require_distinct_publisher' => (bool) env('SPATIAL_REQUIRE_DISTINCT_PUBLISHER', false),
    ],

    'storage' => [
        'disk' => env('SPATIAL_STORAGE_DISK', 'spatial_private'),
        'max_file_kilobytes' => (int) env('SPATIAL_MAX_FILE_KB', 20480),
        'allowed_mime_types' => [
            'application/geo+json',
            'application/json',
            'text/plain',
            'text/json',
            'application/octet-stream',
        ],
        'allowed_extensions' => ['geojson', 'json'],
        'checksum_algorithm' => 'sha256',
    ],

    'import' => [
        'max_features' => (int) env('SPATIAL_MAX_FEATURES', 2000),
        'max_vertices' => (int) env('SPATIAL_MAX_VERTICES', 500000),
        'max_vertices_per_feature' => (int) env('SPATIAL_MAX_VERTICES_PER_FEATURE', 80000),
        'max_json_bytes' => (int) env('SPATIAL_MAX_JSON_BYTES', 20 * 1024 * 1024),
        'max_nesting_depth' => 32,
        'preview_features' => 8,
        'queue' => env('SPATIAL_IMPORT_QUEUE', 'default'),
        'lock_seconds' => 120,
        'accepted_crs' => ['4326', 'EPSG:4326', 'CRS84', 'OGC:CRS84', 'urn:ogc:def:crs:OGC:1.3:CRS84'],
    ],

    'query' => [
        'boundary_warning_degrees' => (float) env('SPATIAL_BOUNDARY_WARNING_DEGREES', 0.001),
        'max_viewport_span_degrees' => (float) env('SPATIAL_MAX_VIEWPORT_SPAN', 60.0),
        'max_viewport_full_span_degrees' => (float) env('SPATIAL_MAX_VIEWPORT_FULL_SPAN', 2.0),
        'max_viewport_features' => (int) env('SPATIAL_MAX_VIEWPORT_FEATURES', 100),
        'max_period_days' => (int) env('SPATIAL_MAX_PERIOD_DAYS', 366),
        'mysql_mbr_candidate_filter' => true,
    ],

    'cache' => [
        'ttl_seconds' => (int) env('SPATIAL_CACHE_TTL', 120),
        'version_key' => 'spatial:public:version',
    ],

    'rate_limits' => [
        'public_per_minute' => (int) env('SPATIAL_PUBLIC_PER_MINUTE', 30),
        'evaluate_per_minute' => (int) env('SPATIAL_EVALUATE_PER_MINUTE', 12),
        'lookup_per_minute' => (int) env('SPATIAL_LOOKUP_PER_MINUTE', 20),
        'admin_download_per_minute' => (int) env('SPATIAL_ADMIN_DOWNLOAD_PER_MINUTE', 20),
    ],

    'disclaimer_key' => 'legal.informational_not_advice',
    'unknown_by_default' => true,
];
