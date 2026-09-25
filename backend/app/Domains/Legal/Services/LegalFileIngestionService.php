<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Support\LegalLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

final class LegalFileIngestionService
{
    public function __construct(private readonly LegalLogger $logger) {}

    /**
     * @return array{disk: string, path: string, checksum: string, mime: string, size: int, original: string}
     */
    public function storeUpload(UploadedFile $file): array
    {
        $this->assertSafeUpload($file);
        $disk = (string) config('legal.storage.disk', 'legal_private');
        $checksum = hash_file('sha256', $file->getRealPath() ?: '') ?: '';
        if ($checksum === '') {
            throw LegalException::fileRejected('Checksum could not be generated.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $name = date('Y/m/').Str::uuid().'.'.$extension;
        $stored = $file->storeAs('', $name, $disk);
        if ($stored === false) {
            throw LegalException::fileRejected('The file could not be stored.');
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

    /**
     * @return array{disk: string, path: string, checksum: string, mime: string, size: int, original: string}
     */
    public function storeBytes(string $body, string $mime, string $extension): array
    {
        $checksum = hash('sha256', $body);
        $disk = (string) config('legal.storage.disk', 'legal_private');
        $path = date('Y/m/').Str::uuid().'.'.$extension;
        Storage::disk($disk)->put($path, $body);

        return [
            'disk' => $disk,
            'path' => $path,
            'checksum' => $checksum,
            'mime' => $mime,
            'size' => strlen($body),
            'original' => basename($path),
        ];
    }

    public function assertSafeUpload(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getSize() === 0) {
            throw LegalException::fileRejected('The uploaded file is empty or invalid.');
        }

        $maxKb = (int) config('legal.storage.max_file_kilobytes', 20480);
        if ($file->getSize() > $maxKb * 1024) {
            throw LegalException::fileRejected('The uploaded file exceeds the size limit.');
        }

        $original = (string) $file->getClientOriginalName();
        if (str_contains($original, '..') || substr_count($original, '.') > 1) {
            throw LegalException::fileRejected('The filename is not allowed.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExt = config('legal.storage.allowed_extensions', ['pdf', 'txt', 'html']);
        if (! in_array($extension, $allowedExt, true)) {
            throw LegalException::fileRejected('This file type is not allowed.');
        }

        $mime = $this->normalizeMime((string) $file->getMimeType());
        $allowedMime = array_map(
            fn (mixed $value): string => $this->normalizeMime((string) $value),
            config('legal.storage.allowed_mime_types', []),
        );
        if (! in_array($mime, $allowedMime, true)) {
            throw LegalException::fileRejected('The file MIME type is not allowed.');
        }

        $real = new File($file->getRealPath() ?: '');
        $detected = $this->normalizeMime((string) $real->getMimeType());
        if ($this->isDangerousMime($detected) || $this->isDangerousMime($mime)) {
            throw LegalException::fileRejected('The file contents do not match the declared type.');
        }
        if (! $this->mimeMatchesExtension($extension, $mime, $detected, $allowedMime)) {
            throw LegalException::fileRejected('The file contents do not match the declared type.');
        }
    }

    private function normalizeMime(string $mime): string
    {
        $base = strtolower(trim(explode(';', $mime)[0] ?? $mime));

        return $base === '' ? 'application/octet-stream' : $base;
    }

    /**
     * @param  list<string>  $allowedMime
     */
    private function mimeMatchesExtension(string $extension, string $declared, string $detected, array $allowedMime): bool
    {
        if ($detected === $declared || in_array($detected, $allowedMime, true)) {
            return true;
        }

        return match ($extension) {
            'txt' => str_starts_with($detected, 'text/')
                || str_starts_with($detected, 'message/')
                || in_array($detected, ['application/octet-stream', 'inode/x-empty', 'application/x-empty'], true),
            'html' => str_starts_with($detected, 'text/')
                || in_array($detected, ['application/xhtml+xml', 'application/octet-stream'], true),
            'pdf' => in_array($detected, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true),
            default => false,
        };
    }

    private function isDangerousMime(string $mime): bool
    {
        $dangerous = [
            'application/javascript',
            'application/x-executable',
            'application/x-msdownload',
            'application/x-php',
            'application/x-httpd-php',
            'application/x-sh',
            'text/javascript',
            'text/x-php',
            'text/x-shellscript',
        ];

        if (in_array($mime, $dangerous, true)) {
            return true;
        }

        return str_contains($mime, 'php')
            || str_contains($mime, 'executable')
            || str_contains($mime, 'x-msdownload')
            || str_contains($mime, 'x-dosexec');
    }
}
