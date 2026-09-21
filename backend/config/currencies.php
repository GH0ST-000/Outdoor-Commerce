<?php

declare(strict_types=1);

/**
 * Enabled storefront currencies. Day 11 does not convert between currencies.
 */
return [
    'default' => 'GEL',
    'admin_timezone' => env('PRICING_ADMIN_TIMEZONE', 'Asia/Tbilisi'),

    'currencies' => [
        'GEL' => [
            'code' => 'GEL',
            'minor_units' => 2,
            'symbol' => '₾',
            'enabled' => true,
        ],
        'USD' => [
            'code' => 'USD',
            'minor_units' => 2,
            'symbol' => '$',
            'enabled' => true,
        ],
        'EUR' => [
            'code' => 'EUR',
            'minor_units' => 2,
            'symbol' => '€',
            'enabled' => true,
        ],
    ],
];
