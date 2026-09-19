<?php

declare(strict_types=1);

it('returns a healthy backend status payload', function (): void {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'service' => 'backend',
        ]);
});
