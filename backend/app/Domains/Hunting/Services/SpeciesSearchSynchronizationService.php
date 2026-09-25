<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Contracts\SearchGateway;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesLogger;
use App\Domains\Hunting\Support\SpeciesSearchIndexNamer;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class SpeciesSearchSynchronizationService
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SpeciesSearchIndexNamer $namer,
        private readonly SpeciesSearchDocumentFactory $documents,
        private readonly SpeciesLogger $logger,
    ) {}

    public function configure(?string $locale = null): void
    {
        foreach (SpeciesLocales::all() as $item) {
            if ($locale !== null && $item !== $locale) {
                continue;
            }
            $uid = $this->namer->uid($item);
            $this->gateway->ensureIndex($uid, 'id', $this->settings($item));
            Cache::forever($this->indexKey($item), $uid);
        }
    }

    public function sync(int $speciesId): void
    {
        $species = Species::withTrashed()->find($speciesId);
        if ($species === null || $species->publication_status !== SpeciesPublicationStatus::Published) {
            $this->remove($speciesId);

            return;
        }

        foreach (SpeciesLocales::all() as $locale) {
            $document = $this->documents->make($species, $locale);
            $uid = $this->uid($locale);
            if ($document === null) {
                $this->gateway->deleteDocuments($uid, [$this->documents->id($species, $locale)]);

                continue;
            }
            $this->gateway->upsertDocuments($uid, [$document]);
        }
    }

    public function remove(int $speciesId): void
    {
        $species = Species::withTrashed()->find($speciesId);
        if ($species === null) {
            return;
        }
        foreach (SpeciesLocales::all() as $locale) {
            $this->gateway->deleteDocuments($this->uid($locale), [$this->documents->id($species, $locale)]);
        }
    }

    /**
     * @return array{built: int, swapped: list<string>}
     */
    public function rebuild(?string $locale = null, int $chunk = 100): array
    {
        $built = 0;
        $swapped = [];

        foreach (SpeciesLocales::all() as $item) {
            if ($locale !== null && $item !== $locale) {
                continue;
            }

            $rebuildUid = $this->namer->rebuildUid($item);
            $this->gateway->ensureIndex($rebuildUid, 'id', $this->settings($item));

            Species::query()->published()->orderBy('id')->chunkById($chunk, function ($rows) use ($item, $rebuildUid, &$built): void {
                $payload = [];
                foreach ($rows as $species) {
                    $document = $this->documents->make($species, $item);
                    if ($document !== null) {
                        $payload[] = $document;
                    }
                }
                if ($payload !== []) {
                    $this->gateway->upsertDocuments($rebuildUid, $payload);
                    $built += count($payload);
                }
            });

            $live = $this->namer->uid($item);
            try {
                $this->gateway->swapIndexes([$rebuildUid, $live]);
                Cache::forever($this->indexKey($item), $live);
                $this->gateway->deleteIndex($rebuildUid);
                $swapped[] = $live;
            } catch (Throwable $exception) {
                $this->logger->error('search_rebuild_failed', [
                    'locale' => $item,
                    'message' => $exception->getMessage(),
                ]);
                throw $exception;
            }
        }

        return ['built' => $built, 'swapped' => $swapped];
    }

    /**
     * @return array{ok: bool, counts: array<string, int>}
     */
    public function verify(): array
    {
        $counts = [];
        $ok = true;
        foreach (SpeciesLocales::all() as $locale) {
            $uid = $this->uid($locale);
            $indexed = $this->gateway->documentCount($uid);
            $expected = Species::query()->published()->count();
            $counts[$locale] = $indexed;
            if ($indexed > $expected) {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'counts' => $counts];
    }

    public function uid(string $locale): string
    {
        $cached = Cache::get($this->indexKey($locale));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->namer->uid($locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(string $locale): array
    {
        return [
            'searchableAttributes' => [
                'common_name',
                'scientific_name',
                'public_aliases',
                'search_aliases',
                'taxonomy_names',
                'summary',
            ],
            'filterableAttributes' => [
                'activity_type',
                'domain_type',
                'taxonomy_codes',
                'habitat_codes',
                'conservation_codes',
                'locale',
            ],
            'sortableAttributes' => [
                'published_timestamp',
                'common_name',
                'scientific_name',
            ],
            'displayedAttributes' => [
                'id',
                'slug',
                'locale',
                'common_name',
                'scientific_name',
                'content_version',
            ],
            'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
            ],
        ];
    }

    private function indexKey(string $locale): string
    {
        return 'search:index:'.$this->namer->prefix().':species:'.$locale;
    }
}
