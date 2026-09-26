<?php

declare(strict_types=1);

use App\Domains\Legal\Support\HuntingInteriorProhibition;

it('prohibits hunting only inside national parks and strict nature reserves', function () {
    expect(HuntingInteriorProhibition::applies('hunting', 'national_park'))->toBeTrue()
        ->and(HuntingInteriorProhibition::applies('hunting', 'strict_nature_reserve'))->toBeTrue()
        ->and(HuntingInteriorProhibition::applies('hunting', 'managed_reserve'))->toBeFalse()
        ->and(HuntingInteriorProhibition::applies('hunting', 'natural_monument'))->toBeFalse()
        ->and(HuntingInteriorProhibition::applies('hunting', 'protected_area'))->toBeFalse()
        ->and(HuntingInteriorProhibition::applies('fishing', 'national_park'))->toBeFalse();
});
