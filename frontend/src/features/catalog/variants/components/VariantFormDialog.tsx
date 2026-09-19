"use client";

import { FormEvent, useId, useState } from "react";
import type { AttributeValueListItem } from "@/features/catalog/attributes/types/attribute-types";
import type {
  ProductVariantListItem,
  ProductVariantStatus,
  ProductVariantWritePayload,
  VariantAxis,
} from "@/features/catalog/variants/types/variant-types";
import {
  createProductVariant,
  updateProductVariant,
} from "@/features/catalog/variants/api/variants-api";
import { variantFormSchema } from "@/features/catalog/variants/schemas/variant-schemas";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
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

type Draft = {
  sku: string;
  barcode: string;
  status: ProductVariantStatus;
  is_default: boolean;
  attribute_values: Record<string, number | null>;
};

function draftFrom(
  axes: VariantAxis[],
  variant: ProductVariantListItem | null,
): Draft {
  const attributeValues: Record<string, number | null> = {};
  for (const axis of axes) {
    const assigned = variant?.attribute_values.find(
      (pair) => pair.attribute_id === axis.attribute_id,
    );
    attributeValues[String(axis.attribute_id)] =
      assigned?.attribute_value_id ?? null;
  }

  return {
    sku: variant?.sku ?? "",
    barcode: variant?.barcode ?? "",
    status: variant?.status ?? "draft",
    is_default: variant?.is_default ?? false,
    attribute_values: attributeValues,
  };
}

export function VariantFormDialog({
  productId,
  axes,
  valuesByAxis,
  variant,
  canPublish,
  onSaved,
  onCancel,
}: {
  productId: number | string;
  axes: VariantAxis[];
  valuesByAxis: Record<number, AttributeValueListItem[]>;
  variant: ProductVariantListItem | null;
  canPublish: boolean;
  onSaved: (message: string) => void;
  onCancel: () => void;
}) {
  const fieldId = useId();
  const [draft, setDraft] = useState<Draft>(() => draftFrom(axes, variant));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const statusOptions: ProductVariantStatus[] =
    canPublish || draft.status === "active" ? ["draft", "active"] : ["draft"];

  function buildPayload(): ProductVariantWritePayload {
    const payload: ProductVariantWritePayload = {
      barcode: draft.barcode.trim() || null,
      status: draft.status,
      is_default: draft.is_default,
      attribute_values: axes.map((axis) => ({
        attribute_id: axis.attribute_id,
        attribute_value_id: Number(
          draft.attribute_values[String(axis.attribute_id)],
        ),
      })),
    };

    // Omitting the SKU lets the backend generate one.
    if (draft.sku.trim() !== "") {
      payload.sku = draft.sku.trim().toUpperCase();
    }

    return payload;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending) return;

    setFormError(null);

    const parsed = variantFormSchema(
      axes.map((axis) => axis.attribute_id),
    ).safeParse(draft);

    if (!parsed.success) {
      const next: Record<string, string> = {};
      for (const issue of parsed.error.issues) {
        const key = issue.path.join(".");
        if (key && !next[key]) {
          next[key] = issue.message;
        }
      }
      setErrors(next);
      return;
    }

    setErrors({});
    setPending(true);
    try {
      if (variant) {
        await updateProductVariant(productId, variant.id, buildPayload());
        onSaved("Variant saved.");
      } else {
        await createProductVariant(productId, buildPayload());
        onSaved("Variant created.");
      }
    } catch (err) {
      if (err instanceof ApiClientError) {
        const mapped: Record<string, string> = {};
        for (const [key, messages] of Object.entries(err.details ?? {})) {
          if (messages[0]) {
            mapped[key] = messages[0];
          }
        }
        setErrors(mapped);
        setFormError(err.message);
      } else {
        setFormError("Unable to save the variant.");
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <AdminPanel className="border-primary/30">
      <AdminPanelHeader
        title={variant ? "Edit variant" : "New variant"}
        description="Every axis needs one value. Leave the SKU blank to generate one automatically."
      />

      {formError ? (
        <p
          className="mb-4 rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {formError}
        </p>
      ) : null}

      <form className="space-y-4" onSubmit={onSubmit} noValidate>
        <div className="grid gap-4 md:grid-cols-2">
          {axes.map((axis) => {
            const values = (valuesByAxis[axis.attribute_id] ?? []).filter(
              (value) =>
                value.status === "active" ||
                value.id === draft.attribute_values[String(axis.attribute_id)],
            );
            const current = draft.attribute_values[String(axis.attribute_id)];
            const label = axis.name ?? axis.code;
            return (
              <Field
                key={axis.attribute_id}
                label={label}
                htmlFor={`${fieldId}-axis-${axis.attribute_id}`}
                error={errors[`attribute_values.${axis.attribute_id}`]}
              >
                <Select
                  value={current == null ? "" : String(current)}
                  onValueChange={(value) =>
                    setDraft((state) => ({
                      ...state,
                      attribute_values: {
                        ...state.attribute_values,
                        [String(axis.attribute_id)]: Number(value),
                      },
                    }))
                  }
                >
                  <SelectTrigger
                    id={`${fieldId}-axis-${axis.attribute_id}`}
                    aria-label={label}
                  >
                    <SelectValue placeholder="Select a value" />
                  </SelectTrigger>
                  <SelectContent>
                    {values.map((value) => (
                      <SelectItem key={value.id} value={String(value.id)}>
                        {value.name ?? value.code}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </Field>
            );
          })}

          <Field
            label="SKU"
            htmlFor={`${fieldId}-sku`}
            hint="Leave blank to auto-generate."
            error={errors.sku}
          >
            <Input
              id={`${fieldId}-sku`}
              value={draft.sku}
              onChange={(event) =>
                setDraft((state) => ({ ...state, sku: event.target.value }))
              }
              aria-invalid={Boolean(errors.sku)}
            />
          </Field>
          <Field
            label="Barcode"
            htmlFor={`${fieldId}-barcode`}
            error={errors.barcode}
          >
            <Input
              id={`${fieldId}-barcode`}
              value={draft.barcode}
              onChange={(event) =>
                setDraft((state) => ({ ...state, barcode: event.target.value }))
              }
              aria-invalid={Boolean(errors.barcode)}
            />
          </Field>
          <Field
            label="Status"
            htmlFor={`${fieldId}-status`}
            hint={
              !canPublish
                ? "Activate is hidden without catalog.publish."
                : undefined
            }
            error={errors.status}
          >
            <Select
              value={draft.status}
              onValueChange={(value) =>
                setDraft((state) => ({
                  ...state,
                  status: value as ProductVariantStatus,
                }))
              }
            >
              <SelectTrigger
                id={`${fieldId}-status`}
                aria-label="Variant status"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {statusOptions.map((status) => (
                  <SelectItem key={status} value={status}>
                    {status}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-end pb-1">
            <div className="flex items-center gap-2">
              <Switch
                id={`${fieldId}-default`}
                checked={draft.is_default}
                onCheckedChange={(checked) =>
                  setDraft((state) => ({ ...state, is_default: checked }))
                }
              />
              <Label htmlFor={`${fieldId}-default`} className="text-sm">
                Default variant
              </Label>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button type="submit" size="sm" disabled={pending}>
            {pending ? "Saving…" : variant ? "Save variant" : "Create variant"}
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
