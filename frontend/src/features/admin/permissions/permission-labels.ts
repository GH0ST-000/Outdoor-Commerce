import {
  ALL_PERMISSIONS,
  APPROVED_ROLES,
  type ApprovedRole,
  type Permission,
} from "@/features/admin/permissions/permissions";

export type PermissionGroupId =
  | "access"
  | "people"
  | "catalog"
  | "inventory"
  | "commerce"
  | "content"
  | "operations";

export const PERMISSION_GROUPS: readonly {
  id: PermissionGroupId;
  label: string;
}[] = [
  { id: "access", label: "Admin access" },
  { id: "people", label: "People & security" },
  { id: "catalog", label: "Catalog" },
  { id: "inventory", label: "Inventory" },
  { id: "commerce", label: "Orders & pricing" },
  { id: "content", label: "Content & legal" },
  { id: "operations", label: "Operations" },
] as const;

const PERMISSION_META: Record<
  Permission,
  { label: string; group: PermissionGroupId; summary: string }
> = {
  "admin.access": {
    label: "Open operations console",
    group: "access",
    summary: "Sign in to the admin area",
  },
  "users.view": {
    label: "View users",
    group: "people",
    summary: "See the user directory",
  },
  "users.status.manage": {
    label: "Activate or suspend users",
    group: "people",
    summary: "Change account status",
  },
  "users.roles.manage": {
    label: "Assign user roles",
    group: "people",
    summary: "Change which roles a user has",
  },
  "roles.view": {
    label: "View roles",
    group: "people",
    summary: "See role definitions",
  },
  "roles.manage": {
    label: "Manage roles",
    group: "people",
    summary: "Change role settings",
  },
  "audit-logs.view": {
    label: "View audit logs",
    group: "people",
    summary: "See security and change history",
  },
  "catalog.view": {
    label: "View products",
    group: "catalog",
    summary: "Browse the product catalog",
  },
  "catalog.manage": {
    label: "Edit products",
    group: "catalog",
    summary: "Create and update products",
  },
  "catalog.publish": {
    label: "Publish products",
    group: "catalog",
    summary: "Make products visible to shoppers",
  },
  "inventory.view": {
    label: "View inventory",
    group: "inventory",
    summary: "See warehouses, balances, ledger, and reservations",
  },
  "inventory.manage": {
    label: "Manage warehouses",
    group: "inventory",
    summary: "Create warehouses and update safety stock settings",
  },
  "inventory.adjust": {
    label: "Adjust stock",
    group: "inventory",
    summary: "Receive stock, adjust quantities, and reconcile counts",
  },
  "inventory.transfer": {
    label: "Transfer stock",
    group: "inventory",
    summary: "Move stock between warehouses",
  },
  "inventory.reservations.manage": {
    label: "Manage reservations",
    group: "inventory",
    summary: "Release or cancel active stock reservations",
  },
  "pricing.view": {
    label: "View pricing",
    group: "commerce",
    summary: "See price lists, schedules, and price history",
  },
  "pricing.manage": {
    label: "Manage pricing drafts",
    group: "commerce",
    summary: "Create and edit draft price lists and price periods",
  },
  "pricing.publish": {
    label: "Publish prices",
    group: "commerce",
    summary: "Publish or cancel prices and change active default lists",
  },
  "promotions.view": {
    label: "View promotions",
    group: "commerce",
    summary: "See promotions and price previews",
  },
  "promotions.manage": {
    label: "Manage promotion drafts",
    group: "commerce",
    summary: "Create and edit draft promotions and targets",
  },
  "promotions.publish": {
    label: "Publish promotions",
    group: "commerce",
    summary: "Activate, pause, or archive promotions",
  },
  "orders.view": {
    label: "View orders",
    group: "commerce",
    summary: "See customer orders",
  },
  "orders.manage": {
    label: "Manage orders",
    group: "commerce",
    summary: "Update and fulfill orders",
  },
  "fulfillment.view": {
    label: "View fulfillment",
    group: "commerce",
    summary: "See shipments and pickup progress",
  },
  "fulfillment.manage": {
    label: "Manage fulfillment",
    group: "commerce",
    summary: "Create shipments and update delivery status",
  },
  "legal-rules.view": {
    label: "View hunting rules",
    group: "content",
    summary: "Read legal rule content",
  },
  "legal-rules.manage": {
    label: "Edit hunting rules",
    group: "content",
    summary: "Create and update legal rules",
  },
  "legal-rules.publish": {
    label: "Publish hunting rules",
    group: "content",
    summary: "Make legal rules public",
  },
  "legal.sources.view": {
    label: "View legal sources",
    group: "content",
    summary: "Read official legal source records",
  },
  "legal.sources.manage": {
    label: "Manage legal sources",
    group: "content",
    summary: "Create and edit official sources",
  },
  "legal.sources.verify": {
    label: "Verify legal sources",
    group: "content",
    summary: "Mark official sources as verified",
  },
  "legal.documents.view": {
    label: "View legal documents",
    group: "content",
    summary: "Read versioned legal documents",
  },
  "legal.documents.manage": {
    label: "Manage legal documents",
    group: "content",
    summary: "Create and edit legal documents",
  },
  "legal.versions.upload": {
    label: "Upload legal versions",
    group: "content",
    summary: "Store official document files",
  },
  "legal.versions.review": {
    label: "Review legal versions",
    group: "content",
    summary: "Approve or reject document versions",
  },
  "legal.provisions.manage": {
    label: "Manage legal provisions",
    group: "content",
    summary: "Record articles and clauses",
  },
  "legal.rules.create": {
    label: "Create legal rules",
    group: "content",
    summary: "Draft structured legal rules",
  },
  "legal.rules.update": {
    label: "Update legal rules",
    group: "content",
    summary: "Edit unpublished legal rules",
  },
  "legal.rules.review": {
    label: "Review legal rules",
    group: "content",
    summary: "Submit, approve, or reject rules",
  },
  "legal.rules.publish": {
    label: "Publish structured legal rules",
    group: "content",
    summary: "Publish source-backed rules",
  },
  "legal.rules.supersede": {
    label: "Supersede legal rules",
    group: "content",
    summary: "Replace published rules without deleting history",
  },
  "legal.conflicts.view": {
    label: "View legal conflicts",
    group: "content",
    summary: "See overlapping rule conflicts",
  },
  "legal.conflicts.resolve": {
    label: "Resolve legal conflicts",
    group: "content",
    summary: "Record human conflict decisions",
  },
  "legal.audit.view": {
    label: "View legal audit events",
    group: "content",
    summary: "Read legal workflow history",
  },
  "content.view": {
    label: "View content",
    group: "content",
    summary: "Read site content drafts",
  },
  "content.manage": {
    label: "Edit content",
    group: "content",
    summary: "Create and update content",
  },
  "content.publish": {
    label: "Publish content",
    group: "content",
    summary: "Make content public",
  },
  "recommendations.view": {
    label: "View recommendations",
    group: "catalog",
    summary: "See product recommendation settings",
  },
  "recommendations.manage": {
    label: "Manage recommendations",
    group: "catalog",
    summary: "Change recommendation settings",
  },
  "operations.view": {
    label: "View operations overview",
    group: "operations",
    summary: "See operational dashboards",
  },
  "species.view": {
    label: "View species",
    group: "content",
    summary: "Browse the species knowledge base",
  },
  "species.create": {
    label: "Create species",
    group: "content",
    summary: "Add species drafts",
  },
  "species.update": {
    label: "Edit species",
    group: "content",
    summary: "Update biological species records",
  },
  "species.delete": {
    label: "Delete species drafts",
    group: "content",
    summary: "Remove unpublished species",
  },
  "species.review": {
    label: "Submit species for review",
    group: "content",
    summary: "Move species into editorial review",
  },
  "species.publish": {
    label: "Publish species",
    group: "content",
    summary: "Make species pages public",
  },
  "species.archive": {
    label: "Archive species",
    group: "content",
    summary: "Remove species from public view",
  },
  "species.manage_taxonomy": {
    label: "Manage species taxonomy",
    group: "content",
    summary: "Edit scientific classification",
  },
  "species.manage_aliases": {
    label: "Manage species aliases",
    group: "content",
    summary: "Add synonyms and regional names",
  },
  "species.manage_sources": {
    label: "Manage species sources",
    group: "content",
    summary: "Attach citations and provenance",
  },
  "species.manage_media": {
    label: "Manage species media",
    group: "content",
    summary: "Upload identification images",
  },
  "species.view_revisions": {
    label: "View species revisions",
    group: "content",
    summary: "See editorial history",
  },
  "species.restore_revision": {
    label: "Restore species revisions",
    group: "content",
    summary: "Restore a previous editorial snapshot",
  },
};

const ROLE_LABELS: Record<ApprovedRole, string> = {
  admin: "Administrator",
  "catalog-manager": "Catalog manager",
  "inventory-manager": "Inventory manager",
  "pricing-manager": "Pricing manager",
  "order-manager": "Order manager",
  "legal-editor": "Legal editor",
};

export function permissionLabel(permission: string): string {
  if (permission in PERMISSION_META) {
    return PERMISSION_META[permission as Permission].label;
  }
  return permission;
}

export function permissionSummary(permission: string): string {
  if (permission in PERMISSION_META) {
    return PERMISSION_META[permission as Permission].summary;
  }
  return permission;
}

export function permissionGroupId(
  permission: string,
): PermissionGroupId | null {
  if (permission in PERMISSION_META) {
    return PERMISSION_META[permission as Permission].group;
  }
  return null;
}

export function roleLabel(role: string): string {
  if (role in ROLE_LABELS) {
    return ROLE_LABELS[role as ApprovedRole];
  }
  return role
    .split("-")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function groupPermissions(
  permissions: readonly string[],
): { id: PermissionGroupId | "other"; label: string; permissions: string[] }[] {
  const set = new Set(permissions);
  const groups = PERMISSION_GROUPS.map((group) => ({
    id: group.id as PermissionGroupId | "other",
    label: group.label,
    permissions: ALL_PERMISSIONS.filter(
      (permission) =>
        set.has(permission) && PERMISSION_META[permission].group === group.id,
    ) as string[],
  })).filter((group) => group.permissions.length > 0);

  const known = new Set(ALL_PERMISSIONS as readonly string[]);
  const other = permissions.filter((permission) => !known.has(permission));
  if (other.length > 0) {
    groups.push({
      id: "other",
      label: "Other",
      permissions: [...other].sort(),
    });
  }

  return groups;
}

/** Sanity: every approved role has a label. */
export const APPROVED_ROLE_LABELS = APPROVED_ROLES.map((role) => ({
  role,
  label: ROLE_LABELS[role],
}));
