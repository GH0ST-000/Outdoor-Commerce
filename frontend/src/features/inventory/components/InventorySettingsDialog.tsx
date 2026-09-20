"use client";

import { FormEvent, useState } from "react";
import { updateAdminInventorySettings } from "@/features/inventory/api/inventory-api";
import { mapApiFieldErrors } from "@/features/inventory/schemas/inventory-schemas";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

export function InventorySettingsDialog({
  warehouseId,
  variantId,
  safetyStock,
  reorderPoint,
  expectedVersion,
  onSuccess,
  onCancel,
}: {
  warehouseId: number;
  variantId: number;
  safetyStock: number;
  reorderPoint: number;
  expectedVersion: number;
  onSuccess: (message: string) => void;
  onCancel: () => void;
}) {
  const [safety, setSafety] = useState(String(safetyStock));
  const [reorder, setReorder] = useState(String(reorderPoint));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const safetyValue = Number(safety);
    const reorderValue = Number(reorder);
    const nextErrors: Record<string, string> = {};
    if (!Number.isFinite(safetyValue) || safetyValue < 0) {
      nextErrors.safety_stock = "Safety stock must be zero or greater.";
    }
    if (!Number.isFinite(reorderValue) || reorderValue < 0) {
      nextErrors.reorder_point = "Reorder point must be zero or greater.";
    }
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setPending(true);
    setFormError(null);
    try {
      await updateAdminInventorySettings(warehouseId, variantId, {
        safety_stock: safetyValue,
        reorder_point: reorderValue,
        expected_version: expectedVersion,
      });
      onSuccess("Inventory settings updated.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to update settings.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Inventory settings"
        description="Configure safety stock and reorder point for availability calculations."
      />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <Field
          label="Safety stock"
          htmlFor="settings-safety"
          error={errors.safety_stock}
        >
          <Input
            id="settings-safety"
            type="number"
            min={0}
            value={safety}
            onChange={(event) => setSafety(event.target.value)}
          />
        </Field>
        <Field
          label="Reorder point"
          htmlFor="settings-reorder"
          error={errors.reorder_point}
        >
          <Input
            id="settings-reorder"
            type="number"
            min={0}
            value={reorder}
            onChange={(event) => setReorder(event.target.value)}
          />
        </Field>
        {formError ? (
          <p className="text-sm text-destructive" role="alert">
            {formError}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-2">
          <Button type="submit" size="sm" disabled={pending}>
            {pending ? "Saving…" : "Save settings"}
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
