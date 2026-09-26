<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Support\SpatialLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

final class SpatialFileIngestionService
{
    public function __construct(private readonly SpatialLogger $logger) {}

    /**
     * @return array{disk: string, path: string, checksum: string, mime: string, size: int, original: string}
     */
    public function storeUpload(UploadedFile $file): array
    {
        $this->assertSafeUpload($file);
        $disk = (string) config('spatial.storage.disk', 'spatial_private');
        $checksum = hash_file('sha256', $file->getRealPath() ?: '') ?: '';
        if ($checksum === '') {
            throw SpatialException::fileRejected('Checksum could not be generated.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $name = date('Y/m/').Str::uuid().'.'.$extension;
        $stored = $file->storeAs('', $name, $disk);
        if ($stored === false) {
            throw SpatialException::fileRejected('The file could not be stored.');
        }

        $this->logger->info('file_ingested', [
            'checksum' => $checksum,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return [
            'disk' => $disk,
            'path' => $stored,
            'checksum' => $checksum,
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'original' => basename((string) $file->getClientOriginalName()),
        ];
    }

    public function read(string $disk, string $path): string
    {
        $contents = Storage::disk($disk)->get($path);
        if (! is_string($contents) || $contents === '') {
            throw SpatialException::fileRejected('The stored spatial file could not be read.');
        }

        return $contents;
    }

    public function assertSafeUpload(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getSize() === 0) {
            throw SpatialException::fileRejected('The uploaded file is empty or invalid.');
        }

        $maxKb = (int) config('spatial.storage.max_file_kilobytes', 20480);
        if ($file->getSize() > $maxKb * 1024) {
            throw SpatialException::fileRejected('The uploaded file exceeds the size limit.');
        }

        $original = (string) $file->getClientOriginalName();
        if (str_contains($original, '..') || str_contains($original, '/') || str_contains($original, '\\')) {
            throw SpatialException::fileRejected('The filename is not allowed.');
        }
        if (preg_match('/\.(zip|shp|kml|kmz)$/i', $original) === 1) {
            throw SpatialException::fileRejected('Only GeoJSON is supported. Zip, KML, and shapefile packages are rejected.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExt = config('spatial.storage.allowed_extensions', ['geojson', 'json']);
        if (! in_array($extension, $allowedExt, true)) {
            throw SpatialException::fileRejected('This file type is not allowed.');
        }

        $mime = $this->normalizeMime((string) $file->getMimeType());
        $allowedMime = array_map(
            fn (mixed $value): string => $this->normalizeMime((string) $value),
            config('spatial.storage.allowed_mime_types', []),
        );
        if (! in_array($mime, $allowedMime, true)) {
            throw SpatialException::fileRejected('The file MIME type is not allowed.');
        }

        $real = new File($file->getRealPath() ?: '');
        $detected = $this->normalizeMime((string) $real->getMimeType());
        $dangerous = ['application/zip', 'application/x-zip-compressed', 'application/javascript', 'application/x-php'];
        if (in_array($detected, $dangerous, true) || in_array($mime, $dangerous, true)) {
            throw SpatialException::fileRejected('The file contents do not match the declared type.');
        }
        $jsonLike = ['application/json', 'application/geo+json', 'text/plain', 'text/json', 'application/octet-stream'];
        if (! in_array($detected, $jsonLike, true) && ! in_array($mime, $jsonLike, true)) {
            throw SpatialException::fileRejected('The file contents do not match the declared type.');
        }
    }

    private function normalizeMime(string $mime): string
    {
        $base = strtolower(trim(explode(';', $mime)[0] ?? $mime));

        return $base === '' ? 'application/octet-stream' : $base;
    }
}
