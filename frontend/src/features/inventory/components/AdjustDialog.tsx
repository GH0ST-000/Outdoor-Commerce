"use client";

import { FormEvent, useState } from "react";
import { postAdminInventoryAdjustment } from "@/features/inventory/api/inventory-api";
import { mapApiFieldErrors } from "@/features/inventory/schemas/inventory-schemas";
import type { InventoryReasonCode } from "@/features/inventory/types/inventory-types";
import { INVENTORY_REASON_OPTIONS } from "@/features/inventory/utils/reason-code-labels";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
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
import { Textarea } from "@/components/ui/textarea";

export function AdjustDialog({
  warehouseId,
  variantId,
  expectedVersion,
  onSuccess,
  onCancel,
}: {
  warehouseId: number;
  variantId: number;
  expectedVersion: number;
  onSuccess: (message: string) => void;
  onCancel: () => void;
}) {
  const [quantityDelta, setQuantityDelta] = useState("0");
  const [reasonCode, setReasonCode] =
    useState<InventoryReasonCode>("manual_correction");
  const [note, setNote] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const delta = Number(quantityDelta);
    const nextErrors: Record<string, string> = {};
    if (!Number.isFinite(delta) || delta === 0) {
      nextErrors.quantity_delta = "Enter a non-zero adjustment.";
    }
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setPending(true);
    setFormError(null);
    try {
      await postAdminInventoryAdjustment({
        warehouse_id: warehouseId,
        product_variant_id: variantId,
        quantity_delta: delta,
        reason_code: reasonCode,
        note: note.trim() || null,
        expected_version: expectedVersion,
      });
      onSuccess("Inventory adjusted.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to adjust inventory.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Adjust stock"
        description="Apply a signed quantity change (positive or negative)."
      />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <Field
          label="Quantity delta"
          htmlFor="adjust-delta"
          error={errors.quantity_delta}
        >
          <Input
            id="adjust-delta"
            type="number"
            value={quantityDelta}
            onChange={(event) => setQuantityDelta(event.target.value)}
          />
        </Field>
        <Field label="Reason" error={errors.reason_code}>
          <Select
            value={reasonCode}
            onValueChange={(value) =>
              setReasonCode(value as InventoryReasonCode)
            }
          >
            <SelectTrigger id="adjust-reason" aria-label="Reason">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {INVENTORY_REASON_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field label="Note" htmlFor="adjust-note">
          <Textarea
            id="adjust-note"
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
            {pending ? "Saving…" : "Apply adjustment"}
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
