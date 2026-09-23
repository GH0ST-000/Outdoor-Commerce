<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Payments\Actions\RetryPaymentWebhookAction;
use Illuminate\Console\Command;

final class RetryPaymentWebhookCommand extends Command
{
    protected $signature = 'payments:retry-webhook {publicId : Webhook public ID}';

    protected $description = 'Reprocess a previously verified payment webhook by public ID.';

    public function handle(RetryPaymentWebhookAction $action): int
    {
        $id = (string) $this->argument('publicId');
        $action->execute($id);
        $this->info('Webhook retry queued for '.$id);

        return self::SUCCESS;
    }
}
