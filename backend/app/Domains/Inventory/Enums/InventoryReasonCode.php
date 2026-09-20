<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryReasonCode: string
{
    case InitialCount = 'initial_count';
    case SupplierReceipt = 'supplier_receipt';
    case ManualCorrection = 'manual_correction';
    case StockCountCorrection = 'stock_count_correction';
    case CustomerReturn = 'customer_return';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case ExpiredGoods = 'expired_goods';
    case Transfer = 'transfer';
    case OrderSale = 'order_sale';
    case Other = 'other';

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }
}
