"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  activateAdminPromotion,
  archiveAdminPromotion,
  fetchAdminPromotions,
  pauseAdminPromotion,
  restoreAdminPromotion,
} from "@/features/pricing/api/promotions-api";
import { basisPointsToPercentLabel } from "@/features/pricing/lib/money";
import type {
  Promotion,
  PromotionListParams,
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
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";

type PromotionsResult = {
  key: string;
  promotions: Promotion[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): PromotionListParams {
  return {
    search: searchParams.get("search") ?? "",
    include_deleted: searchParams.get("include_deleted") === "1" ? "1" : "",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

function discountLabel(promotion: Promotion): string {
  if (promotion.discount_type === "percentage") {
    return basisPointsToPercentLabel(promotion.percentage_basis_points ?? 0);
  }
  if (promotion.fixed_amount_minor != null && promotion.currency_code) {
    return `${(promotion.fixed_amount_minor / 100).toFixed(2)} ${promotion.currency_code}`;
  }
  return "—";
}

export function PromotionsPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.PROMOTIONS_MANAGE);
  const canPublish = hasPermission(permissions, PERMISSIONS.PROMOTIONS_PUBLISH);

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = JSON.stringify(params);

  const [searchInput, setSearchInput] = useState(params.search ?? "");
  const [result, setResult] = useState<PromotionsResult | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [actionPending, setActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);

  const syncUrl = useCallback(
    (next: PromotionListParams) => {
      const qs = new URLSearchParams();
      if (next.search) qs.set("search", next.search);
      if (next.include_deleted === "1") qs.set("include_deleted", "1");
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
      if ((params.search ?? "") === searchInput) return;
      syncUrl({ ...params, search: searchInput, page: 1 });
    }, 350);
    return () => window.clearTimeout(handle);
  }, [searchInput, params, syncUrl]);

  useEffect(() => {
    let cancelled = false;
    const fetchKey = `${requestKey}#${refreshNonce}`;
    void fetchAdminPromotions(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          promotions: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          promotions: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load promotions.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [params, requestKey, refreshNonce]);

  const activeKey = `${requestKey}#${refreshNonce}`;
  const loading = result?.key !== activeKey;
  const promotions = result?.key === activeKey ? result.promotions : [];
  const meta = result?.key === activeKey ? result.meta : null;
  const error = result?.key === activeKey ? result.error : null;

  function onFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...params, search: searchInput, page: 1 });
  }

  async function runAction(task: () => Promise<unknown>, notice: string) {
    if (actionPending) return;
    setActionPending(true);
    setActionError(null);
    setActionNotice(null);
    try {
      await task();
      setActionNotice(notice);
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to complete action.",
      );
    } finally {
      setActionPending(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Promotions"
        description="Discount rules with targets, stacking, and lifecycle controls."
        actions={
          canManage ? (
            <Button asChild size="sm">
              <Link href="/admin/pricing/promotions/new">New promotion</Link>
            </Button>
          ) : null
        }
      />

      <form onSubmit={onFilterSubmit}>
        <AdminToolbar className="lg:grid-cols-12">
          <Field
            label="Search"
            htmlFor="promotion-search"
            className="lg:col-span-6"
          >
            <Input
              id="promotion-search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Code or name"
            />
          </Field>
          <div className="flex items-end gap-3 lg:col-span-6">
            <div className="flex items-center gap-2">
              <Switch
                id="promotion-include-archived"
                checked={params.include_deleted === "1"}
                onCheckedChange={(checked) =>
                  syncUrl({
                    ...params,
                    include_deleted: checked ? "1" : "",
                    page: 1,
                  })
                }
              />
              <Label htmlFor="promotion-include-archived" className="text-sm">
                Include archived
              </Label>
            </div>
            <Button type="submit" size="sm">
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

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading promotions…
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

      {!loading && !error && promotions.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm text-muted-foreground">
            No promotions match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && promotions.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Name</th>
                <th className={adminThClassName()}>Code</th>
                <th className={adminThClassName()}>Discount</th>
                <th className={adminThClassName()}>Status</th>
                <th className={adminThClassName()}>Schedule</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {promotions.map((promotion) => (
                <tr key={promotion.id} className="hover:bg-muted/30">
                  <td className={adminTdClassName()}>
                    <Link
                      href={`/admin/pricing/promotions/${promotion.id}/edit`}
                      className="font-medium no-underline hover:text-primary"
                    >
                      {promotion.name}
                    </Link>
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {promotion.code}
                  </td>
                  <td className={adminTdClassName()}>
                    {discountLabel(promotion)}
                  </td>
                  <td className={adminTdClassName()}>
                    <StatusBadge status={promotion.status} />
                  </td>
                  <td
                    className={`${adminTdClassName()} text-xs text-muted-foreground`}
                  >
                    {new Date(promotion.starts_at).toLocaleDateString()}
                    {promotion.ends_at
                      ? ` – ${new Date(promotion.ends_at).toLocaleDateString()}`
                      : ""}
                  </td>
                  <td className={adminTdClassName()}>
                    <div className="flex flex-wrap gap-2">
                      <Button asChild variant="outline" size="sm">
                        <Link
                          href={`/admin/pricing/promotions/${promotion.id}/edit`}
                        >
                          Edit
                        </Link>
                      </Button>
                      {canPublish && promotion.status === "draft" ? (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={actionPending}
                          onClick={() =>
                            void runAction(
                              () => activateAdminPromotion(promotion.id),
                              "Promotion activated.",
                            )
                          }
                        >
                          Activate
                        </Button>
                      ) : null}
                      {canPublish && promotion.status === "active" ? (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={actionPending}
                          onClick={() =>
                            void runAction(
                              () => pauseAdminPromotion(promotion.id),
                              "Promotion paused.",
                            )
                          }
                        >
                          Pause
                        </Button>
                      ) : null}
                      {canManage && promotion.status !== "archived" ? (
                        <Button
                          type="button"
                          variant="destructive"
                          size="sm"
                          disabled={actionPending}
                          onClick={() =>
                            void runAction(
                              () => archiveAdminPromotion(promotion.id),
                              "Promotion archived.",
                            )
                          }
                        >
                          Archive
                        </Button>
                      ) : null}
                      {canManage && promotion.status === "archived" ? (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={actionPending}
                          onClick={() =>
                            void runAction(
                              () => restoreAdminPromotion(promotion.id),
                              "Promotion restored.",
                            )
                          }
                        >
                          Restore
                        </Button>
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
