<?php

declare(strict_types=1);

use App\Domains\Cart\Support\CartTokenHasher;

it('hashes guest tokens without storing the raw credential', function (): void {
    $hasher = new CartTokenHasher;
    $raw = $hasher->generate();

    expect($raw)->not->toBeEmpty()
        ->and($hasher->hash($raw))->toHaveLength(64)
        ->and($hasher->hash($raw))->not->toBe($raw)
        ->and($hasher->hash($raw))->toBe($hasher->hash($raw))
        ->and($hasher->hash($raw))->not->toBe($hasher->hash($raw.'x'));
});
