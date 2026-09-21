"use client";

import { FormEvent, useState } from "react";
import { postAdminInventoryReceipt } from "@/features/inventory/api/inventory-api";
import {
  mapApiFieldErrors,
  validateReceiptForm,
} from "@/features/inventory/schemas/inventory-schemas";
import type { InventoryReasonCode } from "@/features/inventory/types/inventory-types";
import {
  INVENTORY_REASON_OPTIONS,
  reasonCodeRequiresNote,
} from "@/features/inventory/utils/reason-code-labels";
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

export function ReceiptDialog({
  warehouseId,
  variantId,
  onSuccess,
  onCancel,
}: {
  warehouseId: number;
  variantId: number;
  onSuccess: (message: string) => void;
  onCancel: () => void;
}) {
  const [quantity, setQuantity] = useState("1");
  const [reasonCode, setReasonCode] =
    useState<InventoryReasonCode>("supplier_receipt");
  const [note, setNote] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const nextErrors = validateReceiptForm({
      quantity,
      reason_code: reasonCode,
      note,
    });
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setPending(true);
    setFormError(null);
    try {
      await postAdminInventoryReceipt({
        warehouse_id: warehouseId,
        items: [
          {
            product_variant_id: variantId,
            quantity: Number(quantity),
          },
        ],
        reason_code: reasonCode,
        note: note.trim() || null,
      });
      onSuccess("Stock received.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to record receipt.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Receive stock"
        description="Increase on-hand quantity for this variant at the selected warehouse."
      />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <Field
          label="Quantity"
          htmlFor="receipt-quantity"
          error={errors.quantity}
        >
          <Input
            id="receipt-quantity"
            type="number"
            min={1}
            value={quantity}
            onChange={(event) => setQuantity(event.target.value)}
          />
        </Field>
        <Field label="Reason" error={errors.reason_code}>
          <Select
            value={reasonCode}
            onValueChange={(value) =>
              setReasonCode(value as InventoryReasonCode)
            }
          >
            <SelectTrigger id="receipt-reason" aria-label="Reason">
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
        <Field
          label={
            reasonCodeRequiresNote(reasonCode) ? "Note (required)" : "Note"
          }
          htmlFor="receipt-note"
          error={errors.note}
        >
          <Textarea
            id="receipt-note"
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
            {pending ? "Saving…" : "Confirm receipt"}
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
