<?php

declare(strict_types=1);

namespace App\Domains\Operations\Enums;

enum AuditEvent: string
{
    case AdministratorCreated = 'administrator_created';
    case AdministratorPromoted = 'administrator_promoted';
    case UserStatusChanged = 'user_status_changed';
    case UserRolesChanged = 'user_roles_changed';
    case AdminAccessDenied = 'admin_access_denied';
    case PermissionConfigurationSynchronized = 'permission_configuration_synchronized';
    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';
    case ProductStatusChanged = 'product.status_changed';
    case ProductArchived = 'product.archived';
    case ProductRestored = 'product.restored';

    case AttributeCreated = 'attribute.created';
    case AttributeUpdated = 'attribute.updated';
    case AttributeStatusChanged = 'attribute.status_changed';
    case AttributeArchived = 'attribute.archived';
    case AttributeRestored = 'attribute.restored';

    case AttributeValueCreated = 'attribute_value.created';
    case AttributeValueUpdated = 'attribute_value.updated';
    case AttributeValueStatusChanged = 'attribute_value.status_changed';
    case AttributeValueArchived = 'attribute_value.archived';
    case AttributeValueRestored = 'attribute_value.restored';

    case ProductVariantAxesUpdated = 'product.variant_axes_updated';

    case ProductVariantCreated = 'product_variant.created';
    case ProductVariantUpdated = 'product_variant.updated';
    case ProductVariantStatusChanged = 'product_variant.status_changed';
    case ProductVariantDefaultChanged = 'product_variant.default_changed';
    case ProductVariantArchived = 'product_variant.archived';
    case ProductVariantRestored = 'product_variant.restored';

    case ProductVariantsGenerated = 'product_variants.generated';

    case MediaUploaded = 'media.uploaded';
    case MediaProcessingCompleted = 'media.processing_completed';
    case MediaProcessingFailed = 'media.processing_failed';
    case MediaRetryRequested = 'media.retry_requested';
    case MediaMetadataUpdated = 'media.metadata_updated';
    case MediaReordered = 'media.reordered';
    case MediaPrimaryChanged = 'media.primary_changed';
    case MediaAttachmentRemoved = 'media.attachment_removed';
    case MediaOrphanDeleted = 'media.orphan_deleted';

    case WarehouseCreated = 'warehouse.created';
    case WarehouseUpdated = 'warehouse.updated';
    case WarehouseStatusChanged = 'warehouse.status_changed';
    case WarehouseDefaultChanged = 'warehouse.default_changed';
    case WarehouseArchived = 'warehouse.archived';
    case WarehouseRestored = 'warehouse.restored';

    case InventoryReceived = 'inventory.received';
    case InventoryAdjusted = 'inventory.adjusted';
    case InventoryStockCountReconciled = 'inventory.stock_count_reconciled';
    case InventoryTransferred = 'inventory.transferred';
    case InventorySettingsUpdated = 'inventory.settings_updated';

    case InventoryReservationCreated = 'inventory.reservation_created';
    case InventoryReservationReleased = 'inventory.reservation_released';
    case InventoryReservationExpired = 'inventory.reservation_expired';
    case InventoryReservationCommitted = 'inventory.reservation_committed';
    case InventoryReservationCancelled = 'inventory.reservation_cancelled';

    case PriceListCreated = 'price_list.created';
    case PriceListUpdated = 'price_list.updated';
    case PriceListStatusChanged = 'price_list.status_changed';
    case PriceListDefaultChanged = 'price_list.default_changed';
    case PriceListArchived = 'price_list.archived';
    case PriceListRestored = 'price_list.restored';

    case PricePeriodCreated = 'price_period.created';
    case PricePeriodUpdated = 'price_period.updated';
    case PricePeriodPublished = 'price_period.published';
    case PricePeriodCancelled = 'price_period.cancelled';
    case PricePeriodSuperseded = 'price_period.superseded';

    case PromotionCreated = 'promotion.created';
    case PromotionUpdated = 'promotion.updated';
    case PromotionTargetsUpdated = 'promotion.targets_updated';
    case PromotionActivated = 'promotion.activated';
    case PromotionPaused = 'promotion.paused';
    case PromotionArchived = 'promotion.archived';
    case PromotionRestored = 'promotion.restored';

    case SearchConfigured = 'search.configured';
    case SearchRebuilt = 'search.rebuilt';
    case SearchProductSynced = 'search.product_synced';
    case SearchProductRemoved = 'search.product_removed';
    case SearchVerified = 'search.verified';

    case OrderCreated = 'order.created';
    case OrderCancelled = 'order.cancelled';
    case OrderExpired = 'order.expired';

    case ShipmentCreated = 'shipment.created';
    case ShipmentPreparationStarted = 'shipment.preparation_started';
    case ShipmentPicked = 'shipment.picked';
    case ShipmentPacked = 'shipment.packed';
    case ShipmentReadyForDispatch = 'shipment.ready_for_dispatch';
    case ShipmentDispatched = 'shipment.dispatched';
    case ShipmentInTransit = 'shipment.in_transit';
    case ShipmentOutForDelivery = 'shipment.out_for_delivery';
    case ShipmentDelivered = 'shipment.delivered';
    case ShipmentCollected = 'shipment.collected';
    case ShipmentExceptionRecorded = 'shipment.exception_recorded';
    case ShipmentCancelled = 'shipment.cancelled';
    case ShipmentReadyForPickup = 'shipment.ready_for_pickup';
    case ShipmentDeliveryAttemptFailed = 'shipment.delivery_attempt_failed';

    case SpeciesCreated = 'species.created';
    case SpeciesUpdated = 'species.updated';
    case SpeciesSubmittedReview = 'species.submitted_review';
    case SpeciesPublished = 'species.published';
    case SpeciesUnpublished = 'species.unpublished';
    case SpeciesArchived = 'species.archived';
    case SpeciesReturnedToDraft = 'species.returned_to_draft';
    case SpeciesRevisionRestored = 'species.revision_restored';

    case LegalSourceCreated = 'legal.source.created';
    case LegalSourceUpdated = 'legal.source.updated';
    case LegalSourceSubmittedReview = 'legal.source.submitted_review';
    case LegalSourceVerified = 'legal.source.verified';
    case LegalSourceRejected = 'legal.source.rejected';
    case LegalDocumentCreated = 'legal.document.created';
    case LegalDocumentUpdated = 'legal.document.updated';
    case LegalVersionCreated = 'legal.version.created';
    case LegalVersionSubmittedReview = 'legal.version.submitted_review';
    case LegalVersionApproved = 'legal.version.approved';
    case LegalVersionRejected = 'legal.version.rejected';
    case LegalProvisionCreated = 'legal.provision.created';
    case LegalProvisionUpdated = 'legal.provision.updated';
    case LegalProvisionDeleted = 'legal.provision.deleted';
    case LegalRuleCreated = 'legal.rule.created';
    case LegalRuleUpdated = 'legal.rule.updated';
    case LegalRuleSubmittedReview = 'legal.rule.submitted_review';
    case LegalRuleApproved = 'legal.rule.approved';
    case LegalRulePublished = 'legal.rule.published';
    case LegalRuleRejected = 'legal.rule.rejected';
    case LegalRuleSuperseded = 'legal.rule.superseded';
    case LegalChangeDetected = 'legal.change.detected';
    case LegalChangeConfirmed = 'legal.change.confirmed';
    case LegalChangeDismissed = 'legal.change.dismissed';
    case LegalConflictResolved = 'legal.conflict.resolved';
    case LegalSeasonCreated = 'legal.season.created';
    case LegalSeasonUpdated = 'legal.season.updated';
    case LegalSeasonSubmittedReview = 'legal.season.submitted_review';
    case LegalSeasonApproved = 'legal.season.approved';
    case LegalSeasonPublished = 'legal.season.published';
    case LegalSeasonRejected = 'legal.season.rejected';
    case LegalSeasonSuperseded = 'legal.season.superseded';
    case LegalSeasonOverrideCreated = 'legal.season_override.created';
    case LegalSeasonOverrideUpdated = 'legal.season_override.updated';
    case LegalSeasonOverrideSubmittedReview = 'legal.season_override.submitted_review';
    case LegalSeasonOverrideApproved = 'legal.season_override.approved';
    case LegalSeasonOverridePublished = 'legal.season_override.published';
    case LegalSeasonOverrideRejected = 'legal.season_override.rejected';

    case SpatialSourceCreated = 'spatial.source.created';
    case SpatialSourceUpdated = 'spatial.source.updated';
    case SpatialSourceVerified = 'spatial.source.verified';
    case SpatialSourceRejected = 'spatial.source.rejected';
    case SpatialDatasetCreated = 'spatial.dataset.created';
    case SpatialDatasetUpdated = 'spatial.dataset.updated';
    case SpatialDatasetVersionUploaded = 'spatial.dataset_version.uploaded';
    case SpatialPropertyMappingSaved = 'spatial.dataset_version.mapped';
    case SpatialImportStarted = 'spatial.import.started';
    case SpatialDatasetVersionSubmittedReview = 'spatial.dataset_version.submitted_review';
    case SpatialDatasetVersionApproved = 'spatial.dataset_version.approved';
    case SpatialDatasetVersionRejected = 'spatial.dataset_version.rejected';
    case SpatialDatasetVersionPublished = 'spatial.dataset_version.published';
    case SpatialRuleAssigned = 'spatial.rule.assigned';
    case SpatialSourceFileDownloaded = 'spatial.source_file.downloaded';

    case RecommendationTaxonomySaved = 'recommendation.taxonomy.saved';
    case RecommendationAssignmentSaved = 'recommendation.assignment.saved';
    case RecommendationAssignmentRemoved = 'recommendation.assignment.removed';
    case RecommendationBulkAssignmentCompleted = 'recommendation.assignment.bulk_completed';
    case RecommendationProfileCreated = 'recommendation.profile.created';
    case RecommendationProfileUpdated = 'recommendation.profile.updated';
    case RecommendationProfileSubmitted = 'recommendation.profile.submitted';
    case RecommendationProfileApproved = 'recommendation.profile.approved';
    case RecommendationProfilePublished = 'recommendation.profile.published';
    case RecommendationProfileSuperseded = 'recommendation.profile.superseded';
    case RecommendationMerchandisingSaved = 'recommendation.merchandising.saved';
    case RecommendationSimulated = 'recommendation.simulated';
    case RecommendationReindexed = 'recommendation.reindexed';
}
