<?php

declare(strict_types=1);

it('boots the application and serves the web root', function (): void {
    $response = $this->get('/');

    $response->assertOk();
});
