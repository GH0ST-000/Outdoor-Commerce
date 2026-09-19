/**
 * Stable capability names — must match backend Identity Permission enum.
 */
export const PERMISSIONS = {
  ADMIN_ACCESS: "admin.access",
  USERS_VIEW: "users.view",
  USERS_STATUS_MANAGE: "users.status.manage",
  USERS_ROLES_MANAGE: "users.roles.manage",
  ROLES_VIEW: "roles.view",
  ROLES_MANAGE: "roles.manage",
  AUDIT_LOGS_VIEW: "audit-logs.view",
  CATALOG_VIEW: "catalog.view",
  CATALOG_MANAGE: "catalog.manage",
  CATALOG_PUBLISH: "catalog.publish",
  INVENTORY_VIEW: "inventory.view",
  INVENTORY_MANAGE: "inventory.manage",
  PRICING_VIEW: "pricing.view",
  PRICING_MANAGE: "pricing.manage",
  ORDERS_VIEW: "orders.view",
  ORDERS_MANAGE: "orders.manage",
  LEGAL_RULES_VIEW: "legal-rules.view",
  LEGAL_RULES_MANAGE: "legal-rules.manage",
  LEGAL_RULES_PUBLISH: "legal-rules.publish",
  CONTENT_VIEW: "content.view",
  CONTENT_MANAGE: "content.manage",
  CONTENT_PUBLISH: "content.publish",
  RECOMMENDATIONS_VIEW: "recommendations.view",
  RECOMMENDATIONS_MANAGE: "recommendations.manage",
  OPERATIONS_VIEW: "operations.view",
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];

/** All known permission strings (order matches backend enum). */
export const ALL_PERMISSIONS: readonly Permission[] = [
  PERMISSIONS.ADMIN_ACCESS,
  PERMISSIONS.USERS_VIEW,
  PERMISSIONS.USERS_STATUS_MANAGE,
  PERMISSIONS.USERS_ROLES_MANAGE,
  PERMISSIONS.ROLES_VIEW,
  PERMISSIONS.ROLES_MANAGE,
  PERMISSIONS.AUDIT_LOGS_VIEW,
  PERMISSIONS.CATALOG_VIEW,
  PERMISSIONS.CATALOG_MANAGE,
  PERMISSIONS.CATALOG_PUBLISH,
  PERMISSIONS.INVENTORY_VIEW,
  PERMISSIONS.INVENTORY_MANAGE,
  PERMISSIONS.PRICING_VIEW,
  PERMISSIONS.PRICING_MANAGE,
  PERMISSIONS.ORDERS_VIEW,
  PERMISSIONS.ORDERS_MANAGE,
  PERMISSIONS.LEGAL_RULES_VIEW,
  PERMISSIONS.LEGAL_RULES_MANAGE,
  PERMISSIONS.LEGAL_RULES_PUBLISH,
  PERMISSIONS.CONTENT_VIEW,
  PERMISSIONS.CONTENT_MANAGE,
  PERMISSIONS.CONTENT_PUBLISH,
  PERMISSIONS.RECOMMENDATIONS_VIEW,
  PERMISSIONS.RECOMMENDATIONS_MANAGE,
  PERMISSIONS.OPERATIONS_VIEW,
] as const;

export const KNOWN_PERMISSION_SET: ReadonlySet<string> = new Set(
  ALL_PERMISSIONS,
);

export function isKnownPermission(value: string): value is Permission {
  return KNOWN_PERMISSION_SET.has(value);
}

export const APPROVED_ROLES = [
  "admin",
  "catalog-manager",
  "order-manager",
  "legal-editor",
] as const;

export type ApprovedRole = (typeof APPROVED_ROLES)[number];

export const APPROVED_ROLE_SET: ReadonlySet<string> = new Set(APPROVED_ROLES);

export function isApprovedRole(value: string): value is ApprovedRole {
  return APPROVED_ROLE_SET.has(value);
}
