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

    case LegalSeasonsView = 'legal.seasons.view';
    case LegalSeasonsCreate = 'legal.seasons.create';
    case LegalSeasonsUpdate = 'legal.seasons.update';
    case LegalSeasonsReview = 'legal.seasons.review';
    case LegalSeasonsPublish = 'legal.seasons.publish';
    case LegalSeasonsSupersede = 'legal.seasons.supersede';
    case LegalSeasonsGenerate = 'legal.seasons.generate';
    case LegalSeasonOverridesView = 'legal.season_overrides.view';
    case LegalSeasonOverridesCreate = 'legal.season_overrides.create';
    case LegalSeasonOverridesReview = 'legal.season_overrides.review';
    case LegalSeasonOverridesPublish = 'legal.season_overrides.publish';
    case LegalCalendarPreview = 'legal.calendar.preview';
    case LegalCalendarCoverage = 'legal.calendar.coverage';
    case LegalCalendarGenerationRunsView = 'legal.calendar.generation_runs.view';

    case SpatialSourcesView = 'spatial.sources.view';
    case SpatialSourcesManage = 'spatial.sources.manage';
    case SpatialSourcesVerify = 'spatial.sources.verify';
    case SpatialDatasetsView = 'spatial.datasets.view';
    case SpatialDatasetsManage = 'spatial.datasets.manage';
    case SpatialVersionsUpload = 'spatial.versions.upload';
    case SpatialVersionsValidate = 'spatial.versions.validate';
    case SpatialVersionsImport = 'spatial.versions.import';
    case SpatialVersionsReview = 'spatial.versions.review';
    case SpatialVersionsPublish = 'spatial.versions.publish';
    case SpatialZonesView = 'spatial.zones.view';
    case SpatialZonesManage = 'spatial.zones.manage';
    case SpatialGeometryReview = 'spatial.geometry.review';
    case SpatialGeometryPublish = 'spatial.geometry.publish';
    case SpatialRulesAssign = 'spatial.rules.assign';
    case SpatialConflictsView = 'spatial.conflicts.view';
    case SpatialConflictsResolve = 'spatial.conflicts.resolve';
    case SpatialPreviewEvaluate = 'spatial.preview.evaluate';
    case SpatialAuditView = 'spatial.audit.view';

    case ContentView = 'content.view';
    case ContentManage = 'content.manage';
    case ContentPublish = 'content.publish';

    case RecommendationsView = 'recommendations.view';
    case RecommendationsManage = 'recommendations.manage';
    case RecommendationsSimulate = 'recommendations.simulate';
    case RecommendationsProfilesView = 'recommendations.profiles.view';
    case RecommendationsProfilesManage = 'recommendations.profiles.manage';
    case RecommendationsProfilesReview = 'recommendations.profiles.review';
    case RecommendationsProfilesPublish = 'recommendations.profiles.publish';
    case RecommendationsAssignmentsView = 'recommendations.assignments.view';
    case RecommendationsAssignmentsManage = 'recommendations.assignments.manage';
    case RecommendationsAssignmentsBulkManage = 'recommendations.assignments.bulk_manage';
    case RecommendationsMerchandisingView = 'recommendations.merchandising.view';
    case RecommendationsMerchandisingManage = 'recommendations.merchandising.manage';
    case RecommendationsCoverageView = 'recommendations.coverage.view';
    case RecommendationsAuditView = 'recommendations.audit.view';

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
