import type { Permission } from "@/features/admin/permissions/permissions";

/**
 * Deny by default: missing/empty grants never pass.
 */
export function hasPermission(
  granted: readonly string[] | null | undefined,
  permission: Permission | string,
): boolean {
  if (!granted || granted.length === 0 || !permission) {
    return false;
  }
  return granted.includes(permission);
}

export function hasAnyPermission(
  granted: readonly string[] | null | undefined,
  permissions: readonly (Permission | string)[],
): boolean {
  if (!granted || granted.length === 0 || permissions.length === 0) {
    return false;
  }
  return permissions.some((permission) => granted.includes(permission));
}

export function hasAllPermissions(
  granted: readonly string[] | null | undefined,
  permissions: readonly (Permission | string)[],
): boolean {
  if (!granted || granted.length === 0 || permissions.length === 0) {
    return false;
  }
  return permissions.every((permission) => granted.includes(permission));
}
