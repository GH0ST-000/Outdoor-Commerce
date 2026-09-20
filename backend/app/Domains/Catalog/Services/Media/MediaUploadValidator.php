<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Media;

use App\Domains\Catalog\DTOs\Media\MediaFileInspectionData;
use App\Domains\Catalog\Enums\MediaFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Content-first upload validation.
 *
 * The client's filename and Content-Type are treated as untrusted labels. What a
 * file *is* comes from finfo plus getimagesize, and the two must agree — that is
 * what rejects polyglots (a GIF/JPEG header glued in front of a script) as well
 * as plain renames.
 *
 * Validation is all-or-nothing: one bad file fails the whole request with
 * per-file error keys, so an admin never ends up with a half-applied gallery.
 */
final class MediaUploadValidator
{
    /**
     * Byte signatures we accept, keyed by detected MIME type.
     *
     * @var array<string, list<string>>
     */
    private const SIGNATURES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/webp' => ['RIFF'],
    ];

    /**
     * Executable container headers. Only meaningful at offset zero — searching for
     * these deeper in a real image would reject valid files at random.
     *
     * @var list<string>
     */
    private const EXECUTABLE_HEADERS = ['MZ', "\x7FELF", '#!/', "\xCA\xFE\xBA\xBE"];

    /**
     * Markup/script fragments that must not appear in the first kilobyte.
     *
     * @var list<string>
     */
    private const FORBIDDEN_MARKUP = ['<?php', '<?=', '<script', '<svg', '<html', '<!doctype'];

    /**
     * @param  list<UploadedFile>  $files
     * @return list<MediaFileInspectionData>
     *
     * @throws ValidationException
     */
    public function validate(array $files, int $existingCount = 0, ?int $maxPerOwner = null): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        if ($files === []) {
            throw ValidationException::withMessages(['files' => ['At least one image file is required.']]);
        }

        $maxPerRequest = (int) config('media.uploads.max_files_per_request', 10);
        if (count($files) > $maxPerRequest) {
            $errors['files'] = ["No more than {$maxPerRequest} files can be uploaded in one request."];
        }

        $maxPerOwner ??= (int) config('media.uploads.max_per_owner', 24);
        if ($existingCount + count($files) > $maxPerOwner) {
            $remaining = max(0, $maxPerOwner - $existingCount);
            $errors['files'] = array_merge($errors['files'] ?? [], [
                "The gallery limit of {$maxPerOwner} images would be exceeded. Remaining slots: {$remaining}.",
            ]);
        }

        // Request-level failures short-circuit file inspection so error keys stay predictable.
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $inspections = [];

        foreach ($files as $index => $file) {
            try {
                $inspections[] = $this->inspectUpload($file);
            } catch (ValidationException $exception) {
                /** @var array<string, list<string>> $messages */
                $messages = $exception->errors();
                $errors["files.{$index}"] = $messages['file'] ?? ['The file is invalid.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $inspections;
    }

    /**
     * @throws ValidationException
     */
    public function inspectUpload(UploadedFile $file): MediaFileInspectionData
    {
        if (! $file->isValid()) {
            $this->fail('The file could not be uploaded.');
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            $this->fail('The file could not be read.');
        }

        $this->assertExtensionNotBlocked((string) $file->getClientOriginalName());

        $inspection = $this->inspectPath($path);

        return new MediaFileInspectionData(
            absolutePath: $inspection->absolutePath,
            format: $inspection->format,
            mimeType: $inspection->mimeType,
            extension: $inspection->extension,
            width: $inspection->width,
            height: $inspection->height,
            byteSize: $inspection->byteSize,
            checksum: $inspection->checksum,
            sanitizedFilename: $this->sanitizeFilename((string) $file->getClientOriginalName()),
        );
    }

    /**
     * Re-runs the same checks against bytes already on a disk. Used by the worker
     * so a file that changed between upload and processing cannot slip through.
     *
     * @throws ValidationException
     */
    public function inspectPath(string $absolutePath): MediaFileInspectionData
    {
        $byteSize = @filesize($absolutePath);

        if ($byteSize === false) {
            $this->fail('The file could not be read.');
        }

        $minBytes = (int) config('media.uploads.min_file_size_bytes', 64);
        if ($byteSize <= $minBytes) {
            $this->fail('The file is empty or truncated.');
        }

        $maxBytes = (int) config('media.uploads.max_file_size_kilobytes', 12288) * 1024;
        if ($byteSize > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            $this->fail("The file may not be larger than {$maxMb} MB.");
        }

        $mimeType = $this->detectMimeType($absolutePath);

        /** @var list<string> $accepted */
        $accepted = config('media.uploads.accepted_mime_types', []);
        if ($mimeType === null || ! in_array($mimeType, $accepted, true)) {
            $this->fail('Only JPEG, PNG, and WebP images are accepted.');
        }

        $this->assertSignatureMatches($absolutePath, $mimeType);

        $size = @getimagesize($absolutePath);
        if ($size === false) {
            $this->fail('The file is not a readable image.');
        }

        $declared = (string) $size['mime'];
        if ($this->normalizeMime($declared) !== $this->normalizeMime($mimeType)) {
            $this->fail('The image content does not match its detected type.');
        }

        $format = MediaFormat::fromMimeType($mimeType);
        if ($format === null) {
            $this->fail('Only JPEG, PNG, and WebP images are accepted.');
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $this->assertDimensions($width, $height);

        $checksum = hash_file('sha256', $absolutePath);

        return new MediaFileInspectionData(
            absolutePath: $absolutePath,
            format: $format,
            mimeType: $mimeType,
            extension: $format->extension(),
            width: $width,
            height: $height,
            byteSize: (int) $byteSize,
            checksum: $checksum === false ? '' : $checksum,
            sanitizedFilename: null,
        );
    }

    /**
     * Keeps a human-recognizable name for the admin UI while guaranteeing it can
     * never be used as a path segment.
     */
    public function sanitizeFilename(string $original): ?string
    {
        $base = basename(str_replace('\\', '/', $original));
        $name = pathinfo($base, PATHINFO_FILENAME);
        $ascii = Str::ascii($name);
        $clean = (string) preg_replace('/[^A-Za-z0-9 ._-]+/', '-', $ascii);
        $clean = trim((string) preg_replace('/-{2,}/', '-', $clean), ' .-_');

        if ($clean === '') {
            return null;
        }

        return Str::limit($clean, (int) config('media.metadata.original_filename_max_length', 180), '');
    }

    private function detectMimeType(string $absolutePath): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $detected = finfo_file($finfo, $absolutePath);
        finfo_close($finfo);

        return is_string($detected) && $detected !== '' ? strtolower($detected) : null;
    }

    /**
     * @throws ValidationException
     */
    private function assertSignatureMatches(string $absolutePath, string $mimeType): void
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            $this->fail('The file could not be read.');
        }

        $head = (string) fread($handle, 1024);
        fclose($handle);

        $signatures = self::SIGNATURES[$mimeType] ?? [];
        $matched = $signatures === [];

        foreach ($signatures as $signature) {
            if (str_starts_with($head, $signature)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            $this->fail('The image content does not match its detected type.');
        }

        // WebP is RIFF-framed; make sure it is actually a WebP RIFF.
        if ($mimeType === 'image/webp' && ! str_contains(substr($head, 0, 16), 'WEBP')) {
            $this->fail('The image content does not match its detected type.');
        }

        foreach (self::EXECUTABLE_HEADERS as $header) {
            if (str_starts_with($head, $header)) {
                $this->fail('The file contains executable content.');
            }
        }

        $lower = strtolower($head);
        foreach (self::FORBIDDEN_MARKUP as $marker) {
            if (str_contains($lower, $marker)) {
                $this->fail('The file contains markup or script content.');
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            $this->fail('The image dimensions could not be determined.');
        }

        $minWidth = (int) config('media.dimensions.min_width', 200);
        $minHeight = (int) config('media.dimensions.min_height', 200);
        if ($width < $minWidth || $height < $minHeight) {
            $this->fail("The image must be at least {$minWidth}x{$minHeight} pixels.");
        }

        $maxWidth = (int) config('media.dimensions.max_width', 12000);
        $maxHeight = (int) config('media.dimensions.max_height', 12000);
        if ($width > $maxWidth || $height > $maxHeight) {
            $this->fail("The image may not exceed {$maxWidth}x{$maxHeight} pixels.");
        }

        $maxPixels = (int) config('media.dimensions.max_pixels', 40000000);
        if ($width * $height > $maxPixels) {
            $megapixels = round($maxPixels / 1000000);
            $this->fail("The image may not exceed {$megapixels} megapixels.");
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertExtensionNotBlocked(string $clientName): void
    {
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        /** @var list<string> $blocked */
        $blocked = config('media.uploads.blocked_extensions', []);

        if ($extension !== '' && in_array($extension, $blocked, true)) {
            $this->fail('This file type is not allowed.');
        }
    }

    private function normalizeMime(string $mimeType): string
    {
        return $mimeType === 'image/jpg' ? 'image/jpeg' : strtolower(trim($mimeType));
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
