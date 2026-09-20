"use client";

import { FormEvent, useEffect, useState } from "react";
import { postAdminInventoryTransfer } from "@/features/inventory/api/inventory-api";
import { fetchAdminWarehouses } from "@/features/inventory/api/warehouses-api";
import {
  mapApiFieldErrors,
  validateTransferForm,
} from "@/features/inventory/schemas/inventory-schemas";
import type { WarehouseListItem } from "@/features/inventory/types/inventory-types";
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

export function TransferDialog({
  sourceWarehouseId,
  variantId,
  onSuccess,
  onCancel,
}: {
  sourceWarehouseId: number;
  variantId: number;
  onSuccess: (message: string) => void;
  onCancel: () => void;
}) {
  const [warehouses, setWarehouses] = useState<WarehouseListItem[]>([]);
  const [sourceId, setSourceId] = useState(String(sourceWarehouseId));
  const [destinationId, setDestinationId] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [note, setNote] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

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

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const nextErrors = validateTransferForm({
      source_warehouse_id: sourceId,
      destination_warehouse_id: destinationId,
      quantity,
    });
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setPending(true);
    setFormError(null);
    try {
      await postAdminInventoryTransfer({
        source_warehouse_id: Number(sourceId),
        destination_warehouse_id: Number(destinationId),
        items: [{ product_variant_id: variantId, quantity: Number(quantity) }],
        note: note.trim() || null,
      });
      onSuccess("Transfer completed.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to transfer stock.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Transfer stock"
        description="Move quantity from one warehouse to another."
      />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <Field label="Source warehouse" error={errors.source_warehouse_id}>
          <Select value={sourceId} onValueChange={setSourceId}>
            <SelectTrigger id="transfer-source" aria-label="Source warehouse">
              <SelectValue placeholder="Select source" />
            </SelectTrigger>
            <SelectContent>
              {warehouses.map((warehouse) => (
                <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                  {warehouse.name} ({warehouse.code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field
          label="Destination warehouse"
          error={errors.destination_warehouse_id}
        >
          <Select value={destinationId} onValueChange={setDestinationId}>
            <SelectTrigger
              id="transfer-destination"
              aria-label="Destination warehouse"
            >
              <SelectValue placeholder="Select destination" />
            </SelectTrigger>
            <SelectContent>
              {warehouses.map((warehouse) => (
                <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                  {warehouse.name} ({warehouse.code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field
          label="Quantity"
          htmlFor="transfer-quantity"
          error={errors.quantity}
        >
          <Input
            id="transfer-quantity"
            type="number"
            min={1}
            value={quantity}
            onChange={(event) => setQuantity(event.target.value)}
          />
        </Field>
        <Field label="Note" htmlFor="transfer-note">
          <Textarea
            id="transfer-note"
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
            {pending ? "Saving…" : "Confirm transfer"}
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
