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
}
