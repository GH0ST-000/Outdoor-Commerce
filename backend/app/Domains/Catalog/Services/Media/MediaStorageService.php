<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Models\MediaAsset;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Owns every media path on disk.
 *
 * Paths are derived from a server-generated UUID and the asset's creation month,
 * never from anything the client sent. The client filename survives only as
 * sanitized display metadata in the database.
 */
final class MediaStorageService
{
    public function originalsDisk(): Filesystem
    {
        return Storage::disk(MediaDisk::originals()->value);
    }

    public function derivativesDisk(): Filesystem
    {
        return Storage::disk(MediaDisk::derivatives()->value);
    }

    public function originalDirectory(string $uuid, DateTimeInterface $createdAt): string
    {
        return sprintf('originals/%s/%s/%s', $createdAt->format('Y'), $createdAt->format('m'), $uuid);
    }

    public function originalPath(string $uuid, string $extension, DateTimeInterface $createdAt): string
    {
        return $this->originalDirectory($uuid, $createdAt).'/original.'.$extension;
    }

    public function derivativeDirectory(string $uuid, DateTimeInterface $createdAt): string
    {
        return sprintf('derivatives/%s/%s/%s', $createdAt->format('Y'), $createdAt->format('m'), $uuid);
    }

    public function derivativePath(
        string $uuid,
        MediaPreset $preset,
        MediaFormat $format,
        DateTimeInterface $createdAt,
    ): string {
        return sprintf(
            '%s/%s.%s',
            $this->derivativeDirectory($uuid, $createdAt),
            $preset->value,
            $format->extension(),
        );
    }

    /**
     * Streams the temporary upload into the private disk under a generated name.
     */
    public function storeOriginal(
        UploadedFile $file,
        string $uuid,
        string $extension,
        DateTimeInterface $createdAt,
    ): string {
        $path = $this->originalPath($uuid, $extension, $createdAt);
        $stream = fopen($file->getRealPath() ?: '', 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        try {
            $stored = $this->originalsDisk()->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if ($stored === false) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        return $path;
    }

    public function readOriginal(MediaAsset $asset): string
    {
        if (! MediaDisk::isAllowed($asset->original_disk)) {
            throw new RuntimeException("Refusing to read media from disk [{$asset->original_disk}].");
        }

        $contents = Storage::disk($asset->original_disk)->get($asset->original_path);

        if ($contents === null) {
            throw new RuntimeException("Original media file is missing at [{$asset->original_path}].");
        }

        return $contents;
    }

    /**
     * Copies the original to a temporary local file so finfo/getimagesize can
     * inspect it regardless of which driver backs the disk.
     */
    public function copyOriginalToTemporaryFile(MediaAsset $asset): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'media_');

        if ($temporary === false) {
            throw new RuntimeException('Unable to allocate a temporary file.');
        }

        file_put_contents($temporary, $this->readOriginal($asset));

        return $temporary;
    }

    public function putDerivative(string $path, string $contents): void
    {
        if ($this->derivativesDisk()->put($path, $contents) === false) {
            throw new RuntimeException("Unable to write derivative at [{$path}].");
        }
    }

    public function deleteOriginal(MediaAsset $asset): bool
    {
        if (! MediaDisk::isAllowed($asset->original_disk)) {
            return false;
        }

        return Storage::disk($asset->original_disk)->delete($asset->original_path);
    }

    /**
     * Deletes a single recorded path. Refuses any disk outside the media allowlist
     * so a tampered row can never reach unrelated storage.
     */
    public function deleteRecordedPath(string $disk, string $path): bool
    {
        if (! MediaDisk::isAllowed($disk) || trim($path) === '') {
            return false;
        }

        return Storage::disk($disk)->delete($path);
    }

    public function deleteDirectories(MediaAsset $asset): void
    {
        $createdAt = $asset->created_at ?? now();

        if (MediaDisk::isAllowed($asset->original_disk)) {
            Storage::disk($asset->original_disk)
                ->deleteDirectory($this->originalDirectory($asset->uuid, $createdAt));
        }

        $this->derivativesDisk()->deleteDirectory($this->derivativeDirectory($asset->uuid, $createdAt));
    }
}
