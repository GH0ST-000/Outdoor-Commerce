<?php

declare(strict_types=1);

it('serves a public media derivative without a signature', function (): void {
    $directory = storage_path('app/public/media/derivatives/public-storage-test');
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    file_put_contents($directory.'/card.webp', 'webp-bytes');

    try {
        $this->get('/storage/media/derivatives/public-storage-test/card.webp')
            ->assertOk();
    } finally {
        @unlink($directory.'/card.webp');
        @rmdir($directory);
    }
});

it('does not serve a private file from the public media url', function (): void {
    $this->get('/storage/media/../private/secret.jpg')->assertNotFound();
    $this->get('/storage/media/derivatives/does-not-exist.webp')->assertNotFound();
});
