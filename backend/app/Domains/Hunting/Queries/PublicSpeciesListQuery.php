<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Queries;

use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Services\SpeciesPresenter;
use App\Domains\Hunting\Support\SpeciesLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PublicSpeciesListQuery
{
    public function __construct(private readonly SpeciesPresenter $presenter) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator<int, Species>, cards: list<array<string, mixed>>}
     */
    public function paginate(array $filters): array
    {
        $locale = SpeciesLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : SpeciesLocales::default();

        $query = Species::query()
            ->published()
            ->with([
                'translations',
                'habitatLinks.habitat.translations',
                'mediaAttachments.asset.derivatives',
                'mediaAttachments.translations',
            ])
            ->whereHas('translations', function ($translations) use ($locale): void {
                $translations->where('content_status', 'published')
                    ->where(function ($inner) use ($locale): void {
                        $inner->where('locale', $locale);
                        if ($locale !== SpeciesLocales::fallback() && (bool) config('species.english_fallback.enabled', true)) {
                            $inner->orWhere('locale', SpeciesLocales::fallback());
                        }
                    });
            });

        if (isset($filters['activity_type']) && is_string($filters['activity_type']) && $filters['activity_type'] !== '') {
            $query->where('activity_type', $filters['activity_type']);
        }
        if (isset($filters['domain_type']) && is_string($filters['domain_type']) && $filters['domain_type'] !== '') {
            $query->where('domain_type', $filters['domain_type']);
        }
        if (isset($filters['taxonomy']) && is_string($filters['taxonomy']) && $filters['taxonomy'] !== '') {
            $value = $filters['taxonomy'];
            $query->where(function ($inner) use ($value): void {
                $inner->where('kingdom', $value)
                    ->orWhere('class_name', $value)
                    ->orWhere('order_name', $value)
                    ->orWhere('family', $value)
                    ->orWhere('genus', $value);
            });
        }
        if (isset($filters['habitat']) && is_string($filters['habitat']) && $filters['habitat'] !== '') {
            $code = $filters['habitat'];
            $query->whereHas('habitatLinks.habitat', static fn ($habitats) => $habitats->where('code', $code));
        }
        if (isset($filters['conservation_status']) && is_string($filters['conservation_status']) && $filters['conservation_status'] !== '') {
            $code = $filters['conservation_status'];
            $query->whereHas('conservationAssessments', static fn ($rows) => $rows->where('status_code', $code));
        }

        $sort = (string) ($filters['sort'] ?? 'name');
        match ($sort) {
            'newest' => $query->orderByDesc('published_at'),
            'scientific' => $query->orderBy('scientific_name'),
            default => $query->orderBy('scientific_name'),
        };

        $perPage = min(
            (int) config('species.pagination.max', 50),
            max(1, (int) ($filters['per_page'] ?? config('species.pagination.default', 10))),
        );

        /** @var LengthAwarePaginator<int, Species> $paginator */
        $paginator = $query->paginate($perPage);
        $cards = [];
        foreach ($paginator->items() as $species) {
            $cards[] = $this->presenter->card($species, $locale);
        }

        return ['paginator' => $paginator, 'cards' => $cards];
    }
}
