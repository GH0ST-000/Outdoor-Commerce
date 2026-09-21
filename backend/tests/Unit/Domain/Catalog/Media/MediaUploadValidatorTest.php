<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Services\Media\MediaUploadValidator;
use Illuminate\Validation\ValidationException;
use Tests\Support\MediaFixtures;

afterEach(function (): void {
    MediaFixtures::cleanup();
});

function mediaValidator(): MediaUploadValidator
{
    return new MediaUploadValidator;
}

it('identifies accepted formats from content rather than filename', function (): void {
    $cases = [
        [MediaFixtures::jpegUpload('photo.webp'), MediaFormat::Jpeg, 'image/jpeg', 'jpg'],
        [MediaFixtures::pngUpload('photo.jpg'), MediaFormat::Png, 'image/png', 'png'],
        [MediaFixtures::webpUpload('photo.jpeg'), MediaFormat::Webp, 'image/webp', 'webp'],
    ];

    foreach ($cases as [$file, $format, $mimeType, $extension]) {
        $inspection = mediaValidator()->inspectUpload($file);

        expect($inspection->format)->toBe($format)
            ->and($inspection->mimeType)->toBe($mimeType)
            ->and($inspection->extension)->toBe($extension)
            ->and($inspection->width)->toBe(800)
            ->and($inspection->height)->toBe(600)
            ->and($inspection->checksum)->toHaveLength(64);
    }
});

it('rejects svg, executables, polyglots, and empty payloads', function (): void {
    $cases = [
        'svg by extension' => MediaFixtures::upload(MediaFixtures::svg(), 'logo.svg'),
        'svg renamed' => MediaFixtures::upload(MediaFixtures::svg(), 'logo.png'),
        'executable' => MediaFixtures::upload(MediaFixtures::executable(), 'setup.jpg'),
        'polyglot' => MediaFixtures::upload(MediaFixtures::polyglot(), 'shell.jpg'),
        'empty' => MediaFixtures::upload('', 'empty.jpg'),
        'text' => MediaFixtures::upload(str_repeat('not an image ', 500), 'notes.jpg'),
        'gif' => MediaFixtures::upload('GIF89a'.str_repeat("\x00", 2048), 'anim.gif'),
    ];

    foreach ($cases as $label => $file) {
        expect(fn () => mediaValidator()->inspectUpload($file))
            ->toThrow(ValidationException::class, message: "Expected [{$label}] to be rejected.");
    }
});

it('enforces dimension and pixel ceilings', function (): void {
    $small = MediaFixtures::jpegUpload('small.jpg', 100, 100);
    expect(fn () => mediaValidator()->inspectUpload($small))->toThrow(ValidationException::class);

    config(['media.dimensions.max_width' => 500]);
    $wide = MediaFixtures::jpegUpload('wide.jpg', 800, 400);
    expect(fn () => mediaValidator()->inspectUpload($wide))->toThrow(ValidationException::class);

    config(['media.dimensions.max_width' => 12000, 'media.dimensions.max_pixels' => 10000]);
    $bomb = MediaFixtures::jpegUpload('bomb.jpg', 800, 600);
    expect(fn () => mediaValidator()->inspectUpload($bomb))->toThrow(ValidationException::class);
});

it('reports every invalid file in a batch and keeps indexes aligned', function (): void {
    $files = [
        MediaFixtures::jpegUpload('good.jpg'),
        MediaFixtures::upload(MediaFixtures::svg(), 'bad.svg'),
        MediaFixtures::upload(MediaFixtures::executable(), 'worse.jpg'),
    ];

    try {
        mediaValidator()->validate($files);
        expect(false)->toBeTrue('The batch should have been rejected.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['files.1', 'files.2']);
    }
});

it('fails the batch when the gallery quota would be exceeded', function (): void {
    config(['media.uploads.max_per_owner' => 3]);

    try {
        mediaValidator()->validate([MediaFixtures::jpegUpload(), MediaFixtures::jpegUpload('b.jpg')], existingCount: 2);
        expect(false)->toBeTrue('The batch should have been rejected.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['files'])
            ->and($exception->errors()['files'][0])->toContain('Remaining slots: 1');
    }
});

it('sanitizes the display filename without ever using it as a path', function (): void {
    $validator = mediaValidator();

    expect($validator->sanitizeFilename('../../etc/passwd.jpg'))->toBe('passwd')
        ->and($validator->sanitizeFilename('Rifle Scope 4x32.JPG'))->toBe('Rifle Scope 4x32')
        ->and($validator->sanitizeFilename('shell;rm -rf.png'))->toBe('shell-rm -rf')
        ->and($validator->sanitizeFilename('..'))->toBeNull()
        ->and($validator->sanitizeFilename('ოპტიკა.jpg'))->not->toContain('/');
});

it('accepts a valid file and returns an inspection for each batch entry', function (): void {
    $inspections = mediaValidator()->validate([
        MediaFixtures::jpegUpload('one.jpg'),
        MediaFixtures::pngUpload('two.png'),
    ]);

    expect($inspections)->toHaveCount(2)
        ->and($inspections[0]->sanitizedFilename)->toBe('one')
        ->and($inspections[1]->format)->toBe(MediaFormat::Png);
});
