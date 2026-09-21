<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Models\MediaDerivative;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The single place a public media URL is produced. Controllers and resources ask
 * this service rather than concatenating strings, so changing where derivatives
 * are served from is a one-line change.
 */
final class MediaUrlService
{
    public function forDerivative(MediaDerivative $derivative): ?string
    {
        return $this->forPath($derivative->disk, $derivative->path);
    }

    public function forPath(string $disk, string $path): ?string
    {
        if (! MediaDisk::isAllowed($disk) || trim($path) === '') {
            return null;
        }

        // Originals live on a private disk and never get a URL.
        if ($disk === MediaDisk::PrivateOriginals->value) {
            return null;
        }

        try {
            return Storage::disk($disk)->url($path);
        } catch (Throwable) {
            // Disks without a configured URL (for example Storage::fake in tests)
            // still need a stable, relative reference.
            return rtrim((string) config('media.public_url_prefix', '/storage/media'), '/').'/'.ltrim($path, '/');
        }
    }
}
