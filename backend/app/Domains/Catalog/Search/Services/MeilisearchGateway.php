<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Exceptions\SearchUnavailableException;
use GuzzleHttp\Client as GuzzleClient;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Throwable;

final class MeilisearchGateway implements SearchGateway
{
    private ?Client $client = null;

    public function health(): bool
    {
        try {
            $status = $this->client()->health();

            return is_array($status) && ($status['status'] ?? null) === 'available';
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureIndex(string $uid, string $primaryKey, array $settings): void
    {
        if (! $this->indexExists($uid)) {
            try {
                $this->wait($this->client()->createIndex($uid, ['primaryKey' => $primaryKey]));
            } catch (ApiException $exception) {
                if ($exception->getCode() !== 409) {
                    throw SearchUnavailableException::infrastructure('Unable to create search index.');
                }
            }
        }

        $this->wait($this->client()->index($uid)->updateSettings($settings));
    }

    public function upsertDocuments(string $uid, array $documents): void
    {
        if ($documents === []) {
            return;
        }
        if (! $this->indexExists($uid)) {
            try {
                $this->wait($this->client()->createIndex($uid, ['primaryKey' => 'id']));
            } catch (ApiException $exception) {
                if ($exception->getCode() !== 409) {
                    throw SearchUnavailableException::infrastructure('Unable to create search index.');
                }
            }
        }
        $this->wait($this->client()->index($uid)->addDocuments($documents, 'id'));
    }

    public function deleteDocuments(string $uid, array $ids): void
    {
        if ($ids === [] || ! $this->indexExists($uid)) {
            return;
        }
        $this->wait($this->client()->index($uid)->deleteDocuments($ids));
    }

    public function deleteIndex(string $uid): void
    {
        try {
            $this->wait($this->client()->deleteIndex($uid));
        } catch (ApiException $exception) {
            if ($exception->getCode() !== 404) {
                throw SearchUnavailableException::infrastructure('Unable to delete search index.');
            }
        }
    }

    public function search(string $uid, string $query, array $params): array
    {
        try {
            $result = $this->client()->index($uid)->search($query === '' ? null : $query, $params);
        } catch (Throwable $exception) {
            throw SearchUnavailableException::infrastructure('Search request failed.');
        }

        return is_array($result) ? $result : $result->toArray();
    }

    public function swapIndexes(array $pair): void
    {
        $this->wait($this->client()->swapIndexes([$pair]));
    }

    public function documentCount(string $uid): int
    {
        try {
            $stats = $this->client()->index($uid)->stats();

            return (int) ($stats['numberOfDocuments'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    public function documentIds(string $uid): array
    {
        $ids = [];
        $offset = 0;
        $limit = 1000;
        do {
            $page = $this->client()->index($uid)->getDocuments(
                (new DocumentsQuery)->setOffset($offset)->setLimit($limit)->setFields(['id']),
            );
            $hits = $page->getResults();
            foreach ($hits as $hit) {
                if (isset($hit['id'])) {
                    $ids[] = (string) $hit['id'];
                }
            }
            $offset += $limit;
        } while (count($hits) === $limit);

        return $ids;
    }

    public function indexExists(string $uid): bool
    {
        try {
            $this->client()->index($uid)->fetchRawInfo();

            return true;
        } catch (ApiException $exception) {
            return $exception->getCode() === 404 ? false : throw SearchUnavailableException::infrastructure();
        } catch (Throwable) {
            return false;
        }
    }

    private function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $host = rtrim((string) config('search.host'), '/');
        $key = config('search.key');
        $http = new GuzzleClient([
            'timeout' => (float) config('search.request_timeout', 1.5),
            'connect_timeout' => (float) config('search.connect_timeout', 0.4),
            'http_errors' => false,
        ]);

        $this->client = new Client($host, is_string($key) ? $key : null, $http);

        return $this->client;
    }

    /**
     * @param  array<string, mixed>|object  $task
     */
    private function wait(array|object $task): void
    {
        $uid = null;
        if (is_array($task)) {
            $uid = $task['taskUid'] ?? $task['uid'] ?? null;
        } elseif (method_exists($task, 'getTaskUid')) {
            $uid = $task->getTaskUid();
        } elseif (method_exists($task, 'getUid')) {
            $uid = $task->getUid();
        }
        if (! is_int($uid) && ! is_numeric($uid)) {
            return;
        }
        try {
            $result = $this->client()->waitForTask((int) $uid, (int) config('search.task_wait_ms', 60000));
        } catch (CommunicationException) {
            throw SearchUnavailableException::infrastructure('Search task wait failed.');
        }
        $status = $result['status'] ?? null;
        if ($status === 'failed') {
            $code = is_array($result['error'] ?? null) ? (string) ($result['error']['code'] ?? 'task_failed') : 'task_failed';
            throw SearchUnavailableException::infrastructure('Search index task failed ('.$code.').');
        }
    }
}
