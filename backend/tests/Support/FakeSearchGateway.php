<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Exceptions\SearchUnavailableException;

final class FakeSearchGateway implements SearchGateway
{
    /** @var array<string, array<string, array<string, mixed>>> */
    public array $indexes = [];

    public bool $healthy = true;

    public bool $failSearch = false;

    public int $searchCalls = 0;

    public function health(): bool
    {
        return $this->healthy;
    }

    public function ensureIndex(string $uid, string $primaryKey, array $settings): void
    {
        $this->indexes[$uid] ??= [];
    }

    public function upsertDocuments(string $uid, array $documents): void
    {
        $this->indexes[$uid] ??= [];
        foreach ($documents as $document) {
            $id = (string) ($document['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $this->indexes[$uid][$id] = $document;
        }
    }

    public function deleteDocuments(string $uid, array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->indexes[$uid][$id]);
        }
    }

    public function deleteIndex(string $uid): void
    {
        unset($this->indexes[$uid]);
    }

    public function search(string $uid, string $query, array $params): array
    {
        $this->searchCalls++;
        if ($this->failSearch || ! $this->healthy) {
            throw SearchUnavailableException::infrastructure();
        }

        $documents = array_values($this->indexes[$uid] ?? []);
        $needle = mb_strtolower($query, 'UTF-8');
        $matched = [];
        foreach ($documents as $document) {
            if ($needle !== '' && ! $this->matchesQuery($document, $needle)) {
                continue;
            }
            if (! $this->matchesFilters($document, $params['filter'] ?? [])) {
                continue;
            }
            $matched[] = $document;
        }

        usort($matched, function (array $left, array $right) use ($needle, $params): int {
            $sort = $params['sort'][0] ?? null;
            if (is_string($sort) && str_contains($sort, ':')) {
                [$field, $direction] = explode(':', $sort, 2);
                $cmp = ($left[$field] ?? 0) <=> ($right[$field] ?? 0);

                return $direction === 'desc' ? -$cmp : $cmp;
            }
            $leftScore = $this->score($left, $needle);
            $rightScore = $this->score($right, $needle);

            return $rightScore <=> $leftScore;
        });

        $seen = [];
        $distinct = [];
        foreach ($matched as $document) {
            $productId = (int) ($document['product_id'] ?? 0);
            $key = $productId > 0 ? 'p'.$productId : (string) ($document['id'] ?? spl_object_id((object) $document));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $distinct[] = $document;
        }

        $offset = (int) ($params['offset'] ?? 0);
        $limit = (int) ($params['limit'] ?? 10);
        $page = array_slice($distinct, $offset, $limit);

        $facets = [];
        foreach (($params['facets'] ?? []) as $facet) {
            $counts = [];
            foreach ($distinct as $document) {
                $value = $document[$facet] ?? null;
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $label = is_scalar($item) ? (string) $item : '';
                        if ($label === '') {
                            continue;
                        }
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }

                    continue;
                }
                if ($value === null || $value === '') {
                    continue;
                }
                $label = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
            $facets[$facet] = $counts;
        }

        $hits = array_map(function (array $document) use ($params): array {
            $fields = $params['attributesToRetrieve'] ?? null;
            if (! is_array($fields) || $fields === []) {
                return $document;
            }
            $hit = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $document)) {
                    $hit[$field] = $document[$field];
                }
            }
            $hit['_rankingScore'] = 1.0;

            return $hit;
        }, $page);

        return [
            'hits' => $hits,
            'estimatedTotalHits' => count($distinct),
            'facetDistribution' => $facets,
        ];
    }

    public function swapIndexes(array $pair): void
    {
        $left = $this->indexes[$pair[0]] ?? [];
        $right = $this->indexes[$pair[1]] ?? [];
        $this->indexes[$pair[0]] = $right;
        $this->indexes[$pair[1]] = $left;
    }

    public function documentCount(string $uid): int
    {
        return count($this->indexes[$uid] ?? []);
    }

    public function documentIds(string $uid): array
    {
        return array_keys($this->indexes[$uid] ?? []);
    }

    public function indexExists(string $uid): bool
    {
        return array_key_exists($uid, $this->indexes);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function matchesQuery(array $document, string $needle): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($document['name'] ?? ''),
            (string) ($document['searchable_name'] ?? ''),
            (string) ($document['sku'] ?? ''),
            (string) ($document['brand_name'] ?? ''),
            (string) ($document['category_name'] ?? ''),
            (string) ($document['slug'] ?? ''),
            is_array($document['search_aliases'] ?? null) ? implode(' ', $document['search_aliases']) : '',
        ], fn (string $value): bool => $value !== '')), 'UTF-8');

        return str_contains($haystack, $needle);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>|string  $filters
     */
    private function matchesFilters(array $document, array|string $filters): bool
    {
        $parts = is_array($filters) ? $filters : [$filters];
        foreach ($parts as $part) {
            if (! is_string($part) || $part === '') {
                continue;
            }
            if (! $this->matchesFilterPart($document, $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function matchesFilterPart(array $document, string $part): bool
    {
        if (preg_match('/^([a-z_]+) = "(.*)"$/u', $part, $match) === 1) {
            return (string) ($document[$match[1]] ?? '') === stripcslashes($match[2]);
        }
        if (preg_match('/^([a-z_]+) = (true|false)$/', $part, $match) === 1) {
            $expected = $match[2] === 'true';

            return (bool) ($document[$match[1]] ?? false) === $expected;
        }
        if (preg_match('/^([a-z_]+) >= (\d+)$/', $part, $match) === 1) {
            return (int) ($document[$match[1]] ?? 0) >= (int) $match[2];
        }
        if (preg_match('/^([a-z_]+) <= (\d+)$/', $part, $match) === 1) {
            return (int) ($document[$match[1]] ?? 0) <= (int) $match[2];
        }
        if (preg_match('/^([a-z_]+) IN \[([^\]]*)\]$/', $part, $match) === 1) {
            $field = $match[1];
            $raw = array_filter(array_map('trim', explode(',', $match[2])));
            $wanted = array_map(function (string $value): string {
                $value = trim($value, '"');

                return stripcslashes($value);
            }, $raw);
            $actual = $document[$field] ?? null;
            if (is_array($actual)) {
                foreach ($actual as $item) {
                    if (in_array((string) $item, $wanted, true)) {
                        return true;
                    }
                }

                return false;
            }

            return in_array((string) $actual, $wanted, true);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function score(array $document, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }
        $sku = mb_strtolower((string) ($document['sku'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string) ($document['name'] ?? ''), 'UTF-8');
        if ($sku === $needle) {
            return 100;
        }
        if (str_starts_with($name, $needle)) {
            return 80;
        }
        if (str_contains($name, $needle)) {
            return 60;
        }

        return 10;
    }
}
