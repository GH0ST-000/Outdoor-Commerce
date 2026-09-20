<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Image fixtures generated with GD at test time.
 *
 * Nothing is committed to the repository: binary fixtures rot, and a generated
 * JPEG is guaranteed to be a real JPEG on whatever GD build the suite runs on.
 * Files are written to the system temp directory and cleaned up per test.
 */
final class MediaFixtures
{
    /** @var list<string> */
    private static array $temporaryPaths = [];

    /**
     * Fakes both media disks. The derivatives disk gets an explicit URL because
     * Storage::fake does not inherit one from the real configuration.
     */
    public static function fakeDisks(): void
    {
        Storage::fake('media_private');
        Storage::fake('media_public', ['url' => 'http://localhost/storage/media']);
    }

    public static function jpeg(int $width = 800, int $height = 600): string
    {
        return self::encode($width, $height, static fn ($image) => imagejpeg($image, null, 90));
    }

    public static function png(int $width = 800, int $height = 600): string
    {
        return self::encode($width, $height, static fn ($image) => imagepng($image));
    }

    public static function webp(int $width = 800, int $height = 600): string
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('This GD build cannot create WebP fixtures.');
        }

        return self::encode($width, $height, static fn ($image) => imagewebp($image, null, 90));
    }

    public static function svg(): string
    {
        return <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
              <script>alert('xss')</script>
              <rect width="400" height="400" fill="#123456"/>
            </svg>
            SVG;
    }

    /**
     * A Windows executable header padded out so it is not rejected merely for being
     * too small.
     */
    public static function executable(): string
    {
        return "MZ\x90\x00\x03\x00\x00\x00".str_repeat("\x00", 4096);
    }

    /**
     * A JPEG header glued in front of PHP source: the classic polyglot attempt.
     */
    public static function polyglot(): string
    {
        return "\xFF\xD8\xFF\xE0".str_repeat("\x00", 128)."<?php echo shell_exec(\$_GET['c']); ?>".str_repeat("\x20", 2048);
    }

    public static function upload(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'media_fixture_');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a fixture file.');
        }

        file_put_contents($path, $contents);
        self::$temporaryPaths[] = $path;

        // `test: true` keeps Symfony from rejecting a file that did not arrive
        // through a real multipart upload.
        return new UploadedFile($path, $name, null, null, true);
    }

    public static function jpegUpload(string $name = 'scope.jpg', int $width = 800, int $height = 600): UploadedFile
    {
        return self::upload(self::jpeg($width, $height), $name);
    }

    public static function pngUpload(string $name = 'scope.png', int $width = 800, int $height = 600): UploadedFile
    {
        return self::upload(self::png($width, $height), $name);
    }

    public static function webpUpload(string $name = 'scope.webp', int $width = 800, int $height = 600): UploadedFile
    {
        return self::upload(self::webp($width, $height), $name);
    }

    public static function cleanup(): void
    {
        foreach (self::$temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        self::$temporaryPaths = [];
    }

    /**
     * @param  callable(\GdImage): bool  $writer
     */
    private static function encode(int $width, int $height, callable $writer): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD could not allocate the fixture canvas.');
        }

        // Some structure rather than a flat fill, so encoders produce realistic
        // output sizes instead of a few hundred bytes.
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 24, 48, 32));

        for ($i = 0; $i < 6; $i++) {
            imagefilledrectangle(
                $image,
                (int) ($width * $i / 12),
                (int) ($height * $i / 12),
                (int) ($width * ($i + 3) / 12),
                (int) ($height * ($i + 4) / 12),
                (int) imagecolorallocate($image, 40 + $i * 30, 90 + $i * 20, 60 + $i * 25),
            );
        }

        ob_start();
        $writer($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        if ($contents === '') {
            throw new RuntimeException('GD produced an empty fixture.');
        }

        return $contents;
    }
}
