<?php

declare(strict_types=1);

use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('generates a correlation id when the header is missing', function (): void {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    $response->assertHeader(CorrelationId::HEADER);

    $id = $response->headers->get(CorrelationId::HEADER);

    expect($id)->toBeString()
        ->and(CorrelationId::isValidFormat((string) $id))->toBeTrue()
        ->and($this->app['request']->attributes->get(CorrelationId::REQUEST_ATTRIBUTE))->toBe($id);
});

it('preserves a valid incoming uuid correlation id', function (): void {
    $incoming = (string) Str::uuid();

    $response = $this->withHeader(CorrelationId::HEADER, $incoming)
        ->getJson('/api/health');

    $response->assertOk();
    $response->assertHeader(CorrelationId::HEADER, strtolower($incoming));
    expect($this->app['request']->attributes->get(CorrelationId::REQUEST_ATTRIBUTE))
        ->toBe(strtolower($incoming));
});

it('replaces an invalid correlation id', function (): void {
    $response = $this->withHeader(CorrelationId::HEADER, 'not-a-uuid')
        ->getJson('/api/health');

    $response->assertOk();

    $id = $response->headers->get(CorrelationId::HEADER);

    expect($id)->not->toBe('not-a-uuid')
        ->and(CorrelationId::isValidFormat((string) $id))->toBeTrue();
});

it('rejects an excessively long correlation id and replaces it', function (): void {
    $tooLong = str_repeat('a', 200);

    $response = $this->withHeader(CorrelationId::HEADER, $tooLong)
        ->getJson('/api/health');

    $response->assertOk();

    $id = $response->headers->get(CorrelationId::HEADER);

    expect($id)->not->toBe($tooLong)
        ->and(strlen((string) $id))->toBeLessThanOrEqual(64)
        ->and(CorrelationId::isValidFormat((string) $id))->toBeTrue();
});

it('always returns x-request-id on api responses', function (): void {
    $response = $this->getJson('/api/health');

    $response->assertHeader(CorrelationId::HEADER);
    expect(CorrelationId::isValidFormat((string) $response->headers->get(CorrelationId::HEADER)))->toBeTrue();
});

it('keeps the day 1 health payload unchanged', function (): void {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'service' => 'backend',
            'search' => [
                'status' => 'ok',
                'enabled' => true,
                'reachable' => true,
            ],
        ]);
});

it('shares the correlation id with the logging context', function (): void {
    $incoming = (string) Str::uuid();

    $this->withHeader(CorrelationId::HEADER, $incoming)
        ->getJson('/api/health')
        ->assertOk();

    $shared = Log::sharedContext();

    expect($shared)->toHaveKey('request_id')
        ->and($shared['request_id'])->toBe(strtolower($incoming));
});
