"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { fetchAdminInventory } from "@/features/inventory/api/inventory-api";
import { fetchAdminWarehouses } from "@/features/inventory/api/warehouses-api";
import type {
  InventoryBalanceRef,
  InventoryListParams,
  WarehouseListItem,
} from "@/features/inventory/types/inventory-types";
import type { PaginationMeta } from "@/features/admin/types/admin-types";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  AdminTable,
  AdminToolbar,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
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

type InventoryResult = {
  key: string;
  rows: InventoryBalanceRef[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): InventoryListParams {
  return {
    search: searchParams.get("search") ?? "",
    warehouse_id: searchParams.get("warehouse_id") ?? "",
    low_stock: searchParams.get("low_stock") === "1" ? "1" : "",
    out_of_stock: searchParams.get("out_of_stock") === "1" ? "1" : "",
    has_reservations:
      searchParams.get("has_reservations") === "1" ? "1" : "",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

function balanceKey(row: InventoryBalanceRef): string {
  return `${row.warehouse.id}-${row.variant.id}`;
}

export function InventoryListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = JSON.stringify(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [result, setResult] = useState<InventoryResult | null>(null);
  const [warehouses, setWarehouses] = useState<WarehouseListItem[]>([]);

  const syncUrl = useCallback(
    (next: InventoryListParams) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.warehouse_id) qs.set("warehouse_id", String(next.warehouse_id));
      if (next.low_stock === "1") qs.set("low_stock", "1");
      if (next.out_of_stock === "1") qs.set("out_of_stock", "1");
      if (next.has_reservations === "1") qs.set("has_reservations", "1");
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
    return () => window.clearTimeout(handle);
  }, [searchInput, params, syncUrl]);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminWarehouses({ status: "active", per_page: 50 })
      .then((response) => {
        if (cancelled) return;
        setWarehouses(response.data);
      })
      .catch(() => {
        if (cancelled) return;
        setWarehouses([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminInventory(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          rows: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          rows: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load inventory.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [params, requestKey]);

  const loading = result?.key !== requestKey;
  const rows = result?.key === requestKey ? result.rows : [];
  const meta = result?.key === requestKey ? result.meta : null;
  const error = result?.key === requestKey ? result.error : null;

  function onFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...params, search: searchInput, page: 1 });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Stock"
        description="Search balances by SKU, barcode, or product name."
      />

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12">
          <Field
            label="Search"
            htmlFor="inventory-search"
            className="lg:col-span-4"
          >
            <Input
              id="inventory-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="SKU, barcode, or product"
            />
          </Field>
          <Field label="Warehouse" className="lg:col-span-3">
            <Select
              value={params.warehouse_id ? String(params.warehouse_id) : "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  warehouse_id: value === "all" ? "" : value,
                  page: 1,
                })
              }
            >
              <SelectTrigger id="inventory-warehouse" aria-label="Warehouse">
                <SelectValue placeholder="All warehouses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All warehouses</SelectItem>
                {warehouses.map((warehouse) => (
                  <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                    {warehouse.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <div className="flex flex-col justify-end gap-3 lg:col-span-5">
            <div className="flex flex-wrap items-center gap-4">
              <div className="flex items-center gap-2">
                <Switch
                  id="inventory-low-stock"
                  checked={params.low_stock === "1"}
                  onCheckedChange={(checked) =>
                    syncUrl({
                      ...params,
                      low_stock: checked ? "1" : "",
                      page: 1,
                    })
                  }
                />
                <Label htmlFor="inventory-low-stock" className="text-sm">
                  Low stock
                </Label>
              </div>
              <div className="flex items-center gap-2">
                <Switch
                  id="inventory-out-of-stock"
                  checked={params.out_of_stock === "1"}
                  onCheckedChange={(checked) =>
                    syncUrl({
                      ...params,
                      out_of_stock: checked ? "1" : "",
                      page: 1,
                    })
                  }
                />
                <Label htmlFor="inventory-out-of-stock" className="text-sm">
                  Out of stock
                </Label>
              </div>
              <div className="flex items-center gap-2">
                <Switch
                  id="inventory-has-reservations"
                  checked={params.has_reservations === "1"}
                  onCheckedChange={(checked) =>
                    syncUrl({
                      ...params,
                      has_reservations: checked ? "1" : "",
                      page: 1,
                    })
                  }
                />
                <Label htmlFor="inventory-has-reservations" className="text-sm">
                  Has reservations
                </Label>
              </div>
            </div>
            <Button type="submit" size="sm" className="w-full sm:w-auto">
              Apply search
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
          Loading inventory…
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

      {!loading && !error && rows.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm text-muted-foreground">
            No inventory rows match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Product</th>
                <th className={adminThClassName()}>Variant</th>
                <th className={adminThClassName()}>Warehouse</th>
                <th className={adminThClassName()}>On hand</th>
                <th className={adminThClassName()}>Reserved</th>
                <th className={adminThClassName()}>Available</th>
                <th className={adminThClassName()}>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={balanceKey(row)}
                  className="transition-colors hover:bg-muted/30"
                >
                  <td className={adminTdClassName()}>
                    <Link
                      href={`/admin/inventory/${row.warehouse.id}/${row.variant.id}`}
                      className="font-medium text-foreground no-underline hover:text-primary"
                    >
                      {row.product.name ?? `Product #${row.product.id}`}
                    </Link>
                  </td>
                  <td
                    className={`${adminTdClassName()} text-muted-foreground`}
                  >
                    {row.variant.sku ?? row.variant.combination_label ?? "—"}
                  </td>
                  <td
                    className={`${adminTdClassName()} text-muted-foreground`}
                  >
                    {row.warehouse.name}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.quantities.on_hand}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.quantities.reserved}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.quantities.available_to_sell}
                  </td>
                  <td className={adminTdClassName()}>
                    <div className="flex flex-wrap gap-1">
                      {row.status.out_of_stock ? (
                        <Badge variant="danger">Out</Badge>
                      ) : null}
                      {row.status.low_stock ? (
                        <Badge variant="secondary">Low</Badge>
                      ) : null}
                      {!row.status.out_of_stock && !row.status.low_stock ? (
                        <span className="text-muted-foreground">OK</span>
                      ) : null}
                    </div>
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
