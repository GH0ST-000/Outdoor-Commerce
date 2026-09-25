<?php

declare(strict_types=1);

return [
    'default_jurisdiction' => env('LEGAL_DEFAULT_JURISDICTION', 'GE'),
    'jurisdictions' => ['GE'],
    'timezone' => env('LEGAL_TIMEZONE', 'Asia/Tbilisi'),

    'pagination' => [
        'default' => 10,
        'max' => 50,
    ],

    'publishing' => [
        'require_distinct_publisher' => (bool) env('LEGAL_REQUIRE_DISTINCT_PUBLISHER', false),
        'minimum_verification_level' => env('LEGAL_MINIMUM_VERIFICATION_LEVEL', 'provision_verified'),
        'block_on_high_severity_conflicts' => true,
    ],

    'storage' => [
        'disk' => env('LEGAL_STORAGE_DISK', 'legal_private'),
        'max_file_kilobytes' => (int) env('LEGAL_MAX_FILE_KB', 20480),
        'allowed_mime_types' => [
            'application/pdf',
            'text/plain',
            'text/html',
        ],
        'allowed_extensions' => ['pdf', 'txt', 'html'],
        'checksum_algorithm' => 'sha256',
        'malware_scan' => false,
    ],

    'retrieval' => [
        'enabled' => (bool) env('LEGAL_URL_RETRIEVAL_ENABLED', true),
        'timeout_seconds' => 8,
        'max_bytes' => 20 * 1024 * 1024,
        'max_redirects' => 3,
        'allowed_schemes' => ['https'],
        'allow_custom_ports' => false,
    ],

    'monitoring' => [
        'queue' => env('LEGAL_MONITOR_QUEUE', 'default'),
        'min_interval_minutes' => 360,
        'rate_limit_per_source_per_day' => 8,
    ],

    'cache' => [
        'ttl_seconds' => (int) env('LEGAL_CACHE_TTL', 120),
        'version_key' => 'legal:public:version',
        'lock_seconds' => 10,
    ],

    'search' => [
        'schema_version' => 'v1',
        'queue' => env('LEGAL_SEARCH_QUEUE', env('SEARCH_QUEUE', 'catalog')),
        'enabled' => (bool) env('LEGAL_SEARCH_ENABLED', true),
    ],

    'evaluation' => [
        'disclaimer_key' => 'legal.informational_not_advice',
        'unknown_by_default' => true,
    ],

    'excerpt_max' => 400,

    'rate_limits' => [
        'public_per_minute' => (int) env('LEGAL_PUBLIC_PER_MINUTE', 60),
        'evaluate_per_minute' => (int) env('LEGAL_EVALUATE_PER_MINUTE', 20),
        'admin_download_per_minute' => (int) env('LEGAL_ADMIN_DOWNLOAD_PER_MINUTE', 30),
        'calendar_per_minute' => (int) env('LEGAL_CALENDAR_PER_MINUTE', 30),
    ],

    /*
    | Date-only seasons open at local 00:00 and close inclusively through the
    | end of the stated local day, stored internally as half-open [start, endExclusive).
    | Cross-year occurrences belong to the opening year. Recurring 29 February
    | generates only on leap years with no invented fallback date.
    */
    'calendar' => [
        'max_public_days' => (int) env('LEGAL_CALENDAR_MAX_PUBLIC_DAYS', 366),
        'max_admin_days' => (int) env('LEGAL_CALENDAR_MAX_ADMIN_DAYS', 1096),
        'horizon_past_years' => (int) env('LEGAL_CALENDAR_HORIZON_PAST', 1),
        'horizon_future_years' => (int) env('LEGAL_CALENDAR_HORIZON_FUTURE', 3),
        'queue' => env('LEGAL_CALENDAR_QUEUE', 'default'),
        'lock_seconds' => 120,
        'regions' => [
            'GE-AB',
            'GE-AJ',
            'GE-GU',
            'GE-IM',
            'GE-KA',
            'GE-KK',
            'GE-MM',
            'GE-RL',
            'GE-SZ',
            'GE-SJ',
            'GE-SK',
            'GE-TB',
        ],
    ],
];
