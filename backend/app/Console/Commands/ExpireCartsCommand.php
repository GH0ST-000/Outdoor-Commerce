<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Cart\Actions\ExpireCartsAction;
use Illuminate\Console\Command;

final class ExpireCartsCommand extends Command
{
    protected $signature = 'carts:expire';

    protected $description = 'Expire inactive guest and authenticated carts past their retention window.';

    public function handle(ExpireCartsAction $action): int
    {
        $result = $action->execute();
        $this->info(sprintf('Expired %d cart(s); skipped %d.', $result['expired'], $result['skipped']));

        return self::SUCCESS;
    }
}
