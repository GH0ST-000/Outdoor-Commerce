"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { fetchAdminPriceLists } from "@/features/pricing/api/price-lists-api";
import { fetchAdminVariantPrices } from "@/features/pricing/api/prices-api";
import { BulkPriceEditor } from "@/features/pricing/components/BulkPriceEditor";
import { PriceEditorDialog } from "@/features/pricing/components/PriceEditorDialog";
import { formatMoneyMinor } from "@/features/pricing/lib/money";
import type {
  PriceList,
  VariantPriceListParams,
  VariantPriceRow,
} from "@/features/pricing/types/pricing-types";
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

type PricesResult = {
  key: string;
  rows: VariantPriceRow[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): VariantPriceListParams & {
  bulk: boolean;
} {
  return {
    search: searchParams.get("search") ?? "",
    price_list_id: searchParams.get("price_list_id") ?? "",
    period_status: (searchParams.get("period_status") ?? "") as VariantPriceListParams["period_status"],
    pricing_ready:
      searchParams.get("pricing_ready") === "1"
        ? "1"
        : searchParams.get("pricing_ready") === "0"
          ? "0"
          : "",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
    bulk: searchParams.get("bulk") === "1",
  };
}

export function PricesPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.PRICING_MANAGE);
  const canPublish = hasPermission(permissions, PERMISSIONS.PRICING_PUBLISH);

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = JSON.stringify(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [priceLists, setPriceLists] = useState<PriceList[]>([]);
  const [result, setResult] = useState<PricesResult | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [editorRow, setEditorRow] = useState<VariantPriceRow | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const selectedPriceList = useMemo(
    () =>
      priceLists.find(
        (list) => String(list.id) === String(params.price_list_id),
      ) ?? null,
    [params.price_list_id, priceLists],
  );

  const syncUrl = useCallback(
    (next: VariantPriceListParams & { bulk?: boolean }) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.price_list_id) qs.set("price_list_id", String(next.price_list_id));
      if (next.period_status) qs.set("period_status", next.period_status);
      if (next.pricing_ready === "1" || next.pricing_ready === "0") {
        qs.set("pricing_ready", next.pricing_ready);
      }
      if (next.bulk) qs.set("bulk", "1");
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
    void fetchAdminPriceLists({ per_page: 100, status: "active" })
      .then((response) => setPriceLists(response.data))
      .catch(() => setPriceLists([]));
  }, []);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      if ((params.search ?? "") === searchInput) return;
      syncUrl({ ...params, search: searchInput, page: 1 });
    }, 350);
    return () => window.clearTimeout(handle);
  }, [searchInput, params, syncUrl]);

  useEffect(() => {
    let cancelled = false;
    const fetchKey = `${requestKey}#${refreshNonce}`;
    void fetchAdminVariantPrices(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          rows: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          rows: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load prices.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [params, requestKey, refreshNonce]);

  const activeKey = `${requestKey}#${refreshNonce}`;
  const loading = result?.key !== activeKey;
  const rows = result?.key === activeKey ? result.rows : [];
  const meta = result?.key === activeKey ? result.meta : null;
  const error = result?.key === activeKey ? result.error : null;

  function onFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...params, search: searchInput, page: 1 });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Prices"
        description="Variant prices by list with draft periods and publishing."
        actions={
          canManage ? (
            <Button
              type="button"
              size="sm"
              variant={params.bulk ? "default" : "outline"}
              onClick={() =>
                syncUrl({ ...params, bulk: !params.bulk, page: 1 })
              }
            >
              {params.bulk ? "Hide bulk editor" : "Bulk editor"}
            </Button>
          ) : null
        }
      />

      {params.bulk ? (
        <BulkPriceEditor
          priceList={selectedPriceList}
          canManage={canManage}
          canPublish={canPublish}
          onSuccess={(message) => {
            setNotice(message);
            setRefreshNonce((value) => value + 1);
          }}
        />
      ) : null}

      {notice ? (
        <p
          className="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12">
          <Field
            label="Search"
            htmlFor="price-search"
            className="lg:col-span-4"
          >
            <Input
              id="price-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Product, SKU, barcode"
            />
          </Field>
          <Field label="Price list" className="lg:col-span-3">
            <Select
              value={params.price_list_id ? String(params.price_list_id) : "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  price_list_id: value === "all" ? "" : value,
                  page: 1,
                })
              }
            >
              <SelectTrigger aria-label="Price list">
                <SelectValue placeholder="All lists" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All lists</SelectItem>
                {priceLists.map((list) => (
                  <SelectItem key={list.id} value={String(list.id)}>
                    {list.name} ({list.currency_code})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Period status" className="lg:col-span-3">
            <Select
              value={params.period_status || "all"}
              onValueChange={(value) =>
                syncUrl({
                  ...params,
                  period_status:
                    value === "all"
                      ? ""
                      : (value as VariantPriceListParams["period_status"]),
                  page: 1,
                })
              }
            >
              <SelectTrigger aria-label="Period status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Any status</SelectItem>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="published">Published</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-end lg:col-span-2">
            <Button type="submit" size="sm" className="w-full">
              Apply
            </Button>
          </div>
        </AdminToolbar>
      </form>

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading prices…
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
            No variant prices match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Product</th>
                <th className={adminThClassName()}>SKU</th>
                <th className={adminThClassName()}>Effective</th>
                <th className={adminThClassName()}>Current period</th>
                <th className={adminThClassName()}>Scheduled</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="hover:bg-muted/30">
                  <td className={adminTdClassName()}>
                    <div className="font-medium">{row.product_name ?? "—"}</div>
                    {row.variant_label ? (
                      <div className="text-xs text-muted-foreground">
                        {row.variant_label}
                      </div>
                    ) : null}
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {row.sku ?? "—"}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.effective_amount_minor != null
                      ? formatMoneyMinor(
                          row.effective_amount_minor,
                          row.currency_code,
                        )
                      : "—"}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.current_period ? (
                      <div className="space-y-1">
                        <StatusBadge status={row.current_period.status} />
                        <div className="text-xs text-muted-foreground">
                          {formatMoneyMinor(
                            row.current_period.amount_minor,
                            row.currency_code,
                          )}
                        </div>
                      </div>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className={adminTdClassName()}>
                    {row.scheduled_period ? (
                      <div className="space-y-1">
                        <StatusBadge status={row.scheduled_period.status} />
                        <div className="text-xs text-muted-foreground">
                          {formatMoneyMinor(
                            row.scheduled_period.amount_minor,
                            row.currency_code,
                          )}
                        </div>
                      </div>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className={adminTdClassName()}>
                    {canManage ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setEditorRow(row)}
                      >
                        Edit
                      </Button>
                    ) : (
                      "—"
                    )}
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

      {editorRow ? (
        <PriceEditorDialog
          row={editorRow}
          canManage={canManage}
          canPublish={canPublish}
          onClose={() => setEditorRow(null)}
          onSaved={(message) => {
            setNotice(message);
            setEditorRow(null);
            setRefreshNonce((value) => value + 1);
          }}
        />
      ) : null}
    </div>
  );
}
