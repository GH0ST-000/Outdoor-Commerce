"use client";

import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { filterAdminNav } from "@/features/admin/navigation/admin-nav";
import { roleLabel } from "@/features/admin/permissions/permission-labels";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ActiveNotPurchasableNotice } from "@/features/catalog/products/components/ActiveNotPurchasableNotice";
import { Badge } from "@/components/ui/badge";

export function AdminDashboard() {
  const { context, roles, permissions } = useAdminContext();
  const user = context?.user;
  const sections = filterAdminNav(permissions).filter(
    (item) => item.id !== "dashboard",
  );

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title={`Welcome${user ? `, ${user.first_name}` : ""}`}
        description="Your operations workspace. What you can open depends on the permissions assigned to your account."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <AdminPanel>
          <AdminPanelHeader
            title="Your roles"
            description="Assigned for this session."
          />
          {roles.length > 0 ? (
            <ul className="flex flex-wrap gap-2">
              {roles.map((role) => (
                <li key={role}>
                  <Badge variant="default">{roleLabel(role)}</Badge>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">No roles assigned.</p>
          )}
        </AdminPanel>

        <AdminPanel>
          <AdminPanelHeader
            title="Quick open"
            description="Jump into a section you can access."
          />
          {sections.length > 0 ? (
            <ul className="grid gap-2 sm:grid-cols-2">
              {sections.map((section) => (
                <li key={section.id}>
                  <Link
                    href={section.href}
                    className="group flex items-center justify-between rounded-xl border border-border/70 bg-muted/20 px-3.5 py-3 text-sm font-medium no-underline transition-colors hover:border-border hover:bg-muted/45"
                  >
                    <span>{section.label}</span>
                    <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">
              No additional modules are available for your permissions.
            </p>
          )}
        </AdminPanel>
      </div>

      <AdminPanel>
        <AdminPanelHeader title="Account notices" />
        <div className="space-y-3 text-sm">
          {user && !user.email_verified ? (
            <p
              className="rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-amber-950 dark:text-amber-100"
              role="status"
            >
              Your email is not verified yet. Admin access still follows
              assigned permissions.
            </p>
          ) : (
            <p className="rounded-xl border border-border/60 bg-muted/30 px-4 py-3 text-muted-foreground">
              Email is verified.
            </p>
          )}
          <p
            className="rounded-xl border border-border/60 bg-muted/30 px-4 py-3 text-muted-foreground"
            role="note"
          >
            Multi-factor authentication is not configured in this release.
          </p>
          <ActiveNotPurchasableNotice />
        </div>
      </AdminPanel>
    </div>
  );
}
