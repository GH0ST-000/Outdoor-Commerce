<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Support\SearchIndexNamer;
use Illuminate\Support\Facades\Cache;

final class SearchIndexManager
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SearchIndexNamer $namer,
        private readonly SearchIndexSettings $settings,
    ) {}

    public function configure(?string $locale = null): void
    {
        foreach ($this->namer->locales() as $item) {
            if ($locale !== null && $item !== $locale) {
                continue;
            }
            foreach (SearchIndexType::cases() as $type) {
                $uid = $this->namer->uid($type, $item);
                $this->gateway->ensureIndex($uid, 'id', $this->settings->for($type, $item));
                $this->remember($type, $item, $uid);
            }
        }
    }

    public function uid(SearchIndexType $type, string $locale): string
    {
        $cached = Cache::get($this->cacheKey($type, $locale));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->namer->uid($type, $locale);
    }

    public function remember(SearchIndexType $type, string $locale, string $uid): void
    {
        Cache::forever($this->cacheKey($type, $locale), $uid);
    }

    /**
     * @return array<string, string>
     */
    public function activeIndexes(): array
    {
        $map = [];
        foreach ($this->namer->locales() as $locale) {
            foreach (SearchIndexType::cases() as $type) {
                $map[$type->value.'_'.$locale] = $this->uid($type, $locale);
            }
        }

        return $map;
    }

    private function cacheKey(SearchIndexType $type, string $locale): string
    {
        return 'search:index:'.$this->namer->prefix().':'.$type->value.':'.$locale;
    }
}
