import { describe, expect, it } from "vitest";
import {
  hasAllPermissions,
  hasAnyPermission,
  hasPermission,
} from "@/features/admin/permissions/has-permission";
import {
  ALL_PERMISSIONS,
  KNOWN_PERMISSION_SET,
  PERMISSIONS,
} from "@/features/admin/permissions/permissions";

describe("permissions constants", () => {
  it("exposes only known permission strings matching backend names", () => {
    expect(ALL_PERMISSIONS).toEqual([
      "admin.access",
      "users.view",
      "users.status.manage",
      "users.roles.manage",
      "roles.view",
      "roles.manage",
      "audit-logs.view",
      "catalog.view",
      "catalog.manage",
      "catalog.publish",
      "inventory.view",
      "inventory.manage",
      "inventory.adjust",
      "inventory.transfer",
      "inventory.reservations.manage",
      "pricing.view",
      "pricing.manage",
      "pricing.publish",
      "promotions.view",
      "promotions.manage",
      "promotions.publish",
      "orders.view",
      "orders.manage",
      "legal-rules.view",
      "legal-rules.manage",
      "legal-rules.publish",
      "content.view",
      "content.manage",
      "content.publish",
      "recommendations.view",
      "recommendations.manage",
      "operations.view",
    ]);

    for (const permission of Object.values(PERMISSIONS)) {
      expect(KNOWN_PERMISSION_SET.has(permission)).toBe(true);
      expect(ALL_PERMISSIONS).toContain(permission);
    }
  });
});

describe("permission helpers", () => {
  it("denies by default for empty or missing grants", () => {
    expect(hasPermission(null, PERMISSIONS.USERS_VIEW)).toBe(false);
    expect(hasPermission(undefined, PERMISSIONS.USERS_VIEW)).toBe(false);
    expect(hasPermission([], PERMISSIONS.USERS_VIEW)).toBe(false);
    expect(hasAnyPermission([], [PERMISSIONS.USERS_VIEW])).toBe(false);
    expect(hasAllPermissions([], [PERMISSIONS.USERS_VIEW])).toBe(false);
    expect(hasAnyPermission([PERMISSIONS.USERS_VIEW], [])).toBe(false);
    expect(hasAllPermissions([PERMISSIONS.USERS_VIEW], [])).toBe(false);
  });

  it("checks single and combined permissions", () => {
    const grants = [PERMISSIONS.ADMIN_ACCESS, PERMISSIONS.USERS_VIEW];

    expect(hasPermission(grants, PERMISSIONS.USERS_VIEW)).toBe(true);
    expect(hasPermission(grants, PERMISSIONS.ROLES_VIEW)).toBe(false);
    expect(
      hasAnyPermission(grants, [
        PERMISSIONS.ROLES_VIEW,
        PERMISSIONS.USERS_VIEW,
      ]),
    ).toBe(true);
    expect(
      hasAllPermissions(grants, [
        PERMISSIONS.ADMIN_ACCESS,
        PERMISSIONS.USERS_VIEW,
      ]),
    ).toBe(true);
    expect(
      hasAllPermissions(grants, [
        PERMISSIONS.ADMIN_ACCESS,
        PERMISSIONS.ROLES_VIEW,
      ]),
    ).toBe(false);
  });
});
