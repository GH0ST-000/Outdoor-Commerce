"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  fetchAdminUser,
  updateUserRoles,
  updateUserStatus,
} from "@/features/admin/api/admin-api";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import {
  APPROVED_ROLES,
  PERMISSIONS,
} from "@/features/admin/permissions/permissions";
import { roleLabel } from "@/features/admin/permissions/permission-labels";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
import type { AdminUser } from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";

type UserResult = {
  key: string;
  user: AdminUser | null;
  error: string | null;
};

export function UserDetailPage({ userId }: { userId: string }) {
  const { permissions, refresh, context } = useAdminContext();
  const canManageStatus = hasPermission(
    permissions,
    PERMISSIONS.USERS_STATUS_MANAGE,
  );
  const canManageRoles = hasPermission(
    permissions,
    PERMISSIONS.USERS_ROLES_MANAGE,
  );

  const [result, setResult] = useState<UserResult | null>(null);
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
  const [pending, setPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    void fetchAdminUser(userId)
      .then((next) => {
        if (cancelled) return;
        setResult({ key: userId, user: next, error: null });
        setSelectedRoles(next.roles);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: userId,
          user: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load user.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [userId]);

  const loading = result?.key !== userId;
  const user = result?.key === userId ? result.user : null;
  const loadError = result?.key === userId ? result.error : null;

  async function onStatusChange(status: "active" | "disabled") {
    if (!user || pending) return;
    const confirmed = window.confirm(
      status === "disabled"
        ? `Disable ${user.email}? They will lose protected access.`
        : `Re-activate ${user.email}?`,
    );
    if (!confirmed) return;

    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      const updated = await updateUserStatus(user.id, status);
      setResult({ key: userId, user: updated, error: null });
      setSelectedRoles(updated.roles);
      setNotice(`Status updated to ${updated.status}.`);
      if (context?.user.id === updated.id) {
        await refresh();
      }
    } catch (err) {
      if (
        err instanceof ApiClientError &&
        err.code === "LAST_ACTIVE_ADMIN_REQUIRED"
      ) {
        setActionError(
          "Cannot change status: at least one active administrator must remain.",
        );
      } else {
        setActionError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to update status.",
        );
      }
    } finally {
      setPending(false);
    }
  }

  async function onSaveRoles() {
    if (!user || pending) return;
    const confirmed = window.confirm(
      `Update roles for ${user.email} to: ${
        selectedRoles.length > 0 ? selectedRoles.join(", ") : "(none)"
      }?`,
    );
    if (!confirmed) return;

    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      const updated = await updateUserRoles(user.id, selectedRoles);
      setResult({ key: userId, user: updated, error: null });
      setSelectedRoles(updated.roles);
      setNotice("Roles updated.");
      if (context?.user.id === updated.id) {
        await refresh();
      }
    } catch (err) {
      if (
        err instanceof ApiClientError &&
        err.code === "LAST_ACTIVE_ADMIN_REQUIRED"
      ) {
        setActionError("Cannot remove the last active administrator role.");
      } else if (err instanceof ApiClientError && err.code === "INVALID_ROLE") {
        setActionError("One or more roles are invalid.");
      } else {
        setActionError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to update roles.",
        );
      }
    } finally {
      setPending(false);
    }
  }

  function toggleRole(role: string) {
    setSelectedRoles((current) =>
      current.includes(role)
        ? current.filter((item) => item !== role)
        : [...current, role],
    );
  }

  if (loading) {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        Loading user…
      </p>
    );
  }

  if (!user) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-destructive" role="alert">
          {loadError ?? "User not found."}
        </p>
        <Link
          href="/admin/users"
          className="text-sm font-medium text-primary underline-offset-4 hover:underline"
        >
          Back to users
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <Link
          href="/admin/users"
          className="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground no-underline hover:text-foreground"
        >
          ← Users
        </Link>
        <AdminPageHeader
          className="mt-3"
          title={`${user.first_name} ${user.last_name}`}
          description={user.email}
        />
      </div>

      {actionError ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {actionError}
        </p>
      ) : null}
      {notice ? (
        <p
          className="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      <AdminPanel>
        <AdminPanelHeader
          title="Profile"
          description="Account details and current access."
        />
        <dl className="grid gap-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Status
            </dt>
            <dd className="mt-1.5">
              <StatusBadge status={user.status} />
            </dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Email verified
            </dt>
            <dd className="mt-1.5">{user.email_verified ? "Yes" : "No"}</dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Last login
            </dt>
            <dd className="mt-1.5">
              {user.last_login_at
                ? new Date(user.last_login_at).toLocaleString()
                : "Never"}
            </dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Created
            </dt>
            <dd className="mt-1.5">
              {user.created_at
                ? new Date(user.created_at).toLocaleString()
                : "—"}
            </dd>
          </div>
          <div className="sm:col-span-2">
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Current roles
            </dt>
            <dd className="mt-1.5 flex flex-wrap gap-2">
              {user.roles.length > 0 ? (
                user.roles.map((role) => (
                  <Badge key={role} variant="secondary">
                    {roleLabel(role)}
                  </Badge>
                ))
              ) : (
                <span className="text-muted-foreground">None</span>
              )}
            </dd>
          </div>
        </dl>
      </AdminPanel>

      {canManageStatus ? (
        <AdminPanel>
          <AdminPanelHeader
            title="Account status"
            description="Disabling blocks protected API access for this user."
          />
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="destructive"
              disabled={pending || user.status === "disabled"}
              onClick={() => void onStatusChange("disabled")}
            >
              Disable account
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={pending || user.status === "active"}
              onClick={() => void onStatusChange("active")}
            >
              Activate account
            </Button>
          </div>
        </AdminPanel>
      ) : null}

      {canManageRoles ? (
        <AdminPanel>
          <AdminPanelHeader
            title="Roles"
            description="Only approved administrative roles can be assigned."
          />
          <fieldset disabled={pending} className="space-y-3">
            <legend className="sr-only">Assignable roles</legend>
            {APPROVED_ROLES.map((role) => (
              <label
                key={role}
                className="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/15 px-4 py-3"
              >
                <span className="text-sm font-medium">{roleLabel(role)}</span>
                <Switch
                  checked={selectedRoles.includes(role)}
                  onCheckedChange={() => toggleRole(role)}
                  aria-label={roleLabel(role)}
                />
              </label>
            ))}
          </fieldset>
          <div className="mt-4">
            <Button
              type="button"
              disabled={pending}
              onClick={() => void onSaveRoles()}
            >
              {pending ? "Saving…" : "Save roles"}
            </Button>
          </div>
        </AdminPanel>
      ) : null}
    </div>
  );
}
