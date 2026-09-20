<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaFormat;

/**
 * Runtime encoder detection. A container without WebP-enabled GD must still be
 * able to publish images, so format support is a question we ask rather than
 * assume.
 */
final class MediaCapabilityService
{
    public function supportsGd(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    public function supportsWebp(): bool
    {
        return function_exists('imagewebp') && $this->gdFlag('WebP Support');
    }

    /**
     * AVIF is opt-in twice over: the build must support it and config must ask
     * for it. Even then, encoding failures are swallowed by the processor.
     */
    public function supportsAvif(): bool
    {
        if (! (bool) config('media.formats.avif_enabled', false)) {
            return false;
        }

        return function_exists('imageavif') && $this->gdFlag('AVIF Support');
    }

    public function supports(MediaFormat $format): bool
    {
        return match ($format) {
            MediaFormat::Webp => $this->supportsWebp(),
            MediaFormat::Avif => $this->supportsAvif(),
            MediaFormat::Jpeg, MediaFormat::Png => $this->supportsGd(),
        };
    }

    /**
     * @return array{gd: bool, webp: bool, avif: bool, jpeg: bool, png: bool}
     */
    public function summary(): array
    {
        return [
            'gd' => $this->supportsGd(),
            'webp' => $this->supportsWebp(),
            'avif' => $this->supportsAvif(),
            'jpeg' => $this->supportsGd() && $this->gdFlag('JPEG Support'),
            'png' => $this->supportsGd() && $this->gdFlag('PNG Support'),
        ];
    }

    private function gdFlag(string $key): bool
    {
        if (! function_exists('gd_info')) {
            return false;
        }

        /** @var array<string, bool|string> $info */
        $info = gd_info();

        return (bool) ($info[$key] ?? false);
    }
}
