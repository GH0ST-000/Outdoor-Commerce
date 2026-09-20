import { describe, expect, it } from "vitest";
import {
  ADMIN_NAV_ITEMS,
  filterAdminNav,
  groupAdminNav,
  isAdminNavActive,
} from "@/features/admin/navigation/admin-nav";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";

describe("admin navigation", () => {
  it("includes finished modules including inventory", () => {
    expect(ADMIN_NAV_ITEMS.map((item) => item.id)).toEqual([
      "dashboard",
      "products",
      "attributes",
      "inventory",
      "warehouses",
      "reservations",
      "price-lists",
      "prices",
      "promotions",
      "users",
      "roles",
      "audit-logs",
    ]);
    expect(
      ADMIN_NAV_ITEMS.find((item) => item.id === "products")?.section,
    ).toBe("catalog");
    expect(ADMIN_NAV_ITEMS.find((item) => item.id === "inventory")?.href).toBe(
      "/admin/inventory",
    );
    expect(
      ADMIN_NAV_ITEMS.find((item) => item.id === "warehouses")?.permission,
    ).toBe(PERMISSIONS.INVENTORY_VIEW);
  });

  it("filters nav items by permissions and denies by default", () => {
    expect(filterAdminNav(null)).toEqual([]);
    expect(filterAdminNav([])).toEqual([]);

    const adminOnly = filterAdminNav([PERMISSIONS.ADMIN_ACCESS]);
    expect(adminOnly.map((item) => item.id)).toEqual(["dashboard"]);

    const withUsers = filterAdminNav([
      PERMISSIONS.ADMIN_ACCESS,
      PERMISSIONS.USERS_VIEW,
    ]);
    expect(withUsers.map((item) => item.id)).toEqual(["dashboard", "users"]);

    const withCatalog = filterAdminNav([
      PERMISSIONS.ADMIN_ACCESS,
      PERMISSIONS.CATALOG_VIEW,
    ]);
    expect(withCatalog.map((item) => item.id)).toEqual([
      "dashboard",
      "products",
      "attributes",
    ]);

    const withInventory = filterAdminNav([
      PERMISSIONS.ADMIN_ACCESS,
      PERMISSIONS.INVENTORY_VIEW,
    ]);
    expect(withInventory.map((item) => item.id)).toEqual([
      "dashboard",
      "inventory",
      "warehouses",
      "reservations",
    ]);

    const withPricing = filterAdminNav([
      PERMISSIONS.ADMIN_ACCESS,
      PERMISSIONS.PRICING_VIEW,
      PERMISSIONS.PROMOTIONS_VIEW,
    ]);
    expect(withPricing.map((item) => item.id)).toEqual([
      "dashboard",
      "price-lists",
      "prices",
      "promotions",
    ]);

    const full = filterAdminNav([
      PERMISSIONS.ADMIN_ACCESS,
      PERMISSIONS.USERS_VIEW,
      PERMISSIONS.ROLES_VIEW,
      PERMISSIONS.AUDIT_LOGS_VIEW,
      PERMISSIONS.CATALOG_VIEW,
      PERMISSIONS.INVENTORY_VIEW,
      PERMISSIONS.PRICING_VIEW,
      PERMISSIONS.PROMOTIONS_VIEW,
    ]);
    expect(full.map((item) => item.id)).toEqual([
      "dashboard",
      "products",
      "attributes",
      "inventory",
      "warehouses",
      "reservations",
      "price-lists",
      "prices",
      "promotions",
      "users",
      "roles",
      "audit-logs",
    ]);
  });

  it("groups filtered items into labeled sections", () => {
    const grouped = groupAdminNav(
      filterAdminNav([
        PERMISSIONS.ADMIN_ACCESS,
        PERMISSIONS.CATALOG_VIEW,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.USERS_VIEW,
      ]),
    );
    expect(grouped.map((section) => section.id)).toEqual([
      "overview",
      "catalog",
      "inventory",
      "access",
    ]);

    const withPricingGrouped = groupAdminNav(
      filterAdminNav([
        PERMISSIONS.ADMIN_ACCESS,
        PERMISSIONS.PRICING_VIEW,
        PERMISSIONS.PROMOTIONS_VIEW,
      ]),
    );
    expect(withPricingGrouped.map((section) => section.id)).toEqual([
      "overview",
      "pricing",
    ]);
    expect(grouped[1]?.items.map((item) => item.id)).toEqual([
      "products",
      "attributes",
    ]);
    expect(grouped[2]?.items.map((item) => item.id)).toEqual([
      "inventory",
      "warehouses",
      "reservations",
    ]);
  });

  it("matches active paths without treating nested routes as dashboard", () => {
    expect(isAdminNavActive("/admin", "/admin")).toBe(true);
    expect(isAdminNavActive("/admin/users", "/admin")).toBe(false);
    expect(isAdminNavActive("/admin/users/3", "/admin/users")).toBe(true);
    expect(
      isAdminNavActive(
        "/admin/catalog/products/new",
        "/admin/catalog/products",
      ),
    ).toBe(true);
    expect(
      isAdminNavActive(
        "/admin/catalog/attributes/4/edit",
        "/admin/catalog/attributes",
      ),
    ).toBe(true);
    expect(
      isAdminNavActive("/admin/catalog/attributes", "/admin/catalog/products"),
    ).toBe(false);
    expect(
      isAdminNavActive("/admin/inventory/warehouses", "/admin/inventory"),
    ).toBe(false);
    expect(
      isAdminNavActive("/admin/inventory/1/2", "/admin/inventory"),
    ).toBe(true);
    expect(
      isAdminNavActive(
        "/admin/inventory/warehouses/new",
        "/admin/inventory/warehouses",
      ),
    ).toBe(true);
  });
});
