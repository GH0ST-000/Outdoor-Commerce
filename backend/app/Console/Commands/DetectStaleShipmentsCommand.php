<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Shipping\Actions\DetectStaleShipmentsAction;
use Illuminate\Console\Command;

final class DetectStaleShipmentsCommand extends Command
{
    protected $signature = 'shipments:detect-stale';

    protected $description = 'Report stale shipments without changing status.';

    public function handle(DetectStaleShipmentsAction $action): int
    {
        $rows = $action->execute();
        $this->info('Stale shipments: '.count($rows));
        foreach ($rows as $row) {
            $this->line($row['number'].' '.$row['status'].' '.$row['reason']);
        }

        return self::SUCCESS;
    }
}
