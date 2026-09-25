<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Contracts;

/**
 * Public search-gateway contract. Meilisearch remains a Catalog implementation detail.
 */
interface SearchGateway
{
    public function health(): bool;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function ensureIndex(string $uid, string $primaryKey, array $settings): void;

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public function upsertDocuments(string $uid, array $documents): void;

    /**
     * @param  list<string>  $ids
     */
    public function deleteDocuments(string $uid, array $ids): void;

    public function deleteIndex(string $uid): void;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function search(string $uid, string $query, array $params): array;

    /**
     * @param  array{0: string, 1: string}  $pair
     */
    public function swapIndexes(array $pair): void;

    public function documentCount(string $uid): int;

    /**
     * @return list<string>
     */
    public function documentIds(string $uid): array;

    public function indexExists(string $uid): bool;
}
