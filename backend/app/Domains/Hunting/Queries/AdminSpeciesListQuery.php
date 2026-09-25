<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Queries;

use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\SpeciesLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminSpeciesListQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Species>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Species::query()->with(['translations']);

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (isset($filters['publication_status']) && is_string($filters['publication_status']) && $filters['publication_status'] !== '') {
            $query->where('publication_status', $filters['publication_status']);
        }
        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('scientific_name', 'like', $term)
                    ->orWhere('canonical_slug', 'like', $term)
                    ->orWhereHas('translations', static fn ($translations) => $translations->where('common_name', 'like', $term));
            });
        }

        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 10)));

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function listItem(Species $species): array
    {
        $ka = $species->translation(SpeciesLocales::default());

        return [
            'id' => $species->public_id,
            'slug' => $species->canonical_slug,
            'scientific_name' => $species->scientific_name,
            'common_name' => $ka?->common_name,
            'publication_status' => $species->publication_status->value,
            'verification_status' => $species->verification_status->value,
            'activity_type' => $species->activity_type->value,
            'domain_type' => $species->domain_type->value,
            'content_version' => $species->content_version,
            'updated_at' => $species->updated_at?->toIso8601String(),
        ];
    }
}
