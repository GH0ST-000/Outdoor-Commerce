<?php

declare(strict_types=1);

use App\Domains\Catalog\DTOs\Media\MediaRenditionData;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Exceptions\MediaSecurityException;
use App\Domains\Catalog\Services\Media\ImageProcessor;
use App\Domains\Catalog\Services\Media\MediaCapabilityService;
use Tests\Support\MediaFixtures;

function processor(): ImageProcessor
{
    return new ImageProcessor(new MediaCapabilityService);
}

it('generates every preset in webp plus a raster fallback', function (): void {
    $result = processor()->process(MediaFixtures::jpeg(1600, 1200), MediaFormat::Jpeg);

    expect($result->width)->toBe(1600)->and($result->height)->toBe(1200);

    $formats = array_unique(array_map(
        static fn (MediaRenditionData $rendition): string => $rendition->format->value,
        $result->renditions,
    ));

    expect($formats)->toContain('webp')->toContain('jpeg');

    foreach (MediaPreset::ordered() as $preset) {
        $match = array_filter(
            $result->renditions,
            static fn (MediaRenditionData $rendition): bool => $rendition->preset === $preset
                && $rendition->format === MediaFormat::Webp,
        );

        expect($match)->toHaveCount(1);
    }
});

it('keeps aspect ratio and refuses to upscale', function (): void {
    $result = processor()->process(MediaFixtures::jpeg(300, 200), MediaFormat::Jpeg);

    foreach ($result->renditions as $rendition) {
        expect($rendition->width)->toBeLessThanOrEqual(300)
            ->and($rendition->height)->toBeLessThanOrEqual(200);

        // 3:2 within a pixel of rounding.
        expect(abs(($rendition->width / $rendition->height) - 1.5))->toBeLessThan(0.02);
    }
});

it('uses png as the fallback for sources that may carry transparency', function (): void {
    $result = processor()->process(MediaFixtures::png(400, 400), MediaFormat::Png);

    $formats = array_unique(array_map(
        static fn (MediaRenditionData $rendition): string => $rendition->format->value,
        $result->renditions,
    ));

    expect($formats)->toContain('png')->not->toContain('jpeg');
});

it('strips metadata by re-encoding', function (): void {
    // A JPEG carrying a recognizable EXIF comment segment.
    $source = MediaFixtures::jpeg(500, 400);
    $marker = 'SECRET-GPS-PAYLOAD';
    $tampered = substr($source, 0, 2)."\xFF\xFE".pack('n', strlen($marker) + 2).$marker.substr($source, 2);

    expect($tampered)->toContain($marker);

    $result = processor()->process($tampered, MediaFormat::Jpeg);

    foreach ($result->renditions as $rendition) {
        expect($rendition->contents)->not->toContain($marker);
    }
});

it('rejects content it cannot decode', function (): void {
    expect(fn () => processor()->process(MediaFixtures::svg(), MediaFormat::Jpeg))
        ->toThrow(MediaSecurityException::class);

    expect(fn () => processor()->process(MediaFixtures::executable(), MediaFormat::Jpeg))
        ->toThrow(MediaSecurityException::class);
});

it('rejects an image beyond the configured pixel ceiling', function (): void {
    config(['media.dimensions.max_pixels' => 1000]);

    expect(fn () => processor()->process(MediaFixtures::jpeg(400, 300), MediaFormat::Jpeg))
        ->toThrow(MediaSecurityException::class);
});

it('skips avif unless it is both enabled and supported', function (): void {
    config(['media.formats.avif_enabled' => false]);

    $result = processor()->process(MediaFixtures::jpeg(400, 300), MediaFormat::Jpeg);
    $formats = array_map(
        static fn (MediaRenditionData $rendition): string => $rendition->format->value,
        $result->renditions,
    );

    expect($formats)->not->toContain('avif');
});
