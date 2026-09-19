<?php

declare(strict_types=1);

use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Identity\Support\NameNormalizer;

it('normalizes emails consistently', function (): void {
    expect(EmailNormalizer::normalize('  Luka@Example.TEST '))->toBe('luka@example.test');
});

it('normalizes names without destroying Georgian characters', function (): void {
    expect(NameNormalizer::normalize('  ლუკა   დათუ  '))->toBe('ლუკა დათუ');
});
