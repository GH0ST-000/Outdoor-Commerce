<?php

declare(strict_types=1);

use App\Domains\Hunting\Support\ScientificNameNormalizer;
use App\Domains\Hunting\Support\SpeciesHtmlSanitizer;
use App\Domains\Hunting\Support\SpeciesSlug;

it('normalizes scientific names to unique lowercase form and display binomial', function (): void {
    expect(ScientificNameNormalizer::normalize('  Cervus   Elaphus '))
        ->toBe('cervus elaphus')
        ->and(ScientificNameNormalizer::display('cervus elaphus'))
        ->toBe('Cervus elaphus')
        ->and(ScientificNameNormalizer::isValidBinomial('Cervus elaphus'))
        ->toBeTrue()
        ->and(ScientificNameNormalizer::isValidBinomial('wolf'))
        ->toBeFalse();
});

it('keeps Georgian letters in stable slugs', function (): void {
    expect(SpeciesSlug::normalize('  ირემი  მთის '))->toBe('ირემი-მთის')
        ->and(SpeciesSlug::isValid('cervus-elaphus'))->toBeTrue();
});

it('strips scripts and event handlers from species HTML', function (): void {
    $sanitizer = new SpeciesHtmlSanitizer;
    $clean = $sanitizer->sanitize('<p onclick="alert(1)">ok</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>');

    expect($clean)->toContain('<p>ok</p>')
        ->and($clean)->not->toContain('script')
        ->and($clean)->not->toContain('onclick')
        ->and($sanitizer->containsUnsafe('<iframe src="x"></iframe>'))->toBeTrue();

    $georgian = $sanitizer->sanitize('<p onclick="alert(1)">შეჯამება</p><script>x</script>');
    expect($georgian)->toContain('შეჯამება')
        ->and($georgian)->not->toContain('script')
        ->and($georgian)->not->toContain('onclick');
});
