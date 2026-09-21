<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryOperationType: string
{
    case InitialStock = 'initial_stock';
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case StockCount = 'stock_count';
    case Transfer = 'transfer';
    case Sale = 'sale';
    case Return = 'return';
    case Damage = 'damage';
    case Loss = 'loss';
    case ReservationCommitment = 'reservation_commitment';
    case ReservationRelease = 'reservation_release';
}
