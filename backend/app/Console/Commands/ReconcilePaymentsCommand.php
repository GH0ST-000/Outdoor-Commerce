<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Payments\Actions\ReconcilePaymentsAction;
use Illuminate\Console\Command;

final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile {--attempt= : Payment attempt public ID} {--provider= : Provider code such as bog}';

    protected $description = 'Reconcile non-terminal payment attempts with their providers.';

    public function handle(ReconcilePaymentsAction $action): int
    {
        $attempt = $this->option('attempt');
        $provider = $this->option('provider');
        $result = $action->execute(
            is_string($attempt) && $attempt !== '' ? $attempt : null,
            is_string($provider) && $provider !== '' ? $provider : null,
        );
        $this->info(sprintf(
            'Processed %d payment attempt(s); succeeded %d, failed %d, skipped %d.',
            $result['processed'],
            $result['succeeded'],
            $result['failed'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
