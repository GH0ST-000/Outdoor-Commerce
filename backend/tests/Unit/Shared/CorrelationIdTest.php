<?php

declare(strict_types=1);

use App\Domains\Shared\Support\CorrelationId;

it('generates and stringifies a correlation id', function (): void {
    $id = CorrelationId::generate();

    expect(CorrelationId::isValidFormat($id->value()))->toBeTrue()
        ->and((string) $id)->toBe($id->value());
});

it('accepts a trusted uuid', function (): void {
    $raw = '550e8400-e29b-41d4-a716-446655440000';

    expect(CorrelationId::fromTrusted($raw)->value())->toBe($raw);
});

it('rejects invalid header candidates', function (): void {
    expect(CorrelationId::tryFromHeader(null))->toBeNull()
        ->and(CorrelationId::tryFromHeader(''))->toBeNull()
        ->and(CorrelationId::tryFromHeader('nope'))->toBeNull()
        ->and(CorrelationId::tryFromHeader(str_repeat('b', 100)))->toBeNull();
});
