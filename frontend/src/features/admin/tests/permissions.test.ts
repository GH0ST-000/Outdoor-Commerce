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
      "fulfillment.view",
      "fulfillment.manage",
      "legal-rules.view",
      "legal-rules.manage",
      "legal-rules.publish",
      "legal.sources.view",
      "legal.sources.manage",
      "legal.sources.verify",
      "legal.documents.view",
      "legal.documents.manage",
      "legal.versions.upload",
      "legal.versions.review",
      "legal.provisions.manage",
      "legal.rules.create",
      "legal.rules.update",
      "legal.rules.review",
      "legal.rules.publish",
      "legal.rules.supersede",
      "legal.conflicts.view",
      "legal.conflicts.resolve",
      "legal.audit.view",
      "legal.seasons.view",
      "legal.seasons.create",
      "legal.seasons.update",
      "legal.seasons.review",
      "legal.seasons.publish",
      "legal.seasons.supersede",
      "legal.seasons.generate",
      "legal.season_overrides.view",
      "legal.season_overrides.create",
      "legal.season_overrides.review",
      "legal.season_overrides.publish",
      "legal.calendar.preview",
      "legal.calendar.coverage",
      "legal.calendar.generation_runs.view",
      "spatial.sources.view",
      "spatial.sources.manage",
      "spatial.sources.verify",
      "spatial.datasets.view",
      "spatial.datasets.manage",
      "spatial.versions.upload",
      "spatial.versions.validate",
      "spatial.versions.import",
      "spatial.versions.review",
      "spatial.versions.publish",
      "spatial.zones.view",
      "spatial.zones.manage",
      "spatial.geometry.review",
      "spatial.geometry.publish",
      "spatial.rules.assign",
      "spatial.conflicts.view",
      "spatial.conflicts.resolve",
      "spatial.preview.evaluate",
      "spatial.audit.view",
      "content.view",
      "content.manage",
      "content.publish",
      "recommendations.view",
      "recommendations.manage",
      "recommendations.simulate",
      "recommendations.profiles.view",
      "recommendations.profiles.manage",
      "recommendations.profiles.review",
      "recommendations.profiles.publish",
      "recommendations.assignments.view",
      "recommendations.assignments.manage",
      "recommendations.assignments.bulk_manage",
      "recommendations.merchandising.view",
      "recommendations.merchandising.manage",
      "recommendations.coverage.view",
      "recommendations.audit.view",
      "operations.view",
      "species.view",
      "species.create",
      "species.update",
      "species.delete",
      "species.review",
      "species.publish",
      "species.archive",
      "species.manage_taxonomy",
      "species.manage_aliases",
      "species.manage_sources",
      "species.manage_media",
      "species.view_revisions",
      "species.restore_revision",
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
