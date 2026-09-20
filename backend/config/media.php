<?php

declare(strict_types=1);

/**
 * Day 9 local media pipeline configuration.
 *
 * Originals stay on media_private. Only optimized derivatives are published on
 * media_public (via `php artisan storage:link`). Domain code must use these
 * logical disk names through Laravel Storage — never absolute OS paths.
 */
return [

    'queue' => env('MEDIA_QUEUE', 'media'),

    'disks' => [
        'originals' => 'media_private',
        'derivatives' => 'media_public',
    ],

    /*
    | Relative URL prefix used when a filesystem disk has no configured URL
    | (for example Storage::fake in tests without an explicit url option).
    */
    'public_url_prefix' => env('MEDIA_PUBLIC_URL_PREFIX', '/storage/media'),

    'uploads' => [
        'max_files_per_request' => (int) env('MEDIA_MAX_FILES_PER_REQUEST', 10),
        /** Soft ceiling shared by product and variant galleries unless overridden below. */
        'max_per_owner' => (int) env('MEDIA_MAX_PER_OWNER', 24),
        'max_product_gallery' => (int) env('MEDIA_MAX_PRODUCT_GALLERY', 20),
        'max_variant_gallery' => (int) env('MEDIA_MAX_VARIANT_GALLERY', 10),
        'max_file_size_kilobytes' => (int) env('MEDIA_MAX_FILE_SIZE_KB', 15 * 1024),
        'min_file_size_bytes' => (int) env('MEDIA_MIN_FILE_SIZE_BYTES', 64),
        'accepted_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'blocked_extensions' => [
            'svg', 'svgz', 'php', 'phtml', 'phar', 'exe', 'dll', 'so', 'dylib',
            'sh', 'bash', 'bat', 'cmd', 'ps1', 'js', 'mjs', 'html', 'htm', 'xhtml',
            'xml', 'pdf', 'zip', 'gz', 'tgz', 'rar', '7z', 'iso', 'dmg',
        ],
    ],

    'dimensions' => [
        'min_width' => (int) env('MEDIA_MIN_WIDTH', 200),
        'min_height' => (int) env('MEDIA_MIN_HEIGHT', 200),
        'max_width' => (int) env('MEDIA_MAX_WIDTH', 12000),
        'max_height' => (int) env('MEDIA_MAX_HEIGHT', 12000),
        'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 50_000_000),
    ],

    'presets' => [
        'thumbnail' => ['max_width' => 240, 'max_height' => 240],
        'card' => ['max_width' => 640, 'max_height' => 800],
        'card_large' => ['max_width' => 960, 'max_height' => 1200],
        'detail' => ['max_width' => 1280, 'max_height' => 1600],
        'zoom' => ['max_width' => 2200, 'max_height' => 2600],
    ],

    'quality' => [
        'webp' => (int) env('MEDIA_WEBP_QUALITY', 82),
        'jpeg' => (int) env('MEDIA_JPEG_QUALITY', 85),
        'avif' => (int) env('MEDIA_AVIF_QUALITY', 50),
        'png_compression' => (int) env('MEDIA_PNG_COMPRESSION', 6),
    ],

    'formats' => [
        /** Optional; encoding failures never fail required WebP/fallback processing. */
        'avif_enabled' => (bool) env('MEDIA_AVIF_ENABLED', false),
    ],

    'processing' => [
        'tries' => (int) env('MEDIA_JOB_TRIES', 3),
        'timeout' => (int) env('MEDIA_JOB_TIMEOUT', 180),
        'unique_for' => (int) env('MEDIA_JOB_UNIQUE_FOR', 900),
        'backoff' => [10, 60, 300],
    ],

    'cleanup' => [
        'grace_period_hours' => (int) env('MEDIA_ORPHAN_GRACE_HOURS', 24),
        'batch_size' => (int) env('MEDIA_CLEANUP_BATCH_SIZE', 200),
        'quarantine_retention_days' => (int) env('MEDIA_QUARANTINE_RETENTION_DAYS', 30),
    ],

    'recovery' => [
        'processing_stuck_minutes' => (int) env('MEDIA_PROCESSING_STUCK_MINUTES', 15),
        'pending_stuck_minutes' => (int) env('MEDIA_PENDING_STUCK_MINUTES', 30),
        'batch_size' => (int) env('MEDIA_RECOVERY_BATCH_SIZE', 100),
    ],

    'metadata' => [
        'alt_text_max_length' => (int) env('MEDIA_ALT_TEXT_MAX_LENGTH', 300),
        'caption_max_length' => (int) env('MEDIA_CAPTION_MAX_LENGTH', 600),
        'original_filename_max_length' => 180,
    ],
];
