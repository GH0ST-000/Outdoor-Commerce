"use client";

import { FormEvent, useState } from "react";
import { postAdminInventoryStockCount } from "@/features/inventory/api/inventory-api";
import { mapApiFieldErrors } from "@/features/inventory/schemas/inventory-schemas";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

export function StockCountDialog({
  warehouseId,
  variantId,
  currentOnHand,
  expectedVersion,
  onSuccess,
  onCancel,
}: {
  warehouseId: number;
  variantId: number;
  currentOnHand: number;
  expectedVersion: number;
  onSuccess: (message: string) => void;
  onCancel: () => void;
}) {
  const [countedQuantity, setCountedQuantity] = useState(String(currentOnHand));
  const [note, setNote] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const counted = Number(countedQuantity);
    const nextErrors: Record<string, string> = {};
    if (!Number.isFinite(counted) || counted < 0) {
      nextErrors.counted_quantity = "Counted quantity must be zero or greater.";
    }
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setPending(true);
    setFormError(null);
    try {
      await postAdminInventoryStockCount({
        warehouse_id: warehouseId,
        product_variant_id: variantId,
        counted_quantity: counted,
        reason_code: "stock_count_correction",
        note: note.trim() || null,
        expected_version: expectedVersion,
      });
      onSuccess("Stock count reconciled.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to reconcile stock count.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Stock count"
        description={`Set the counted on-hand quantity (current on hand: ${currentOnHand}).`}
      />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <Field
          label="Counted quantity"
          htmlFor="count-quantity"
          error={errors.counted_quantity}
        >
          <Input
            id="count-quantity"
            type="number"
            min={0}
            value={countedQuantity}
            onChange={(event) => setCountedQuantity(event.target.value)}
          />
        </Field>
        <Field label="Note" htmlFor="count-note">
          <Textarea
            id="count-note"
            value={note}
            onChange={(event) => setNote(event.target.value)}
            rows={3}
          />
        </Field>
        {formError ? (
          <p className="text-sm text-destructive" role="alert">
            {formError}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-2">
          <Button type="submit" size="sm" disabled={pending}>
            {pending ? "Saving…" : "Reconcile count"}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={pending}
            onClick={onCancel}
          >
            Cancel
          </Button>
        </div>
      </form>
    </AdminPanel>
  );
}
