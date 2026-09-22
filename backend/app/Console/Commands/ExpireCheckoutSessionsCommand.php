<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Checkout\Actions\ExpireCheckoutSessionsAction;
use Illuminate\Console\Command;

final class ExpireCheckoutSessionsCommand extends Command
{
    protected $signature = 'checkout:expire-sessions';

    protected $description = 'Expire abandoned checkout sessions past their lifetime.';

    public function handle(ExpireCheckoutSessionsAction $action): int
    {
        $result = $action->execute();
        $this->info(sprintf('Expired %d checkout session(s).', $result['expired']));

        return self::SUCCESS;
    }
}
