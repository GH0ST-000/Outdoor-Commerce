"use client";

import { useEffect, useMemo, useState } from "react";
import { fetchAdminRoles } from "@/features/admin/api/admin-api";
import { ALL_PERMISSIONS } from "@/features/admin/permissions/permissions";
import {
  groupPermissions,
  permissionLabel,
  permissionSummary,
  roleLabel,
} from "@/features/admin/permissions/permission-labels";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import {
  AdminTable,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import type { AdminRole } from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";

type RolesResult = {
  roles: AdminRole[];
  error: string | null;
};

export function RolesMatrixPage() {
  const [result, setResult] = useState<RolesResult | null>(null);

  useEffect(() => {
    let cancelled = false;

    void fetchAdminRoles()
      .then((next) => {
        if (!cancelled) setResult({ roles: next, error: null });
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setResult({
            roles: [],
            error:
              err instanceof ApiClientError
                ? err.message
                : "Unable to load roles.",
          });
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const loading = result === null;
  const roles = useMemo(() => result?.roles ?? [], [result]);
  const error = result?.error ?? null;

  const permissionColumns = useMemo(() => {
    const fromRoles = new Set<string>();
    for (const role of roles) {
      for (const permission of role.permissions) {
        fromRoles.add(permission);
      }
    }
    const known = ALL_PERMISSIONS.filter((permission) =>
      fromRoles.has(permission),
    );
    const knownSet = new Set<string>(known);
    const extras = [...fromRoles]
      .filter((permission) => !knownSet.has(permission))
      .sort();
    return [...known, ...extras];
  }, [roles]);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Roles"
        description="What each role can do. Assign roles from a user’s profile."
      />

      {loading ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading roles…
        </p>
      ) : null}

      {error ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      {!loading && !error ? (
        <div className="grid gap-4 xl:grid-cols-2">
          {roles.map((role) => {
            const groups = groupPermissions(role.permissions);

            return (
              <AdminPanel key={role.name}>
                <AdminPanelHeader
                  title={roleLabel(role.name)}
                  description={role.description}
                />
                {groups.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    No permissions assigned.
                  </p>
                ) : (
                  <div className="space-y-5">
                    {groups.map((group) => (
                      <div key={group.id}>
                        <p className="mb-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                          {group.label}
                        </p>
                        <ul className="grid gap-2">
                          {group.permissions.map((permission) => (
                            <li
                              key={permission}
                              className="rounded-xl border border-border/60 bg-muted/20 px-3.5 py-2.5"
                            >
                              <p className="text-sm font-medium">
                                {permissionLabel(permission)}
                              </p>
                              <p className="mt-0.5 text-xs text-muted-foreground">
                                {permissionSummary(permission)}
                              </p>
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                )}
              </AdminPanel>
            );
          })}
        </div>
      ) : null}

      {!loading && !error && roles.length > 0 ? (
        <div className="space-y-3">
          <AdminPageHeader
            title="Compare roles"
            description="Checkmarks show what each role can do."
            className="[&_h1]:text-xl"
          />
          <AdminTable>
            <table className={adminTableClassName()}>
              <caption className="sr-only">Role permission comparison</caption>
              <thead>
                <tr>
                  <th className={adminThClassName()}>Capability</th>
                  {roles.map((role) => (
                    <th
                      key={role.name}
                      className={`${adminThClassName()} text-center`}
                    >
                      {roleLabel(role.name)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {permissionColumns.map((permission) => (
                  <tr
                    key={permission}
                    className="transition-colors hover:bg-muted/25"
                  >
                    <td className={adminTdClassName()}>
                      <span className="font-medium">
                        {permissionLabel(permission)}
                      </span>
                    </td>
                    {roles.map((role) => {
                      const granted = role.permissions.includes(permission);
                      return (
                        <td
                          key={`${role.name}-${permission}`}
                          className={`${adminTdClassName()} text-center`}
                        >
                          <span
                            aria-label={granted ? "Allowed" : "Not allowed"}
                            className={
                              granted
                                ? "font-semibold text-emerald-700 dark:text-emerald-300"
                                : "text-muted-foreground/50"
                            }
                          >
                            {granted ? "✓" : "—"}
                          </span>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </AdminTable>
        </div>
      ) : null}
    </div>
  );
}
