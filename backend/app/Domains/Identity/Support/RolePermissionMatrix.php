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
 * - `inventory.reservations.manage` is granted to order-manager for checkout support.
 * - `catalog-manager` gets `inventory.view` only (stock mutations require inventory-manager).
 * - `catalog-manager` gets pricing/promotion view + draft manage; publish requires pricing-manager.
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
                Permission::PricingView,
                Permission::PricingManage,
                Permission::PromotionsView,
                Permission::PromotionsManage,
                Permission::ContentView,
                Permission::RecommendationsView,
                Permission::RecommendationsManage,
                Permission::SpeciesView,
            ],
            Role::InventoryManager->value => [
                Permission::AdminAccess,
                Permission::InventoryView,
                Permission::InventoryManage,
                Permission::InventoryAdjust,
                Permission::InventoryTransfer,
                Permission::InventoryReservationsManage,
                Permission::CatalogView,
                Permission::PricingView,
            ],
            Role::PricingManager->value => [
                Permission::AdminAccess,
                Permission::PricingView,
                Permission::PricingManage,
                Permission::PricingPublish,
                Permission::PromotionsView,
                Permission::PromotionsManage,
                Permission::PromotionsPublish,
                Permission::CatalogView,
            ],
            Role::OrderManager->value => [
                Permission::AdminAccess,
                Permission::OrdersView,
                Permission::OrdersManage,
                Permission::FulfillmentView,
                Permission::FulfillmentManage,
                Permission::InventoryView,
                Permission::InventoryReservationsManage,
                Permission::PricingView,
                Permission::PromotionsView,
            ],
            Role::LegalEditor->value => [
                Permission::AdminAccess,
                Permission::LegalRulesView,
                Permission::LegalRulesManage,
                Permission::LegalRulesPublish,
                Permission::LegalSourcesView,
                Permission::LegalSourcesManage,
                Permission::LegalSourcesVerify,
                Permission::LegalDocumentsView,
                Permission::LegalDocumentsManage,
                Permission::LegalVersionsUpload,
                Permission::LegalVersionsReview,
                Permission::LegalProvisionsManage,
                Permission::LegalRulesCreate,
                Permission::LegalRulesUpdate,
                Permission::LegalRulesReview,
                Permission::LegalRulesPublishAction,
                Permission::LegalConflictsView,
                Permission::LegalAuditView,
                Permission::ContentView,
                Permission::SpeciesView,
                Permission::SpeciesCreate,
                Permission::SpeciesUpdate,
                Permission::SpeciesDelete,
                Permission::SpeciesReview,
                Permission::SpeciesPublish,
                Permission::SpeciesArchive,
                Permission::SpeciesManageTaxonomy,
                Permission::SpeciesManageAliases,
                Permission::SpeciesManageSources,
                Permission::SpeciesManageMedia,
                Permission::SpeciesViewRevisions,
                Permission::SpeciesRestoreRevision,
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
