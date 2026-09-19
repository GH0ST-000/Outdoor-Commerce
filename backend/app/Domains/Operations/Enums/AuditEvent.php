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
}
