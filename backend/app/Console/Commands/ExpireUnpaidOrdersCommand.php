<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Orders\Actions\ExpireUnpaidOrdersAction;
use Illuminate\Console\Command;

final class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Expire unpaid pending-payment orders past their payment window and release reservations.';

    public function handle(ExpireUnpaidOrdersAction $action): int
    {
        $result = $action->execute();
        $this->info(sprintf(
            'Expired %d unpaid order(s); skipped %d.',
            $result['expired'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
