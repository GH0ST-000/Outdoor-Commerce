<?php

declare(strict_types=1);

it('returns a healthy backend status payload', function (): void {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('service', 'backend')
        ->assertJsonStructure([
            'search' => ['status', 'enabled', 'reachable'],
        ]);

    expect($response->json('search'))->not->toHaveKey('indexes');
    expect($response->getContent())->not->toContain('masterKey');
});
