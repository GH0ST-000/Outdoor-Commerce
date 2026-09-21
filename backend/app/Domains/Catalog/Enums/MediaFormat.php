<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

enum MediaFormat: string
{
    case Webp = 'webp';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Avif = 'avif';

    public function extension(): string
    {
        return match ($this) {
            self::Webp => 'webp',
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Avif => 'avif',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Avif => 'image/avif',
        };
    }

    public static function fromMimeType(string $mimeType): ?self
    {
        return match (strtolower(trim($mimeType))) {
            'image/jpeg', 'image/jpg' => self::Jpeg,
            'image/png' => self::Png,
            'image/webp' => self::Webp,
            'image/avif' => self::Avif,
            default => null,
        };
    }
}
