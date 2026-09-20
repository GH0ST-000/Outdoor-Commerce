<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class VerifyInventoryBalancesCommand extends Command
{
    protected $signature = 'inventory:verify-balances {--repair : Rebuild projections from ledger and reservations}';

    protected $description = 'Verify inventory balance projections against ledger and reservations';

    public function handle(): int
    {
        $mismatches = 0;

        InventoryBalance::query()->orderBy('id')->chunkById(200, function ($balances) use (&$mismatches): void {
            foreach ($balances as $balance) {
                $ledgerOnHand = (int) InventoryLedgerEntry::query()
                    ->where('warehouse_id', $balance->warehouse_id)
                    ->where('product_variant_id', $balance->product_variant_id)
                    ->sum('quantity_delta');

                $activeReserved = (int) InventoryReservation::query()
                    ->where('warehouse_id', $balance->warehouse_id)
                    ->where('product_variant_id', $balance->product_variant_id)
                    ->where('status', InventoryReservationStatus::Active->value)
                    ->sum('quantity');

                if ($ledgerOnHand !== $balance->on_hand || $activeReserved !== $balance->reserved) {
                    $mismatches++;
                    $this->warn(sprintf(
                        'Mismatch balance #%d (warehouse %d, variant %d): on_hand expected %d got %d; reserved expected %d got %d',
                        $balance->id,
                        $balance->warehouse_id,
                        $balance->product_variant_id,
                        $ledgerOnHand,
                        $balance->on_hand,
                        $activeReserved,
                        $balance->reserved,
                    ));

                    if ($this->option('repair')) {
                        DB::transaction(function () use ($balance, $ledgerOnHand, $activeReserved): void {
                            $locked = InventoryBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
                            $locked->on_hand = $ledgerOnHand;
                            $locked->reserved = $activeReserved;
                            $locked->version = $locked->version + 1;
                            $locked->save();
                        });
                        $this->info('  Repaired.');
                    }
                }
            }
        });

        if ($mismatches === 0) {
            $this->info('All inventory balances match ledger and reservations.');

            return self::SUCCESS;
        }

        $this->error("Found {$mismatches} mismatch(es).");

        return self::FAILURE;
    }
}
