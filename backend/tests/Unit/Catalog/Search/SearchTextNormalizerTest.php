<?php

declare(strict_types=1);

use App\Domains\Catalog\Search\Support\SearchTextNormalizer;

it('trims, collapses whitespace, and strips control characters', function (): void {
    $normalizer = new SearchTextNormalizer;

    expect($normalizer->normalize("  Alpine\t\nJacket  "))->toBe('Alpine Jacket');
    expect($normalizer->normalize("scope\x00name"))->toBe('scope name');
    expect($normalizer->searchable('Alpine Jacket'))->toBe('alpine jacket');
});

it('preserves Georgian letters and enforces length limits', function (): void {
    $normalizer = new SearchTextNormalizer;

    expect($normalizer->normalize('  სამიზნე  '))->toBe('სამიზნე');
    expect(mb_strlen($normalizer->normalize(str_repeat('ა', 120), 100), 'UTF-8'))->toBe(100);
    expect($normalizer->normalize(null))->toBe('');
});

it('does not treat HTML payloads as markup', function (): void {
    $normalizer = new SearchTextNormalizer;
    $value = $normalizer->normalize('<script>alert(1)</script>');

    expect($value)->toBe('<script>alert(1)</script>');
});
