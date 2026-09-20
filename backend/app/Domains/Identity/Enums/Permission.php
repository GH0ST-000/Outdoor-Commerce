<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Stable capability names. Prefer authorizing by permission, not role name.
 */
enum Permission: string
{
    case AdminAccess = 'admin.access';

    case UsersView = 'users.view';
    case UsersStatusManage = 'users.status.manage';
    case UsersRolesManage = 'users.roles.manage';

    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';

    case AuditLogsView = 'audit-logs.view';

    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';
    case CatalogPublish = 'catalog.publish';

    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case InventoryAdjust = 'inventory.adjust';
    case InventoryTransfer = 'inventory.transfer';
    case InventoryReservationsManage = 'inventory.reservations.manage';

    case PricingView = 'pricing.view';
    case PricingManage = 'pricing.manage';
    case PricingPublish = 'pricing.publish';
    case PromotionsView = 'promotions.view';
    case PromotionsManage = 'promotions.manage';
    case PromotionsPublish = 'promotions.publish';

    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';

    case LegalRulesView = 'legal-rules.view';
    case LegalRulesManage = 'legal-rules.manage';
    case LegalRulesPublish = 'legal-rules.publish';

    case ContentView = 'content.view';
    case ContentManage = 'content.manage';
    case ContentPublish = 'content.publish';

    case RecommendationsView = 'recommendations.view';
    case RecommendationsManage = 'recommendations.manage';

    case OperationsView = 'operations.view';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
