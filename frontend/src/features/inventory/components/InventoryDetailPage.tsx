"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  fetchAdminInventoryBalance,
  fetchAdminInventoryLedger,
} from "@/features/inventory/api/inventory-api";
import { AdjustDialog } from "@/features/inventory/components/AdjustDialog";
import { InventorySettingsDialog } from "@/features/inventory/components/InventorySettingsDialog";
import { ReceiptDialog } from "@/features/inventory/components/ReceiptDialog";
import { StockCountDialog } from "@/features/inventory/components/StockCountDialog";
import { TransferDialog } from "@/features/inventory/components/TransferDialog";
import type {
  InventoryBalanceRef,
  InventoryLedgerEntry,
} from "@/features/inventory/types/inventory-types";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import type { PaginationMeta } from "@/features/admin/types/admin-types";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import {
  AdminTable,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import { ApiClientError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

type ActiveDialog =
  | "receipt"
  | "adjust"
  | "count"
  | "transfer"
  | "settings"
  | null;

export function InventoryDetailPage({
  warehouseId,
  variantId,
}: {
  warehouseId: string;
  variantId: string;
}) {
  const { permissions } = useAdminContext();
  const canAdjust = hasPermission(permissions, PERMISSIONS.INVENTORY_ADJUST);
  const canTransfer = hasPermission(permissions, PERMISSIONS.INVENTORY_TRANSFER);
  const canManage = hasPermission(permissions, PERMISSIONS.INVENTORY_MANAGE);

  type DetailResult = {
    key: string;
    balance: InventoryBalanceRef | null;
    ledger: InventoryLedgerEntry[];
    ledgerMeta: PaginationMeta | null;
    error: string | null;
  };

  const [result, setResult] = useState<DetailResult | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [activeDialog, setActiveDialog] = useState<ActiveDialog>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);

  const fetchKey = `${warehouseId}:${variantId}#${refreshNonce}`;

  const reload = useCallback(() => {
    setRefreshNonce((value) => value + 1);
  }, []);

  useEffect(() => {
    let cancelled = false;

    void Promise.all([
      fetchAdminInventoryBalance(warehouseId, variantId),
      fetchAdminInventoryLedger(warehouseId, variantId, { per_page: 10 }),
    ])
      .then(([nextBalance, ledgerResponse]) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          balance: nextBalance,
          ledger: ledgerResponse.data,
          ledgerMeta: ledgerResponse.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: fetchKey,
          balance: null,
          ledger: [],
          ledgerMeta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load inventory detail.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [warehouseId, variantId, fetchKey]);

  const loading = result?.key !== fetchKey;
  const balance = result?.key === fetchKey ? result.balance : null;
  const ledger = result?.key === fetchKey ? (result.ledger ?? []) : [];
  const ledgerMeta = result?.key === fetchKey ? result.ledgerMeta : null;
  const error = result?.key === fetchKey ? result.error : null;

  function onMutationSuccess(message: string) {
    setActiveDialog(null);
    setNotice(message);
    reload();
  }

  const title =
    balance?.product.name ??
    (loading ? "Loading…" : `Variant #${variantId}`);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title={title}
        description={
          balance
            ? `${balance.warehouse.name} · ${balance.variant.sku ?? "SKU pending"}`
            : "Warehouse variant balance"
        }
        actions={
          <Button asChild variant="outline" size="sm">
            <Link href="/admin/inventory">Back to stock</Link>
          </Button>
        }
      />

      {notice ? (
        <p
          className="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
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

      {balance ? (
        <>
          <AdminPanel>
            <AdminPanelHeader
              title="Quantities"
              actions={
                <div className="flex flex-wrap gap-2">
                  {canAdjust ? (
                    <>
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => setActiveDialog("receipt")}
                      >
                        Receive
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setActiveDialog("adjust")}
                      >
                        Adjust
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setActiveDialog("count")}
                      >
                        Stock count
                      </Button>
                    </>
                  ) : null}
                  {canTransfer ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      onClick={() => setActiveDialog("transfer")}
                    >
                      Transfer
                    </Button>
                  ) : null}
                  {canManage ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      onClick={() => setActiveDialog("settings")}
                    >
                      Settings
                    </Button>
                  ) : null}
                </div>
              }
            />
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {(
                [
                  ["On hand", balance.quantities.on_hand],
                  ["Reserved", balance.quantities.reserved],
                  ["Unreserved", balance.quantities.unreserved],
                  ["Safety stock", balance.quantities.safety_stock],
                  ["Available to sell", balance.quantities.available_to_sell],
                  ["Reorder point", balance.quantities.reorder_point],
                ] as const
              ).map(([label, value]) => (
                <div key={label}>
                  <dt className="text-xs text-muted-foreground">{label}</dt>
                  <dd className="text-lg font-semibold tabular-nums">{value}</dd>
                </div>
              ))}
            </dl>
            <div className="mt-4 flex flex-wrap gap-2">
              {balance.status.out_of_stock ? (
                <Badge variant="danger">Out of stock</Badge>
              ) : null}
              {balance.status.low_stock ? (
                <Badge variant="secondary">Low stock</Badge>
              ) : null}
              <span className="text-xs text-muted-foreground">
                Version {balance.version}
                {balance.last_movement_at
                  ? ` · Last movement ${balance.last_movement_at}`
                  : null}
              </span>
            </div>
          </AdminPanel>

          {activeDialog === "receipt" && canAdjust ? (
            <ReceiptDialog
              warehouseId={balance.warehouse.id}
              variantId={balance.variant.id}
              onSuccess={onMutationSuccess}
              onCancel={() => setActiveDialog(null)}
            />
          ) : null}
          {activeDialog === "adjust" && canAdjust ? (
            <AdjustDialog
              warehouseId={balance.warehouse.id}
              variantId={balance.variant.id}
              expectedVersion={balance.version}
              onSuccess={onMutationSuccess}
              onCancel={() => setActiveDialog(null)}
            />
          ) : null}
          {activeDialog === "count" && canAdjust ? (
            <StockCountDialog
              warehouseId={balance.warehouse.id}
              variantId={balance.variant.id}
              currentOnHand={balance.quantities.on_hand}
              expectedVersion={balance.version}
              onSuccess={onMutationSuccess}
              onCancel={() => setActiveDialog(null)}
            />
          ) : null}
          {activeDialog === "transfer" && canTransfer ? (
            <TransferDialog
              sourceWarehouseId={balance.warehouse.id}
              variantId={balance.variant.id}
              onSuccess={onMutationSuccess}
              onCancel={() => setActiveDialog(null)}
            />
          ) : null}
          {activeDialog === "settings" && canManage ? (
            <InventorySettingsDialog
              warehouseId={balance.warehouse.id}
              variantId={balance.variant.id}
              safetyStock={balance.quantities.safety_stock}
              reorderPoint={balance.quantities.reorder_point}
              expectedVersion={balance.version}
              onSuccess={onMutationSuccess}
              onCancel={() => setActiveDialog(null)}
            />
          ) : null}

          <AdminPanel padding="none">
            <div className="border-b border-border/60 px-5 py-4 sm:px-6">
              <h2 className="text-base font-semibold">Ledger</h2>
              <p className="text-sm text-muted-foreground">
                Recent movements for this balance.
              </p>
            </div>
            {ledger.length === 0 ? (
              <p className="px-5 py-8 text-sm text-muted-foreground sm:px-6">
                No ledger entries yet.
              </p>
            ) : (
              <AdminTable>
                <table className={adminTableClassName()}>
                  <thead>
                    <tr>
                      <th className={adminThClassName()}>When</th>
                      <th className={adminThClassName()}>Type</th>
                      <th className={adminThClassName()}>Delta</th>
                      <th className={adminThClassName()}>On hand after</th>
                      <th className={adminThClassName()}>Reason</th>
                    </tr>
                  </thead>
                  <tbody>
                    {ledger.map((entry) => (
                      <tr key={entry.id}>
                        <td
                          className={`${adminTdClassName()} text-muted-foreground`}
                        >
                          {entry.occurred_at ?? entry.created_at ?? "—"}
                        </td>
                        <td className={adminTdClassName()}>
                          {entry.operation_type ?? entry.movement_type}
                        </td>
                        <td className={adminTdClassName()}>
                          {entry.quantity_delta > 0
                            ? `+${entry.quantity_delta}`
                            : entry.quantity_delta}
                        </td>
                        <td className={adminTdClassName()}>
                          {entry.on_hand_after}
                        </td>
                        <td
                          className={`${adminTdClassName()} text-muted-foreground`}
                        >
                          {entry.reason_code ?? "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </AdminTable>
            )}
            {ledgerMeta && ledgerMeta.last_page > 1 ? (
              <p className="px-5 py-3 text-xs text-muted-foreground sm:px-6">
                Showing page {ledgerMeta.current_page} of {ledgerMeta.last_page}
              </p>
            ) : null}
          </AdminPanel>
        </>
      ) : null}
    </div>
  );
}
