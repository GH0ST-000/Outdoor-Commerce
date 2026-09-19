<?php

declare(strict_types=1);

use App\Domains\Catalog\Support\ColorHex;
use App\Domains\Catalog\Support\Variants\Barcode;
use App\Domains\Catalog\Support\Variants\Sku;
use App\Domains\Catalog\Support\Variants\VariantCombination;

it('normalizes and validates SKUs', function (): void {
    expect(Sku::normalize('  prd-1-001 '))->toBe('PRD-1-001');
    expect(Sku::fromString('prd_x-9')->value)->toBe('PRD_X-9');

    expect(fn () => Sku::fromString('   '))->toThrow(InvalidArgumentException::class);
    expect(fn () => Sku::fromString('bad sku'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Sku::fromString('sku#1'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Sku::fromString(str_repeat('A', 65)))->toThrow(InvalidArgumentException::class);
});

it('accepts valid GTIN barcodes and rejects bad check digits', function (): void {
    // Known-valid EAN-13.
    expect(Barcode::passesCheckDigit('4006381333931'))->toBeTrue();
    expect(Barcode::fromNullable('4006381333931')?->value)->toBe('4006381333931');
    expect(Barcode::fromNullable('400-638 133 3931')?->value)->toBe('4006381333931');

    expect(Barcode::passesCheckDigit('4006381333932'))->toBeFalse();
    expect(fn () => Barcode::fromNullable('4006381333932'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Barcode::fromNullable('12345'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Barcode::fromNullable('400638133393X'))->toThrow(InvalidArgumentException::class);

    expect(Barcode::fromNullable(null))->toBeNull();
    expect(Barcode::fromNullable('   '))->toBeNull();
});

it('builds combination identity independent of pair order', function (): void {
    $forward = VariantCombination::fromPairs([
        ['attribute_id' => 4, 'attribute_value_id' => 40],
        ['attribute_id' => 2, 'attribute_value_id' => 21],
    ]);

    $reversed = VariantCombination::fromPairs([
        ['attribute_id' => 2, 'attribute_value_id' => 21],
        ['attribute_id' => 4, 'attribute_value_id' => 40],
    ]);

    expect($forward->signature)->toBe('2:21|4:40');
    expect($reversed->signature)->toBe($forward->signature);
    expect($reversed->hash)->toBe($forward->hash);

    $empty = VariantCombination::fromPairs([]);
    expect($empty->signature)->toBe('');
    expect($empty->hash)->toBe(hash('sha256', ''));
    expect($empty->hash)->not->toBe($forward->hash);

    expect(fn () => VariantCombination::fromPairs([
        ['attribute_id' => 2, 'attribute_value_id' => 21],
        ['attribute_id' => 2, 'attribute_value_id' => 22],
    ]))->toThrow(InvalidArgumentException::class);
});

it('normalizes color hex to uppercase #RRGGBB', function (): void {
    expect(ColorHex::normalize('#1a2b3c'))->toBe('#1A2B3C');
    expect(ColorHex::normalize('1a2b3c'))->toBe('#1A2B3C');
    expect(ColorHex::normalize('#abc'))->toBe('#AABBCC');
    expect(ColorHex::normalize(' #FFF '))->toBe('#FFFFFF');

    expect(ColorHex::normalize('nope'))->toBeNull();
    expect(ColorHex::normalize('#12345'))->toBeNull();
    expect(ColorHex::normalize(null))->toBeNull();
});
