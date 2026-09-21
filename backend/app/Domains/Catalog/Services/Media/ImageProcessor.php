<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\DTOs\Media\MediaProcessingResultData;
use App\Domains\Catalog\DTOs\Media\MediaRenditionData;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Exceptions\MediaProcessingException;
use App\Domains\Catalog\Exceptions\MediaSecurityException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Derivative generation on Intervention Image v3 with the GD driver.
 *
 * Every rendition is a fresh re-encode of the decoded bitmap, which is also how
 * metadata stripping happens: EXIF, GPS, colour profiles, and any trailing bytes
 * appended to the original simply do not survive the round trip.
 */
final class ImageProcessor
{
    public function __construct(
        private readonly MediaCapabilityService $capabilities,
    ) {}

    /**
     * @throws MediaProcessingException
     */
    public function process(string $contents, MediaFormat $sourceFormat): MediaProcessingResultData
    {
        if (! $this->capabilities->supportsGd()) {
            throw new MediaProcessingException(
                'gd_unavailable',
                'The image processing extension is unavailable on this worker.',
            );
        }

        $source = $this->decodeOriented($contents);
        $this->assertDimensions($source->width(), $source->height());

        $width = $source->width();
        $height = $source->height();
        unset($source);

        $targets = $this->targetFormats($sourceFormat);
        $renditions = [];
        $skipped = [];

        foreach (MediaPreset::ordered() as $preset) {
            // Decoded per preset rather than cloned: Intervention modifiers mutate
            // the instance in place and a shallow clone would share the GD handle.
            $scaled = $this->decodeOriented($contents)->scaleDown($preset->maxWidth(), $preset->maxHeight());

            foreach ($targets as $format) {
                $encoded = $this->encode($scaled, $format);

                if ($encoded === null) {
                    $skipped[$format->value] = true;

                    continue;
                }

                $renditions[] = new MediaRenditionData(
                    preset: $preset,
                    format: $format,
                    width: $scaled->width(),
                    height: $scaled->height(),
                    contents: $encoded,
                );
            }

            unset($scaled);
        }

        if ($renditions === []) {
            throw new MediaProcessingException(
                'no_encoder_available',
                'No image encoder available for the configured formats.',
            );
        }

        return new MediaProcessingResultData(
            width: $width,
            height: $height,
            renditions: $renditions,
            skippedFormats: array_keys($skipped),
        );
    }

    /**
     * Decodes and applies EXIF orientation. `scaleDown` later preserves the aspect
     * ratio and never upscales, so a small original yields small derivatives.
     *
     * @throws MediaSecurityException
     */
    private function decodeOriented(string $contents): ImageInterface
    {
        try {
            $image = $this->manager()->read($contents);
        } catch (Throwable $exception) {
            throw new MediaSecurityException(
                'decode_failed',
                'The stored file could not be decoded as an image.',
                $exception->getMessage(),
                $exception,
            );
        }

        try {
            // Bake in the EXIF orientation before re-encoding discards the tag.
            $image->orient();
        } catch (Throwable) {
            // Sources without orientation data are already upright.
        }

        return $image;
    }

    /**
     * @return string|null Null when the format is unsupported or encoding failed in
     *                     a way that must not sink the whole job (AVIF).
     */
    private function encode(ImageInterface $image, MediaFormat $format): ?string
    {
        if (! $this->capabilities->supports($format)) {
            return null;
        }

        try {
            $encoded = match ($format) {
                MediaFormat::Webp => $image->toWebp(quality: (int) config('media.quality.webp', 82)),
                MediaFormat::Jpeg => $image->toJpeg(quality: (int) config('media.quality.jpeg', 85)),
                MediaFormat::Png => $image->toPng(),
                MediaFormat::Avif => $image->toAvif(quality: (int) config('media.quality.avif', 50)),
            };

            return (string) $encoded;
        } catch (Throwable $exception) {
            // AVIF is a best-effort bonus format; a build that advertises support
            // but fails at runtime must not fail the asset.
            if ($format === MediaFormat::Avif) {
                return null;
            }

            throw new MediaProcessingException(
                'encode_failed',
                'The image could not be re-encoded.',
                $format->value.': '.$exception->getMessage(),
                $exception,
            );
        }
    }

    /**
     * WebP is required wherever the build supports it. The raster fallback keeps
     * PNG for sources that may carry transparency and JPEG everywhere else.
     *
     * @return list<MediaFormat>
     */
    private function targetFormats(MediaFormat $sourceFormat): array
    {
        $formats = [];

        if ($this->capabilities->supportsWebp()) {
            $formats[] = MediaFormat::Webp;
        }

        $formats[] = in_array($sourceFormat, [MediaFormat::Png, MediaFormat::Webp], true)
            ? MediaFormat::Png
            : MediaFormat::Jpeg;

        if ($this->capabilities->supportsAvif()) {
            $formats[] = MediaFormat::Avif;
        }

        return array_values(array_unique($formats, SORT_REGULAR));
    }

    /**
     * @throws MediaSecurityException
     */
    private function assertDimensions(int $width, int $height): void
    {
        $maxWidth = (int) config('media.dimensions.max_width', 12000);
        $maxHeight = (int) config('media.dimensions.max_height', 12000);
        $maxPixels = (int) config('media.dimensions.max_pixels', 40000000);

        if ($width < 1 || $height < 1) {
            throw new MediaSecurityException('invalid_dimensions', 'The image dimensions are invalid.');
        }

        if ($width > $maxWidth || $height > $maxHeight || $width * $height > $maxPixels) {
            throw new MediaSecurityException(
                'dimensions_exceeded',
                'The image exceeds the maximum supported dimensions.',
                "{$width}x{$height}",
            );
        }
    }

    private function manager(): ImageManager
    {
        return new ImageManager(new Driver);
    }
}
