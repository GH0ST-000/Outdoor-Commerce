<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Services\Media\MediaStorageService;
use App\Domains\Catalog\Services\Media\MediaUrlService;
use Illuminate\Support\Carbon;

it('derives original and derivative paths from the uuid and creation month', function (): void {
    $storage = new MediaStorageService;
    $createdAt = Carbon::parse('2026-09-20 12:00:00');
    $uuid = '0199a1f2-3b4c-4d5e-8f90-112233445566';

    expect($storage->originalPath($uuid, 'jpg', $createdAt))
        ->toBe("originals/2026/09/{$uuid}/original.jpg")
        ->and($storage->derivativePath($uuid, MediaPreset::CardLarge, MediaFormat::Webp, $createdAt))
        ->toBe("derivatives/2026/09/{$uuid}/card_large.webp")
        ->and($storage->derivativePath($uuid, MediaPreset::Detail, MediaFormat::Jpeg, $createdAt))
        ->toBe("derivatives/2026/09/{$uuid}/detail.jpg");
});

it('allows only the media disks', function (): void {
    expect(MediaDisk::isAllowed('media_private'))->toBeTrue()
        ->and(MediaDisk::isAllowed('media_public'))->toBeTrue()
        ->and(MediaDisk::isAllowed('s3'))->toBeFalse()
        ->and(MediaDisk::isAllowed('local'))->toBeFalse()
        ->and(MediaDisk::isAllowed(null))->toBeFalse();
});

it('refuses to delete a path recorded on a disk outside the allowlist', function (): void {
    $storage = new MediaStorageService;

    expect($storage->deleteRecordedPath('s3', 'originals/2026/09/x/original.jpg'))->toBeFalse()
        ->and($storage->deleteRecordedPath('local', 'anything.jpg'))->toBeFalse()
        ->and($storage->deleteRecordedPath('media_public', '  '))->toBeFalse();
});

it('never builds a url for a private original', function (): void {
    $urls = new MediaUrlService;
    $public = $urls->forPath('media_public', 'derivatives/2026/09/x/card.webp');

    expect($urls->forPath('media_private', 'originals/2026/09/x/original.jpg'))->toBeNull()
        ->and($urls->forPath('s3', 'derivatives/2026/09/x/card.webp'))->toBeNull()
        ->and($public)->not->toBeNull()
        ->and($public)->toEndWith('/storage/media/derivatives/2026/09/x/card.webp')
        ->and($public)->not->toContain('originals/');
});
