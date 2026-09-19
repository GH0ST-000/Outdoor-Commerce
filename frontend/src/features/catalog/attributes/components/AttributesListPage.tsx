"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  archiveAdminAttribute,
  fetchAdminAttributes,
  restoreAdminAttribute,
} from "@/features/catalog/attributes/api/attributes-api";
import { ArchiveAttributeDialog } from "@/features/catalog/attributes/components/ArchiveAttributeDialog";
import type {
  AttributeListItem,
  AttributeListParams,
} from "@/features/catalog/attributes/types/attribute-types";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import type { PaginationMeta } from "@/features/admin/types/admin-types";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  AdminTable,
  AdminToolbar,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
import { ApiClientError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";

type SortField = NonNullable<AttributeListParams["sort"]>;

type AttributesResult = {
  key: string;
  attributes: AttributeListItem[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): AttributeListParams {
  const sort = searchParams.get("sort");
  const direction = searchParams.get("direction");
  const status = searchParams.get("status");
  const type = searchParams.get("type");
  const filterable = searchParams.get("is_filterable");
  const includeDeleted = searchParams.get("include_deleted");
  const allowedSorts: SortField[] = [
    "created_at",
    "updated_at",
    "sort_order",
    "code",
    "status",
  ];
  const allowedStatuses = ["draft", "active", "archived"] as const;
  const allowedTypes = ["select", "color"] as const;

  return {
    search: searchParams.get("search") ?? "",
    status: allowedStatuses.includes(status as (typeof allowedStatuses)[number])
      ? (status as AttributeListParams["status"])
      : "",
    type: allowedTypes.includes(type as (typeof allowedTypes)[number])
      ? (type as AttributeListParams["type"])
      : "",
    is_filterable: filterable === "1" || filterable === "0" ? filterable : "",
    include_deleted:
      includeDeleted === "1" || includeDeleted === "0" ? includeDeleted : "",
    sort: allowedSorts.includes(sort as SortField)
      ? (sort as SortField)
      : "sort_order",
    direction: direction === "desc" ? "desc" : "asc",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

export function AttributesListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.CATALOG_MANAGE);

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = JSON.stringify(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [result, setResult] = useState<AttributesResult | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [archiveTarget, setArchiveTarget] = useState<AttributeListItem | null>(
    null,
  );
  const [actionPending, setActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);

  const syncUrl = useCallback(
    (next: AttributeListParams) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.status) qs.set("status", next.status);
      if (next.type) qs.set("type", next.type);
      if (next.is_filterable) qs.set("is_filterable", next.is_filterable);
      if (next.include_deleted === "1") qs.set("include_deleted", "1");
      if (next.sort && next.sort !== "sort_order") qs.set("sort", next.sort);
      if (next.direction && next.direction !== "asc") {
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
    const fetchKey = `${requestKey}#${refreshNonce}`;

    void fetchAdminAttributes(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          attributes: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          attributes: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load attributes.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [params, requestKey, refreshNonce]);

  const activeKey = `${requestKey}#${refreshNonce}`;
  const loading = result?.key !== activeKey;
  const attributes = result?.key === activeKey ? result.attributes : [];
  const meta = result?.key === activeKey ? result.meta : null;
  const error = result?.key === activeKey ? result.error : null;

  function onFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...params, search: searchInput, page: 1 });
  }

  async function confirmArchive() {
    if (!archiveTarget || actionPending) return;
    setActionPending(true);
    setActionError(null);
    setActionNotice(null);
    try {
      await archiveAdminAttribute(archiveTarget.id);
      setArchiveTarget(null);
      setActionNotice("Attribute archived.");
      syncUrl({ ...params, include_deleted: "1", page: 1 });
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to archive attribute.",
      );
    } finally {
      setActionPending(false);
    }
  }

  async function onRestore(attribute: AttributeListItem) {
    if (actionPending) return;
    setActionPending(true);
    setActionError(null);
    setActionNotice(null);
    try {
      await restoreAdminAttribute(attribute.id);
      setActionNotice("Attribute restored to draft.");
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to restore attribute.",
      );
    } finally {
      setActionPending(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Attributes"
        description="Variant axes and their values. Only active attributes can be assigned to products."
        actions={
          canManage ? (
            <Button asChild size="sm">
              <Link href="/admin/catalog/attributes/new">New attribute</Link>
            </Button>
          ) : null
        }
      />

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12 xl:grid-cols-12">
          <Field
            label="Search"
            htmlFor="attribute-search"
            className="lg:col-span-4"
          >
            <Input
              id="attribute-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Code or name"
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
                    : value) as AttributeListParams["status"],
                  page: 1,
                })
              }
            >
              <SelectTrigger id="attribute-status" aria-label="Status">
                <SelectValue placeholder="All" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All</SelectItem>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="archived">Archived</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="Type" className="lg:col-span-2">
            <Select
              value={params.type || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  type: (value === "all"
                    ? ""
                    : value) as AttributeListParams["type"],
                  page: 1,
                })
              }
            >
              <SelectTrigger id="attribute-type" aria-label="Type">
                <SelectValue placeholder="All" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All</SelectItem>
                <SelectItem value="select">Select</SelectItem>
                <SelectItem value="color">Color</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="Filterable" className="lg:col-span-2">
            <Select
              value={params.is_filterable || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  is_filterable: (value === "all"
                    ? ""
                    : value) as AttributeListParams["is_filterable"],
                  page: 1,
                })
              }
            >
              <SelectTrigger id="attribute-filterable" aria-label="Filterable">
                <SelectValue placeholder="Any" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Any</SelectItem>
                <SelectItem value="1">Filterable</SelectItem>
                <SelectItem value="0">Not filterable</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-end gap-3 lg:col-span-8">
            <div className="flex items-center gap-2">
              <Switch
                id="attribute-include-archived"
                checked={params.include_deleted === "1"}
                onCheckedChange={(checked) =>
                  syncUrl({
                    ...params,
                    include_deleted: checked ? "1" : "",
                    page: 1,
                  })
                }
              />
              <Label htmlFor="attribute-include-archived" className="text-sm">
                Include archived
              </Label>
            </div>
          </div>
          <div className="flex items-end lg:col-span-4">
            <Button type="submit" size="sm" className="w-full sm:w-auto">
              Apply search
            </Button>
          </div>
        </AdminToolbar>
      </form>

      {actionError ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {actionError}
        </p>
      ) : null}
      {actionNotice ? (
        <p
          className="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {actionNotice}
        </p>
      ) : null}

      {archiveTarget ? (
        <ArchiveAttributeDialog
          title="Archive attribute?"
          label={
            archiveTarget.name
              ? `"${archiveTarget.name}"`
              : `attribute #${archiveTarget.id}`
          }
          pending={actionPending}
          onConfirm={() => void confirmArchive()}
          onCancel={() => setArchiveTarget(null)}
        />
      ) : null}

      {loading ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading attributes…
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

      {!loading && !error && attributes.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm text-muted-foreground">
            No attributes match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && attributes.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Name</th>
                <th className={adminThClassName()}>Code</th>
                <th className={adminThClassName()}>Type</th>
                <th className={adminThClassName()}>Status</th>
                <th className={adminThClassName()}>Values</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {attributes.map((attribute) => {
                const isDeleted = Boolean(attribute.deleted_at);
                return (
                  <tr
                    key={attribute.id}
                    className="transition-colors hover:bg-muted/30"
                  >
                    <td className={adminTdClassName()}>
                      <div className="flex flex-wrap items-center gap-2">
                        {isDeleted ? (
                          <span className="font-medium">
                            {attribute.name ?? `Attribute #${attribute.id}`}
                          </span>
                        ) : (
                          <Link
                            href={`/admin/catalog/attributes/${attribute.id}/edit`}
                            className="font-medium text-foreground no-underline hover:text-primary"
                          >
                            {attribute.name ?? `Attribute #${attribute.id}`}
                          </Link>
                        )}
                        {attribute.is_filterable ? (
                          <Badge variant="secondary">Filterable</Badge>
                        ) : null}
                      </div>
                    </td>
                    <td
                      className={`${adminTdClassName()} font-mono text-xs text-muted-foreground`}
                    >
                      {attribute.code}
                    </td>
                    <td
                      className={`${adminTdClassName()} text-muted-foreground`}
                    >
                      {attribute.type}
                    </td>
                    <td className={adminTdClassName()}>
                      <StatusBadge status={attribute.status} />
                    </td>
                    <td
                      className={`${adminTdClassName()} text-muted-foreground`}
                    >
                      {attribute.value_count}
                    </td>
                    <td className={adminTdClassName()}>
                      <div className="flex flex-wrap gap-2">
                        {!isDeleted ? (
                          <Button asChild variant="outline" size="sm">
                            <Link
                              href={`/admin/catalog/attributes/${attribute.id}/edit`}
                            >
                              Edit
                            </Link>
                          </Button>
                        ) : null}
                        {canManage && !isDeleted ? (
                          <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            disabled={actionPending}
                            onClick={() => setArchiveTarget(attribute)}
                          >
                            Archive
                          </Button>
                        ) : null}
                        {canManage && isDeleted ? (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={actionPending}
                            onClick={() => void onRestore(attribute)}
                          >
                            Restore to draft
                          </Button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
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
                syncUrl({
                  ...params,
                  page: Math.max(1, meta.current_page - 1),
                })
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
