import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { hasPermission } from "@/features/admin/permissions/has-permission";

export type AdminNavSectionId =
  | "overview"
  | "catalog"
  | "inventory"
  | "pricing"
  | "access";

export type AdminNavItem = {
  id: string;
  label: string;
  href: string;
  /** Permission required to show this item. Dashboard uses admin.access. */
  permission: string;
  section: AdminNavSectionId;
};

export const ADMIN_NAV_SECTIONS: readonly {
  id: AdminNavSectionId;
  label: string;
}[] = [
  { id: "overview", label: "Overview" },
  { id: "catalog", label: "Catalog" },
  { id: "inventory", label: "Inventory" },
  { id: "pricing", label: "Pricing" },
  { id: "access", label: "Access & security" },
] as const;

/**
 * Finished admin modules only. Orders/legal stay hidden until their UI lands.
 */
export const ADMIN_NAV_ITEMS: readonly AdminNavItem[] = [
  {
    id: "dashboard",
    label: "Dashboard",
    href: "/admin",
    permission: PERMISSIONS.ADMIN_ACCESS,
    section: "overview",
  },
  {
    id: "products",
    label: "Products",
    href: "/admin/catalog/products",
    permission: PERMISSIONS.CATALOG_VIEW,
    section: "catalog",
  },
  {
    id: "attributes",
    label: "Attributes",
    href: "/admin/catalog/attributes",
    permission: PERMISSIONS.CATALOG_VIEW,
    section: "catalog",
  },
  {
    id: "inventory",
    label: "Stock",
    href: "/admin/inventory",
    permission: PERMISSIONS.INVENTORY_VIEW,
    section: "inventory",
  },
  {
    id: "warehouses",
    label: "Warehouses",
    href: "/admin/inventory/warehouses",
    permission: PERMISSIONS.INVENTORY_VIEW,
    section: "inventory",
  },
  {
    id: "reservations",
    label: "Reservations",
    href: "/admin/inventory/reservations",
    permission: PERMISSIONS.INVENTORY_VIEW,
    section: "inventory",
  },
  {
    id: "price-lists",
    label: "Price lists",
    href: "/admin/pricing/price-lists",
    permission: PERMISSIONS.PRICING_VIEW,
    section: "pricing",
  },
  {
    id: "prices",
    label: "Prices",
    href: "/admin/pricing/prices",
    permission: PERMISSIONS.PRICING_VIEW,
    section: "pricing",
  },
  {
    id: "promotions",
    label: "Promotions",
    href: "/admin/pricing/promotions",
    permission: PERMISSIONS.PROMOTIONS_VIEW,
    section: "pricing",
  },
  {
    id: "users",
    label: "Users",
    href: "/admin/users",
    permission: PERMISSIONS.USERS_VIEW,
    section: "access",
  },
  {
    id: "roles",
    label: "Roles",
    href: "/admin/roles",
    permission: PERMISSIONS.ROLES_VIEW,
    section: "access",
  },
  {
    id: "audit-logs",
    label: "Audit Logs",
    href: "/admin/audit-logs",
    permission: PERMISSIONS.AUDIT_LOGS_VIEW,
    section: "access",
  },
] as const;

export function filterAdminNav(
  permissions: readonly string[] | null | undefined,
): AdminNavItem[] {
  return ADMIN_NAV_ITEMS.filter((item) =>
    hasPermission(permissions, item.permission),
  );
}

export function groupAdminNav(
  items: readonly AdminNavItem[],
): { id: AdminNavSectionId; label: string; items: AdminNavItem[] }[] {
  return ADMIN_NAV_SECTIONS.map((section) => ({
    ...section,
    items: items.filter((item) => item.section === section.id),
  })).filter((section) => section.items.length > 0);
}

export function isAdminNavActive(pathname: string, href: string): boolean {
  if (href === "/admin") {
    return pathname === "/admin";
  }
  // Stock list/detail must not mark active for warehouses/reservations sub-routes.
  if (href === "/admin/inventory") {
    if (pathname === "/admin/inventory") {
      return true;
    }
    return /^\/admin\/inventory\/\d+\/\d+/.test(pathname);
  }
  return pathname === href || pathname.startsWith(`${href}/`);
}
