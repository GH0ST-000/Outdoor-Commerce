<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Console\Command;
use Throwable;

final class SearchConfigureCommand extends Command
{
    protected $signature = 'search:configure {--locale= : Limit to one locale}';

    protected $description = 'Apply Meilisearch index settings for catalog search';

    public function handle(SearchIndexManager $indexes): int
    {
        $locale = $this->option('locale');
        if (is_string($locale) && $locale !== '' && ! CatalogLocales::isSupported($locale)) {
            $this->error('Unsupported locale.');

            return self::FAILURE;
        }

        try {
            $indexes->configure(is_string($locale) && $locale !== '' ? $locale : null);
        } catch (Throwable $exception) {
            $this->error('Unable to configure search indexes. Check MEILISEARCH_HOST.');

            return self::FAILURE;
        }

        $this->info('Search indexes configured.');
        foreach ($indexes->activeIndexes() as $key => $uid) {
            $this->line(" {$key}: {$uid}");
        }

        return self::SUCCESS;
    }
}
