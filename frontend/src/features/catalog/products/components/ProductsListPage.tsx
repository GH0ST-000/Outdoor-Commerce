"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  archiveAdminProduct,
  fetchAdminProducts,
  fetchCatalogBrandOptions,
  fetchCatalogCategoryOptions,
  restoreAdminProduct,
} from "@/features/catalog/products/api/products-api";
import { ActiveNotPurchasableNotice } from "@/features/catalog/products/components/ActiveNotPurchasableNotice";
import { ArchiveConfirmDialog } from "@/features/catalog/products/components/ArchiveConfirmDialog";
import { ProductReadinessPanel } from "@/features/catalog/products/components/ProductReadinessPanel";
import type {
  CatalogOption,
  ProductListItem,
  ProductListParams,
} from "@/features/catalog/products/types/product-types";
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

type SortField = NonNullable<ProductListParams["sort"]>;

type ProductsResult = {
  key: string;
  products: ProductListItem[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): ProductListParams {
  const sort = searchParams.get("sort");
  const direction = searchParams.get("direction");
  const status = searchParams.get("status");
  const featured = searchParams.get("is_featured");
  const includeDeleted = searchParams.get("include_deleted");
  const allowedSorts: SortField[] = [
    "created_at",
    "updated_at",
    "published_at",
    "sort_order",
    "status",
  ];
  const allowedStatuses = ["draft", "active", "archived"] as const;

  return {
    search: searchParams.get("search") ?? "",
    status: allowedStatuses.includes(status as (typeof allowedStatuses)[number])
      ? (status as ProductListParams["status"])
      : "",
    brand_id: searchParams.get("brand_id") ?? "",
    category_id: searchParams.get("category_id") ?? "",
    primary_category_id: searchParams.get("primary_category_id") ?? "",
    is_featured: featured === "1" || featured === "0" ? featured : "",
    include_deleted:
      includeDeleted === "1" || includeDeleted === "0" ? includeDeleted : "",
    sort: allowedSorts.includes(sort as SortField)
      ? (sort as SortField)
      : "created_at",
    direction: direction === "asc" ? "asc" : "desc",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

function paramsKey(params: ProductListParams): string {
  return JSON.stringify(params);
}

export function ProductsListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.CATALOG_MANAGE);

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = paramsKey(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [result, setResult] = useState<ProductsResult | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [brands, setBrands] = useState<CatalogOption[]>([]);
  const [categories, setCategories] = useState<CatalogOption[]>([]);
  const [archiveTarget, setArchiveTarget] = useState<ProductListItem | null>(
    null,
  );
  const [actionPending, setActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);

  const syncUrl = useCallback(
    (next: ProductListParams) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.status) qs.set("status", next.status);
      if (next.brand_id) qs.set("brand_id", String(next.brand_id));
      if (next.category_id) qs.set("category_id", String(next.category_id));
      if (next.primary_category_id) {
        qs.set("primary_category_id", String(next.primary_category_id));
      }
      if (next.is_featured) qs.set("is_featured", next.is_featured);
      if (next.include_deleted === "1") {
        qs.set("include_deleted", "1");
      }
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
    void Promise.all([
      fetchCatalogBrandOptions(),
      fetchCatalogCategoryOptions(),
    ])
      .then(([nextBrands, nextCategories]) => {
        if (cancelled) return;
        setBrands(nextBrands);
        setCategories(nextCategories);
      })
      .catch(() => {
        if (cancelled) return;
        setBrands([]);
        setCategories([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    const fetchKey = `${requestKey}#${refreshNonce}`;

    void fetchAdminProducts(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          products: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          products: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load products.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [params, requestKey, refreshNonce]);

  const activeKey = `${requestKey}#${refreshNonce}`;
  const loading = result?.key !== activeKey;
  const products = result?.key === activeKey ? result.products : [];
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
      await archiveAdminProduct(archiveTarget.id);
      setArchiveTarget(null);
      setActionNotice("Product archived.");
      syncUrl({ ...params, include_deleted: "1", page: 1 });
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to archive product.",
      );
    } finally {
      setActionPending(false);
    }
  }

  async function onRestore(product: ProductListItem) {
    if (actionPending) return;
    setActionPending(true);
    setActionError(null);
    setActionNotice(null);
    try {
      await restoreAdminProduct(product.id);
      setActionNotice("Product restored to draft.");
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to restore product.",
      );
    } finally {
      setActionPending(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Products"
        description="Search, filter, and manage catalog products."
        actions={
          canManage ? (
            <Button asChild size="sm">
              <Link href="/admin/catalog/products/new">New product</Link>
            </Button>
          ) : null
        }
      />

      <ActiveNotPurchasableNotice />

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12 xl:grid-cols-12">
          <Field
            label="Search"
            htmlFor="product-search"
            className="lg:col-span-4"
          >
            <Input
              id="product-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Name, slug, or model"
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
                    : value) as ProductListParams["status"],
                  page: 1,
                })
              }
            >
              <SelectTrigger id="product-status" aria-label="Status">
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
          <Field label="Brand" className="lg:col-span-2">
            <Select
              value={params.brand_id ? String(params.brand_id) : "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  brand_id: value === "all" ? "" : value,
                  page: 1,
                })
              }
            >
              <SelectTrigger id="product-brand" aria-label="Brand">
                <SelectValue placeholder="All brands" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All brands</SelectItem>
                {brands.map((brand) => (
                  <SelectItem key={brand.id} value={String(brand.id)}>
                    {brand.name ?? `Brand #${brand.id}`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Category" className="lg:col-span-2">
            <Select
              value={params.category_id ? String(params.category_id) : "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  category_id: value === "all" ? "" : value,
                  page: 1,
                })
              }
            >
              <SelectTrigger id="product-category" aria-label="Category">
                <SelectValue placeholder="All categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All categories</SelectItem>
                {categories.map((category) => (
                  <SelectItem key={category.id} value={String(category.id)}>
                    {category.name ?? `Category #${category.id}`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Featured" className="lg:col-span-2">
            <Select
              value={params.is_featured || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  is_featured: (value === "all"
                    ? ""
                    : value) as ProductListParams["is_featured"],
                  page: 1,
                })
              }
            >
              <SelectTrigger id="product-featured" aria-label="Featured">
                <SelectValue placeholder="Any" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Any</SelectItem>
                <SelectItem value="1">Featured</SelectItem>
                <SelectItem value="0">Not featured</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-end gap-3 lg:col-span-8">
            <div className="flex items-center gap-2">
              <Switch
                id="product-include-archived"
                checked={params.include_deleted === "1"}
                onCheckedChange={(checked) =>
                  syncUrl({
                    ...params,
                    include_deleted: checked ? "1" : "",
                    page: 1,
                  })
                }
              />
              <Label htmlFor="product-include-archived" className="text-sm">
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
        <ArchiveConfirmDialog
          productLabel={
            archiveTarget.name
              ? `"${archiveTarget.name}"`
              : `product #${archiveTarget.id}`
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
          Loading products…
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

      {!loading && !error && products.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm text-muted-foreground">
            No products match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && products.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Name</th>
                <th className={adminThClassName()}>Status</th>
                <th className={adminThClassName()}>Brand</th>
                <th className={adminThClassName()}>Category</th>
                <th className={adminThClassName()}>Readiness</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {products.map((product) => {
                const isDeleted = Boolean(product.deleted_at);
                return (
                  <tr
                    key={product.id}
                    className="transition-colors hover:bg-muted/30"
                  >
                    <td className={adminTdClassName()}>
                      <div className="flex flex-wrap items-center gap-2">
                        {isDeleted ? (
                          <span className="font-medium">
                            {product.name ?? `Product #${product.id}`}
                          </span>
                        ) : (
                          <Link
                            href={`/admin/catalog/products/${product.id}/edit`}
                            className="font-medium text-foreground no-underline hover:text-primary"
                          >
                            {product.name ?? `Product #${product.id}`}
                          </Link>
                        )}
                        {product.is_featured ? (
                          <Badge variant="secondary">Featured</Badge>
                        ) : null}
                      </div>
                    </td>
                    <td className={adminTdClassName()}>
                      <StatusBadge status={product.status} />
                    </td>
                    <td
                      className={`${adminTdClassName()} text-muted-foreground`}
                    >
                      {product.brand?.name ?? "—"}
                    </td>
                    <td
                      className={`${adminTdClassName()} text-muted-foreground`}
                    >
                      {product.primary_category?.name ?? "—"}
                    </td>
                    <td className={adminTdClassName()}>
                      <ProductReadinessPanel
                        readiness={product.readiness}
                        compact
                      />
                    </td>
                    <td className={adminTdClassName()}>
                      <div className="flex flex-wrap gap-2">
                        {!isDeleted ? (
                          <Button asChild variant="outline" size="sm">
                            <Link
                              href={`/admin/catalog/products/${product.id}/edit`}
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
                            onClick={() => setArchiveTarget(product)}
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
                            onClick={() => void onRestore(product)}
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
