<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Contracts\SearchGateway;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Queries\PublicSpeciesListQuery;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesLogger;
use Throwable;

final class SpeciesSearchQueryService
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SpeciesSearchSynchronizationService $indexes,
        private readonly PublicSpeciesListQuery $fallback,
        private readonly SpeciesPresenter $presenter,
        private readonly SpeciesLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{cards: list<array<string, mixed>>, total: int, fallback_used: bool, search_mode: string}
     */
    public function search(array $filters): array
    {
        $locale = SpeciesLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : SpeciesLocales::default();
        $query = trim((string) ($filters['q'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(
            (int) config('species.pagination.max', 50),
            max(1, (int) ($filters['per_page'] ?? 10)),
        );

        if ($query === '') {
            $result = $this->fallback->paginate($filters);

            return [
                'cards' => $result['cards'],
                'total' => $result['paginator']->total(),
                'fallback_used' => false,
                'search_mode' => 'browse',
                'paginator' => $result['paginator'],
            ];
        }

        if (! (bool) config('search.enabled', true) || ! $this->gateway->health()) {
            return $this->safeFallback($filters);
        }

        try {
            $filterParts = ['locale = "'.$locale.'"'];
            foreach (['activity_type', 'domain_type'] as $field) {
                if (isset($filters[$field]) && is_string($filters[$field]) && $filters[$field] !== '') {
                    $filterParts[] = $field.' = "'.$filters[$field].'"';
                }
            }
            if (isset($filters['habitat']) && is_string($filters['habitat']) && $filters['habitat'] !== '') {
                $filterParts[] = 'habitat_codes = "'.$filters['habitat'].'"';
            }

            $started = microtime(true);
            $raw = $this->gateway->search($this->indexes->uid($locale), $query, [
                'filter' => $filterParts,
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'attributesToRetrieve' => ['id', 'slug'],
            ]);
            $this->logger->info('search_latency', [
                'locale' => $locale,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            $hits = $raw['hits'] ?? [];
            $slugs = [];
            foreach ($hits as $hit) {
                if (is_array($hit) && isset($hit['slug']) && is_string($hit['slug'])) {
                    $slugs[] = $hit['slug'];
                }
            }

            $species = Species::query()
                ->published()
                ->whereIn('canonical_slug', $slugs)
                ->with([
                    'translations',
                    'habitatLinks.habitat.translations',
                    'mediaAttachments.asset.derivatives',
                    'mediaAttachments.translations',
                ])
                ->get()
                ->keyBy('canonical_slug');

            $cards = [];
            foreach ($slugs as $slug) {
                $row = $species->get($slug);
                if ($row instanceof Species) {
                    $cards[] = $this->presenter->card($row, $locale);
                }
            }

            $total = (int) ($raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? count($cards));

            return [
                'cards' => $cards,
                'total' => $total,
                'fallback_used' => false,
                'search_mode' => 'search',
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (Throwable $exception) {
            $this->logger->warning('search_unavailable', ['message' => $exception->getMessage()]);

            return $this->safeFallback($filters);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function safeFallback(array $filters): array
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $locale = (string) ($filters['locale'] ?? SpeciesLocales::default());

        $query = Species::query()->published()->with([
            'translations',
            'habitatLinks.habitat.translations',
            'mediaAttachments.asset.derivatives',
            'mediaAttachments.translations',
            'aliases',
        ]);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('scientific_name', 'like', $like)
                    ->orWhereHas('translations', static fn ($translations) => $translations
                        ->where('content_status', 'published')
                        ->where('common_name', 'like', $like))
                    ->orWhereHas('aliases', static fn ($aliases) => $aliases
                        ->where('is_searchable', true)
                        ->where('name', 'like', $like));
            });
        }

        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 10)));
        $paginator = $query->orderBy('scientific_name')->paginate($perPage);
        $cards = [];
        foreach ($paginator->items() as $species) {
            $cards[] = $this->presenter->card($species, $locale);
        }

        return [
            'cards' => $cards,
            'total' => $paginator->total(),
            'fallback_used' => true,
            'search_mode' => 'fallback',
            'paginator' => $paginator,
        ];
    }
}
