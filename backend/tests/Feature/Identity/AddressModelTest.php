<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Address;
use App\Domains\Identity\Models\User;

it('associates addresses with a single owning user', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $address = Address::factory()->for($owner)->create([
        'recipient_name' => 'ლუკა დათუნაშვილი',
        'country_code' => 'GE',
        'is_default' => true,
    ]);

    expect($owner->addresses)->toHaveCount(1);
    expect($address->user->is($owner))->toBeTrue();
    expect($other->addresses)->toHaveCount(0);
    expect($address->recipient_name)->toBe('ლუკა დათუნაშვილი');
});
