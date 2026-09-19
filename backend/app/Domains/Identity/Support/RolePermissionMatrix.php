<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Enums\Role;

/**
 * Canonical role → permission matrix for Day 5.
 *
 * Differences from the product brief:
 * - `content-manager` role is omitted until content workflows need it.
 * - `content.view` is granted to catalog-manager and legal-editor for future copy/legal pages.
 * - `content.manage` / `content.publish` remain admin-only.
 * - `inventory.view` and `pricing.view` are granted to order-manager for order support.
 * - `roles.manage` is assigned to admin only; Day 5 exposes a read-only roles API.
 */
final class RolePermissionMatrix
{
    /**
     * @return array<string, list<Permission>>
     */
    public static function matrix(): array
    {
        return [
            Role::Admin->value => Permission::all(),
            Role::CatalogManager->value => [
                Permission::AdminAccess,
                Permission::CatalogView,
                Permission::CatalogManage,
                Permission::CatalogPublish,
                Permission::InventoryView,
                Permission::InventoryManage,
                Permission::PricingView,
                Permission::PricingManage,
                Permission::ContentView,
                Permission::RecommendationsView,
                Permission::RecommendationsManage,
            ],
            Role::OrderManager->value => [
                Permission::AdminAccess,
                Permission::OrdersView,
                Permission::OrdersManage,
                Permission::InventoryView,
                Permission::PricingView,
            ],
            Role::LegalEditor->value => [
                Permission::AdminAccess,
                Permission::LegalRulesView,
                Permission::LegalRulesManage,
                Permission::LegalRulesPublish,
                Permission::ContentView,
            ],
        ];
    }

    /**
     * @return list<Permission>
     */
    public static function permissionsFor(Role $role): array
    {
        return self::matrix()[$role->value] ?? [];
    }
}
