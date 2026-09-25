<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Models\MediaDerivative;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SpeciesMediaUrl
{
    public static function forDerivative(?MediaDerivative $derivative): ?string
    {
        if ($derivative === null) {
            return null;
        }

        if ($derivative->disk === MediaDisk::PrivateOriginals->value || trim($derivative->path) === '') {
            return null;
        }

        try {
            return Storage::disk($derivative->disk)->url($derivative->path);
        } catch (Throwable) {
            return rtrim((string) config('media.public_url_prefix', '/storage/media'), '/').'/'.ltrim($derivative->path, '/');
        }
    }
}
