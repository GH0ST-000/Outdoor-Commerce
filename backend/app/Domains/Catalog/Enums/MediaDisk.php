<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * The only disks media code may read from or delete on. Cleanup refuses any
 * path whose recorded disk is not one of these.
 */
enum MediaDisk: string
{
    case PrivateOriginals = 'media_private';
    case PublicDerivatives = 'media_public';

    public static function originals(): self
    {
        return self::from((string) config('media.disks.originals', self::PrivateOriginals->value));
    }

    public static function derivatives(): self
    {
        return self::from((string) config('media.disks.derivatives', self::PublicDerivatives->value));
    }

    public static function isAllowed(?string $disk): bool
    {
        return $disk !== null && self::tryFrom($disk) !== null;
    }
}
