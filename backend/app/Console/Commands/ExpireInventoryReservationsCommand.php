<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Inventory\Actions\ExpireInventoryReservationsAction;
use Illuminate\Console\Command;

final class ExpireInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:expire-reservations';

    protected $description = 'Expire active inventory reservations past their TTL';

    public function handle(ExpireInventoryReservationsAction $action): int
    {
        $count = $action->execute();
        $this->info("Expired {$count} reservation(s).");

        return self::SUCCESS;
    }
}
