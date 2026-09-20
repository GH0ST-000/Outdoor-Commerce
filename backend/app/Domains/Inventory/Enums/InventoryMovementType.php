<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Receipt = 'receipt';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case StockCountIn = 'stock_count_in';
    case StockCountOut = 'stock_count_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Sale = 'sale';
    case Return = 'return';
    case Damage = 'damage';
    case Loss = 'loss';
}
