<?php

declare(strict_types=1);

/**
 * Day 11 pricing engine configuration.
 *
 * Rounding: half-up to nearest minor unit when applying percentage discounts.
 * Final price: never negative; minimum final amount defaults to 0 (zero allowed).
 */
return [

    'default_price_list_code' => env('PRICING_DEFAULT_LIST_CODE', 'retail_gel'),

    'cache' => [
        'version_key' => 'pricing:cache_version',
        'ttl_seconds' => (int) env('PRICING_CACHE_TTL', 60),
    ],

    'promotions' => [
        'max_combinable' => (int) env('PRICING_MAX_COMBINABLE_PROMOTIONS', 3),
        /** When true, final price may be 0. When false, enforce min_final_amount_minor. */
        'allow_zero_final_price' => (bool) env('PRICING_ALLOW_ZERO_FINAL', true),
        'min_final_amount_minor' => (int) env('PRICING_MIN_FINAL_AMOUNT_MINOR', 0),
    ],

    'bulk' => [
        'max_rows' => (int) env('PRICING_BULK_MAX_ROWS', 100),
    ],

    'note_max_length' => 1000,
];
