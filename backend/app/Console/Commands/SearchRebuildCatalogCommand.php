<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Console\Command;
use Throwable;

final class SearchRebuildCatalogCommand extends Command
{
    protected $signature = 'search:rebuild-catalog
        {--locale= : Rebuild one locale}
        {--chunk=200 : Source chunk size}';

    protected $description = 'Rebuild versioned catalog search indexes and swap them into place';

    public function handle(SearchSynchronizationService $sync, SearchIndexManager $indexes): int
    {
        $locale = $this->option('locale');
        if (is_string($locale) && $locale !== '' && ! CatalogLocales::isSupported($locale)) {
            $this->error('Unsupported locale.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $this->info('Configuring live indexes...');
        try {
            $indexes->configure(is_string($locale) && $locale !== '' ? $locale : null);
            $result = $sync->rebuild(is_string($locale) && $locale !== '' ? $locale : null, $chunk);
        } catch (Throwable $exception) {
            $this->error('Search rebuild failed. The previous live index was left in place.');

            return self::FAILURE;
        }

        $this->info('Indexed '.$result['built'].' documents.');
        foreach ($result['swapped'] as $uid) {
            $this->line(' swapped '.$uid);
        }

        return self::SUCCESS;
    }
}
