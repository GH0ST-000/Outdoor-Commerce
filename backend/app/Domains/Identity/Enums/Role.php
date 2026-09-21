<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Stable administrative role names. Customers have no role by default.
 */
enum Role: string
{
    case Admin = 'admin';

    case CatalogManager = 'catalog-manager';

    case InventoryManager = 'inventory-manager';

    case PricingManager = 'pricing-manager';

    case OrderManager = 'order-manager';

    case LegalEditor = 'legal-editor';

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return self::cases();
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full administrative access across the operations console.',
            self::CatalogManager => 'Manages catalog and recommendation foundations.',
            self::InventoryManager => 'Manages warehouses, stock movements, and inventory reservations.',
            self::PricingManager => 'Manages price lists, schedules, and promotions.',
            self::OrderManager => 'Manages orders and related fulfillment support.',
            self::LegalEditor => 'Manages hunting and legal rule content.',
        };
    }
}
