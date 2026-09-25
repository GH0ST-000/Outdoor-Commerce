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
    case FulfillmentView = 'fulfillment.view';
    case FulfillmentManage = 'fulfillment.manage';

    case LegalRulesView = 'legal-rules.view';
    case LegalRulesManage = 'legal-rules.manage';
    case LegalRulesPublish = 'legal-rules.publish';

    case LegalSourcesView = 'legal.sources.view';
    case LegalSourcesManage = 'legal.sources.manage';
    case LegalSourcesVerify = 'legal.sources.verify';
    case LegalDocumentsView = 'legal.documents.view';
    case LegalDocumentsManage = 'legal.documents.manage';
    case LegalVersionsUpload = 'legal.versions.upload';
    case LegalVersionsReview = 'legal.versions.review';
    case LegalProvisionsManage = 'legal.provisions.manage';
    case LegalRulesCreate = 'legal.rules.create';
    case LegalRulesUpdate = 'legal.rules.update';
    case LegalRulesReview = 'legal.rules.review';
    case LegalRulesPublishAction = 'legal.rules.publish';
    case LegalRulesSupersede = 'legal.rules.supersede';
    case LegalConflictsView = 'legal.conflicts.view';
    case LegalConflictsResolve = 'legal.conflicts.resolve';
    case LegalAuditView = 'legal.audit.view';

    case ContentView = 'content.view';
    case ContentManage = 'content.manage';
    case ContentPublish = 'content.publish';

    case RecommendationsView = 'recommendations.view';
    case RecommendationsManage = 'recommendations.manage';

    case OperationsView = 'operations.view';

    case SpeciesView = 'species.view';
    case SpeciesCreate = 'species.create';
    case SpeciesUpdate = 'species.update';
    case SpeciesDelete = 'species.delete';
    case SpeciesReview = 'species.review';
    case SpeciesPublish = 'species.publish';
    case SpeciesArchive = 'species.archive';
    case SpeciesManageTaxonomy = 'species.manage_taxonomy';
    case SpeciesManageAliases = 'species.manage_aliases';
    case SpeciesManageSources = 'species.manage_sources';
    case SpeciesManageMedia = 'species.manage_media';
    case SpeciesViewRevisions = 'species.view_revisions';
    case SpeciesRestoreRevision = 'species.restore_revision';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
