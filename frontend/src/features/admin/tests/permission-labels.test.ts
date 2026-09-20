import { describe, expect, it } from "vitest";
import {
  ALL_PERMISSIONS,
  APPROVED_ROLES,
} from "@/features/admin/permissions/permissions";
import {
  groupPermissions,
  permissionLabel,
  roleLabel,
} from "@/features/admin/permissions/permission-labels";

describe("permission labels", () => {
  it("gives every known permission a human label", () => {
    for (const permission of ALL_PERMISSIONS) {
      expect(permissionLabel(permission)).not.toBe(permission);
      expect(permissionLabel(permission).length).toBeGreaterThan(3);
    }
  });

  it("gives approved roles readable names", () => {
    expect(roleLabel("admin")).toBe("Administrator");
    expect(roleLabel("catalog-manager")).toBe("Catalog manager");
    for (const role of APPROVED_ROLES) {
      expect(roleLabel(role)).not.toContain(".");
    }
  });

  it("groups permissions under plain-language sections", () => {
    const grouped = groupPermissions([
      "admin.access",
      "catalog.view",
      "catalog.manage",
      "users.view",
    ]);
    expect(grouped.map((group) => group.label)).toEqual([
      "Admin access",
      "People & security",
      "Catalog",
    ]);
    expect(grouped[2]?.permissions.map(permissionLabel)).toEqual([
      "View products",
      "Edit products",
    ]);
  });
});
