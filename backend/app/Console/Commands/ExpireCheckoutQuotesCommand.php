<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Checkout\Actions\ExpireCheckoutQuotesAction;
use Illuminate\Console\Command;

final class ExpireCheckoutQuotesCommand extends Command
{
    protected $signature = 'checkout:expire-quotes';

    protected $description = 'Expire active checkout quotes past their TTL and release reserved stock.';

    public function handle(ExpireCheckoutQuotesAction $action): int
    {
        $result = $action->execute();
        $this->info(sprintf('Expired %d checkout quote(s).', $result['expired']));

        return self::SUCCESS;
    }
}
