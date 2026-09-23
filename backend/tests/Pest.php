<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit', 'Architecture');

uses()->beforeEach(function (): void {
    $this->withHeaders([
        'X-Locale' => 'ka',
        'Accept-Language' => 'ka',
    ]);
})->in('Feature/Catalog/PublicApi', 'Feature/Catalog/Search', 'Feature/Search');

uses()->beforeEach(function (): void {
    $this->withHeaders([
        'X-Locale' => 'ka',
        'Accept-Language' => 'ka',
    ]);
    $this->withCredentials();
})->in('Feature/Cart', 'Feature/Checkout', 'Feature/Orders', 'Feature/Payments');
