<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Spatial;

use App\Domains\Geography\DTOs\PointLookupQueryData;
use App\Domains\Geography\Enums\SpatialDetailLevel;
use App\Domains\Geography\Enums\SpatialZoneType;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Services\SpatialQueryService;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Actions\IssueOutdoorContextTokenAction;
use App\Domains\Legal\DTOs\SpatialEvaluationQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Services\SpatialLegalEvaluator;
use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

final class PublicSpatialController
{
    public function index(Request $request, SpatialQueryService $queries): JsonResponse
    {
        $bbox = $this->bbox($request);
        $detail = SpatialDetailLevel::tryFrom((string) $request->query('detail', 'region')) ?? SpatialDetailLevel::Region;
        $at = $this->moment($request);
        $activity = $this->activity($request);
        $locale = $this->locale($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));

        return $this->ok($request, $queries->viewport(
            $bbox,
            $at,
            $detail,
            $locale,
            $this->types($request),
            $page,
            $perPage,
            $activity,
        ), true);
    }

    public function search(Request $request, SpatialQueryService $queries): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) > 80) {
            throw SpatialException::invalidQuery('Search text is too long.');
        }

        return $this->ok($request, [
            'results' => $queries->search($term, $this->locale($request)),
            'geocoder' => 'unavailable',
            'disclaimer' => (string) config('spatial.disclaimer_key'),
        ], true);
    }

    public function show(Request $request, string $publicId, SpatialQueryService $queries): JsonResponse
    {
        return $this->ok($request, $queries->zoneDetails(
            $publicId,
            $this->moment($request),
            $this->locale($request),
            $request->boolean('geometry', false),
        ), true);
    }

    public function lookup(Request $request, SpatialQueryService $queries): JsonResponse
    {
        $data = $request->validate([
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'at' => ['nullable', 'date'],
            'jurisdiction' => ['nullable', 'string', 'max:16'],
        ]);

        return $this->private($request, [
            'zones' => $queries->lookup(new PointLookupQueryData(
                longitude: (float) $data['lng'],
                latitude: (float) $data['lat'],
                at: isset($data['at']) ? $this->parseMoment((string) $data['at']) : now(),
                jurisdictionCode: $data['jurisdiction'] ?? (string) config('spatial.default_jurisdiction'),
            ), $this->locale($request)),
            'disclaimer' => (string) config('spatial.disclaimer_key'),
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ]);
    }

    public function evaluate(Request $request, SpatialLegalEvaluator $evaluator, IssueOutdoorContextTokenAction $tokens): JsonResponse
    {
        $data = $request->validate([
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'activity' => ['required', 'in:hunting,fishing'],
            'species' => ['nullable', 'integer'],
            'species_slug' => ['nullable', 'string', 'max:160'],
            'at' => ['nullable', 'date'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'availability' => ['nullable', 'in:any_date,entire_period,timeline'],
            'jurisdiction' => ['nullable', 'string', 'max:16'],
        ]);
        $this->assertPeriod($data);
        $occurredAt = isset($data['at']) ? $this->parseMoment((string) $data['at']) : now();
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        if ($from === null && $to === null) {
            $day = $occurredAt->copy()->timezone('Asia/Tbilisi')->toDateString();
            $from = $day;
            $to = $day;
        }

        $payload = $evaluator->evaluate(new SpatialEvaluationQueryData(
            longitude: (float) $data['lng'],
            latitude: (float) $data['lat'],
            occurredAt: $occurredAt,
            activityType: LegalActivityType::from($data['activity']),
            jurisdictionCode: $data['jurisdiction'] ?? (string) config('spatial.default_jurisdiction'),
            speciesId: $this->speciesId($data),
            from: $from,
            to: $to,
            availabilityMode: isset($data['availability'])
                ? AvailabilityMode::from($data['availability'])->value
                : ($from === $to ? AvailabilityMode::AnyDate->value : null),
        ), $this->locale($request));

        $speciesId = $this->speciesId($data);
        $payload['context_token'] = $tokens->fromEvaluation(
            $payload,
            $speciesId,
            isset($data['species_slug']) ? (string) $data['species_slug'] : null,
        );

        return $this->private($request, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function speciesId(array $data): ?int
    {
        $slug = isset($data['species_slug']) ? trim((string) $data['species_slug']) : '';
        if ($slug !== '') {
            $species = Species::query()->published()->where('canonical_slug', $slug)->first();
            if ($species === null) {
                throw SpatialException::invalidQuery('Published species was not found.');
            }

            return (int) $species->id;
        }

        return isset($data['species']) ? (int) $data['species'] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPeriod(array $data): void
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        if ($from === null && $to === null) {
            return;
        }
        if (! is_string($from) || ! is_string($to)) {
            throw SpatialException::invalidQuery('A period requires both from and to dates.');
        }
        $start = Carbon::createFromFormat('Y-m-d', $from);
        $end = Carbon::createFromFormat('Y-m-d', $to);
        if ($start === false || $end === false || $end->lt($start)) {
            throw SpatialException::invalidQuery('The date period is invalid.');
        }
        $max = (int) config('spatial.query.max_period_days', 366);
        if ($start->diffInDays($end) > $max) {
            throw SpatialException::invalidQuery('The requested period is too long.');
        }
    }

    private function bbox(Request $request): BoundingBox
    {
        $parts = array_map('trim', explode(',', (string) $request->query('bbox', '')));
        if (count($parts) !== 4 || in_array('', $parts, true)) {
            throw SpatialException::invalidBoundingBox('bbox must be west,south,east,north.');
        }
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                throw SpatialException::invalidBoundingBox('bbox must be west,south,east,north.');
            }
        }

        return BoundingBox::fromEdges((float) $parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]);
    }

    private function moment(Request $request): Carbon
    {
        if (! $request->filled('at')) {
            return now();
        }

        return $this->parseMoment((string) $request->query('at'));
    }

    private function parseMoment(string $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw SpatialException::invalidQuery('The date is not valid.');
        }
    }

    private function activity(Request $request): ?string
    {
        $activity = (string) $request->query('activity', '');
        if ($activity === '') {
            return null;
        }
        if (! in_array($activity, ['hunting', 'fishing'], true)) {
            throw SpatialException::invalidQuery('Activity must be hunting or fishing.');
        }

        return $activity;
    }

    /**
     * @return list<string>|null
     */
    private function types(Request $request): ?array
    {
        $raw = $request->query('types');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $allowed = array_map(static fn (SpatialZoneType $type): string => $type->value, SpatialZoneType::cases());
        $requested = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $type): bool => $type !== ''));
        $valid = array_values(array_intersect($requested, $allowed));

        return $valid;
    }

    private function locale(Request $request): string
    {
        $locale = strtolower((string) $request->header('X-Locale', 'ka'));

        return in_array($locale, ['ka', 'en'], true) ? $locale : 'ka';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ok(Request $request, array $data, bool $conditional = false): JsonResponse
    {
        $response = $this->envelope($request, $data);
        if ($conditional) {
            $response->setEtag(sha1((string) json_encode($data)));
            $response->setPublic();
            $response->setMaxAge((int) config('spatial.cache.ttl_seconds', 120));
            if ($response->isNotModified($request)) {
                return $response;
            }
        }

        return $response;
    }

    /**
     * Coordinate responses are private and must not be stored by shared caches.
     *
     * @param  array<string, mixed>  $data
     */
    private function private(Request $request, array $data): JsonResponse
    {
        return $this->envelope($request, $data)->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function envelope(Request $request, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                'srid' => 4326,
                'coordinate_order' => 'longitude,latitude',
            ],
        ]);
    }
}
