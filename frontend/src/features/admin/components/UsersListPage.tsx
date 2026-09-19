"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { fetchAdminUsers } from "@/features/admin/api/admin-api";
import { APPROVED_ROLES } from "@/features/admin/permissions/permissions";
import { roleLabel } from "@/features/admin/permissions/permission-labels";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  AdminTable,
  AdminToolbar,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
import type {
  AdminUser,
  PaginationMeta,
  UserListParams,
} from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type SortField = NonNullable<UserListParams["sort"]>;

type UsersResult = {
  key: string;
  users: AdminUser[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): UserListParams {
  const sort = searchParams.get("sort");
  const direction = searchParams.get("direction");
  const status = searchParams.get("status");
  const allowedSorts: SortField[] = [
    "created_at",
    "last_login_at",
    "email",
    "first_name",
  ];

  return {
    search: searchParams.get("search") ?? "",
    status: status === "active" || status === "disabled" ? status : "",
    role: searchParams.get("role") ?? "",
    sort: allowedSorts.includes(sort as SortField)
      ? (sort as SortField)
      : "created_at",
    direction: direction === "asc" ? "asc" : "desc",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

function paramsKey(params: UserListParams): string {
  return JSON.stringify(params);
}

export function UsersListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = paramsKey(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [result, setResult] = useState<UsersResult | null>(null);

  const syncUrl = useCallback(
    (next: UserListParams) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.status) qs.set("status", next.status);
      if (next.role) qs.set("role", next.role);
      if (next.sort && next.sort !== "created_at") qs.set("sort", next.sort);
      if (next.direction && next.direction !== "desc") {
        qs.set("direction", next.direction);
      }
      if (next.per_page && next.per_page !== 10) {
        qs.set("per_page", String(next.per_page));
      }
      if (next.page && next.page !== 1) qs.set("page", String(next.page));
      const query = qs.toString();
      router.replace(query ? `${pathname}?${query}` : pathname);
    },
    [pathname, router],
  );

  useEffect(() => {
    const handle = window.setTimeout(() => {
      if ((params.search ?? "") === searchInput) {
        return;
      }
      syncUrl({ ...params, search: searchInput, page: 1 });
    }, 350);

    return () => {
      window.clearTimeout(handle);
    };
  }, [searchInput, params, syncUrl]);

  useEffect(() => {
    let cancelled = false;

    void fetchAdminUsers(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          users: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          users: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load users.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [params, requestKey]);

  const loading = result?.key !== requestKey;
  const users = result?.key === requestKey ? result.users : [];
  const meta = result?.key === requestKey ? result.meta : null;
  const error = result?.key === requestKey ? result.error : null;

  function onFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...params, search: searchInput, page: 1 });
  }

  function toggleSort(field: SortField) {
    if (params.sort === field) {
      syncUrl({
        ...params,
        direction: params.direction === "asc" ? "desc" : "asc",
      });
      return;
    }
    syncUrl({ ...params, sort: field, direction: "asc" });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Users"
        description="Find accounts, check status, and manage roles."
      />

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12">
          <Field label="Search" htmlFor="user-search" className="lg:col-span-5">
            <Input
              id="user-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Name or email"
            />
          </Field>
          <Field label="Status" className="lg:col-span-2">
            <Select
              value={params.status || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  status: (value === "all"
                    ? ""
                    : value) as UserListParams["status"],
                  page: 1,
                })
              }
            >
              <SelectTrigger aria-label="Status">
                <SelectValue placeholder="All" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="disabled">Disabled</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="Role" className="lg:col-span-3">
            <Select
              value={params.role || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  role: value === "all" ? "" : value,
                  page: 1,
                })
              }
            >
              <SelectTrigger aria-label="Role">
                <SelectValue placeholder="All" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All</SelectItem>
                {APPROVED_ROLES.map((role) => (
                  <SelectItem key={role} value={role}>
                    {roleLabel(role)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-end lg:col-span-2">
            <Button type="submit" className="w-full">
              Search
            </Button>
          </div>
        </AdminToolbar>
      </form>

      {loading ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading users…
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

      {!loading && !error && users.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm font-medium">No users match these filters.</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Try a different search or clear the filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && users.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>
                  <button
                    type="button"
                    className="font-semibold hover:text-foreground"
                    onClick={() => toggleSort("first_name")}
                  >
                    Name
                  </button>
                </th>
                <th className={adminThClassName()}>
                  <button
                    type="button"
                    className="font-semibold hover:text-foreground"
                    onClick={() => toggleSort("email")}
                  >
                    Email
                  </button>
                </th>
                <th className={adminThClassName()}>Status</th>
                <th className={adminThClassName()}>Roles</th>
                <th className={adminThClassName()}>
                  <button
                    type="button"
                    className="font-semibold hover:text-foreground"
                    onClick={() => toggleSort("created_at")}
                  >
                    Created
                  </button>
                </th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr
                  key={user.id}
                  className="transition-colors hover:bg-muted/30"
                >
                  <td className={adminTdClassName()}>
                    <Link
                      href={`/admin/users/${user.id}`}
                      className="font-medium text-foreground no-underline hover:text-primary"
                    >
                      {user.first_name} {user.last_name}
                    </Link>
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {user.email}
                  </td>
                  <td className={adminTdClassName()}>
                    <StatusBadge status={user.status} />
                  </td>
                  <td className={adminTdClassName()}>
                    {user.roles.length > 0
                      ? user.roles.map(roleLabel).join(", ")
                      : "—"}
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {user.created_at
                      ? new Date(user.created_at).toLocaleString()
                      : "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </AdminTable>
      ) : null}

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <p className="text-xs text-muted-foreground">
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </p>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() =>
                syncUrl({ ...params, page: Math.max(1, meta.current_page - 1) })
              }
            >
              Previous
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() =>
                syncUrl({
                  ...params,
                  page: Math.min(meta.last_page, meta.current_page + 1),
                })
              }
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
