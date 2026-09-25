<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Hunting\Services\SpeciesSearchSynchronizationService;
use App\Domains\Hunting\Support\SpeciesLocales;
use Illuminate\Console\Command;
use Throwable;

final class SearchRebuildSpeciesCommand extends Command
{
    protected $signature = 'search:rebuild-species
        {--locale= : Rebuild one locale}
        {--chunk=100 : Source chunk size}';

    protected $description = 'Rebuild versioned species search indexes and swap them into place';

    public function handle(SpeciesSearchSynchronizationService $sync): int
    {
        $locale = $this->option('locale');
        if (is_string($locale) && $locale !== '' && ! SpeciesLocales::isSupported($locale)) {
            $this->error('Unsupported locale.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $this->info('Configuring live species indexes...');
        try {
            $sync->configure(is_string($locale) && $locale !== '' ? $locale : null);
            $result = $sync->rebuild(is_string($locale) && $locale !== '' ? $locale : null, $chunk);
        } catch (Throwable) {
            $this->error('Species search rebuild failed. The previous live index was left in place.');

            return self::FAILURE;
        }

        $this->info('Indexed '.$result['built'].' documents.');
        foreach ($result['swapped'] as $uid) {
            $this->line(' swapped '.$uid);
        }

        return self::SUCCESS;
    }
}
