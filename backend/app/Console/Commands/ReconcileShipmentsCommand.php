<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Shipping\Actions\ReconcileShipmentsAction;
use Illuminate\Console\Command;

final class ReconcileShipmentsCommand extends Command
{
    protected $signature = 'shipments:reconcile {--shipment= : Shipment public ID} {--provider= : Provider code}';

    protected $description = 'Reconcile non-terminal provider-backed shipments. Skips the manual provider.';

    public function handle(ReconcileShipmentsAction $action): int
    {
        $shipment = $this->option('shipment');
        $provider = $this->option('provider');
        $result = $action->execute(
            is_string($shipment) && $shipment !== '' ? $shipment : null,
            is_string($provider) && $provider !== '' ? $provider : null,
        );
        $this->info(sprintf(
            'Processed %d shipment(s); skipped %d, failed %d.',
            $result['processed'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
