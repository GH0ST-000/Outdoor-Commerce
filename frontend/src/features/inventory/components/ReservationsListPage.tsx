"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  cancelAdminInventoryReservation,
  fetchAdminInventoryReservations,
  releaseAdminInventoryReservation,
} from "@/features/inventory/api/reservations-api";
import { fetchAdminWarehouses } from "@/features/inventory/api/warehouses-api";
import { ReservationActionConfirm } from "@/features/inventory/components/ReservationActionConfirm";
import type {
  InventoryReservation,
  ReservationListParams,
  WarehouseListItem,
} from "@/features/inventory/types/inventory-types";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type ReservationsResult = {
  key: string;
  reservations: InventoryReservation[];
  meta: PaginationMeta | null;
  error: string | null;
};

type PendingAction = {
  reservation: InventoryReservation;
  type: "release" | "cancel";
};

function readParams(searchParams: URLSearchParams): ReservationListParams {
  const status = searchParams.get("status");
  const allowed = ["active", "committed", "released", "expired", "cancelled"];
  return {
    status: allowed.includes(status ?? "")
      ? (status as ReservationListParams["status"])
      : "active",
    warehouse_id: searchParams.get("warehouse_id") ?? "",
    per_page: Number(searchParams.get("per_page") ?? 10) || 10,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

export function ReservationsListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(
    permissions,
    PERMISSIONS.INVENTORY_RESERVATIONS_MANAGE,
  );

  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = JSON.stringify(params);

  const [result, setResult] = useState<ReservationsResult | null>(null);
  const [warehouses, setWarehouses] = useState<WarehouseListItem[]>([]);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(
    null,
  );
  const [actionPending, setActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);

  const syncUrl = useCallback(
    (next: ReservationListParams) => {
      const qs = new URLSearchParams();
      if (next.status) qs.set("status", next.status);
      if (next.warehouse_id) qs.set("warehouse_id", String(next.warehouse_id));
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
    const fetchKey = `${requestKey}#${refreshNonce}`;
    void fetchAdminInventoryReservations(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          reservations: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          reservations: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load reservations.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [params, requestKey, refreshNonce]);

  const activeKey = `${requestKey}#${refreshNonce}`;
  const loading = result?.key !== activeKey;
  const reservations = result?.key === activeKey ? result.reservations : [];
  const meta = result?.key === activeKey ? result.meta : null;
  const error = result?.key === activeKey ? result.error : null;

  async function confirmAction() {
    if (!pendingAction || actionPending) return;
    setActionPending(true);
    setActionError(null);
    setActionNotice(null);
    try {
      if (pendingAction.type === "release") {
        await releaseAdminInventoryReservation(pendingAction.reservation.id);
        setActionNotice("Reservation released.");
      } else {
        await cancelAdminInventoryReservation(pendingAction.reservation.id);
        setActionNotice("Reservation cancelled.");
      }
      setPendingAction(null);
      setRefreshNonce((value) => value + 1);
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to update reservation.",
      );
    } finally {
      setActionPending(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Reservations"
        description="Active holds on stock for checkout or orders."
      />

      <AdminToolbar className="lg:grid-cols-12">
        <Field label="Status" className="lg:col-span-3">
          <Select
            value={params.status || "active"}
            onValueChange={(value) =>
              syncUrl({
                ...params,
                status: value as ReservationListParams["status"],
                page: 1,
              })
            }
          >
            <SelectTrigger id="reservation-status" aria-label="Status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="committed">Committed</SelectItem>
              <SelectItem value="released">Released</SelectItem>
              <SelectItem value="expired">Expired</SelectItem>
              <SelectItem value="cancelled">Cancelled</SelectItem>
            </SelectContent>
          </Select>
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
            <SelectTrigger id="reservation-warehouse" aria-label="Warehouse">
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
      </AdminToolbar>

      {pendingAction ? (
        <ReservationActionConfirm
          title={
            pendingAction.type === "release"
              ? "Release reservation?"
              : "Cancel reservation?"
          }
          description={`${pendingAction.reservation.reservation_key} · ${pendingAction.reservation.quantity} units at ${pendingAction.reservation.warehouse.name}.`}
          confirmLabel={
            pendingAction.type === "release"
              ? "Confirm release"
              : "Confirm cancel"
          }
          pending={actionPending}
          onConfirm={() => void confirmAction()}
          onCancel={() => setPendingAction(null)}
        />
      ) : null}

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
          Loading reservations…
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

      {!loading && !error && reservations.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm text-muted-foreground">
            No reservations match these filters.
          </p>
        </div>
      ) : null}

      {!loading && !error && reservations.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Key</th>
                <th className={adminThClassName()}>Warehouse</th>
                <th className={adminThClassName()}>SKU</th>
                <th className={adminThClassName()}>Qty</th>
                <th className={adminThClassName()}>Status</th>
                <th className={adminThClassName()}>Expires</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {reservations.map((reservation) => (
                <tr key={reservation.id} className="hover:bg-muted/30">
                  <td className={adminTdClassName()}>
                    {reservation.reservation_key}
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {reservation.warehouse.name}
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {reservation.variant.sku ?? "—"}
                  </td>
                  <td className={adminTdClassName()}>{reservation.quantity}</td>
                  <td className={adminTdClassName()}>
                    <StatusBadge status={reservation.status} />
                  </td>
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {reservation.expires_at ?? "—"}
                  </td>
                  <td className={adminTdClassName()}>
                    <div className="flex flex-wrap gap-2">
                      <Button asChild variant="outline" size="sm">
                        <Link
                          href={`/admin/inventory/${reservation.warehouse.id}/${reservation.variant.id}`}
                        >
                          Stock
                        </Link>
                      </Button>
                      {canManage && reservation.status === "active" ? (
                        <>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                              setPendingAction({
                                reservation,
                                type: "release",
                              })
                            }
                          >
                            Release
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() =>
                              setPendingAction({
                                reservation,
                                type: "cancel",
                              })
                            }
                          >
                            Cancel
                          </Button>
                        </>
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
