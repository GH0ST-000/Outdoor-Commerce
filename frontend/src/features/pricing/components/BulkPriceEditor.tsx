"use client";

import { FormEvent, useState } from "react";
import { bulkAdminPrices } from "@/features/pricing/api/prices-api";
import { parseMajorToMinor } from "@/features/pricing/lib/money";
import { mapApiFieldErrors } from "@/features/pricing/schemas/pricing-schemas";
import type { PriceList } from "@/features/pricing/types/pricing-types";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Textarea } from "@/components/ui/textarea";

type BulkRow = {
  variant_id: number;
  amount: string;
};

function parseBulkRows(
  raw: string,
  priceListId: number,
  currencyCode: string,
): {
  items: BulkRow[];
  errors: string | null;
  payloadItems: Array<{
    price_list_id: number;
    product_variant_id: number;
    amount_minor: number;
  }>;
} {
  const lines = raw
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);
  const payloadItems: Array<{
    price_list_id: number;
    product_variant_id: number;
    amount_minor: number;
  }> = [];

  for (const [index, line] of lines.entries()) {
    const [variantPart, amountPart] = line
      .split(/[,;\t]/)
      .map((part) => part.trim());
    const variant_id = Number(variantPart);
    if (!Number.isFinite(variant_id) || variant_id < 1) {
      return {
        items: [],
        errors: `Line ${index + 1}: invalid variant id.`,
        payloadItems: [],
      };
    }
    const parsed = parseMajorToMinor(amountPart ?? "", currencyCode);
    if (!parsed.ok) {
      return {
        items: [],
        errors: `Line ${index + 1}: invalid amount.`,
        payloadItems: [],
      };
    }
    payloadItems.push({
      price_list_id: priceListId,
      product_variant_id: variant_id,
      amount_minor: parsed.amount_minor,
    });
  }

  return { items: [], errors: null, payloadItems };
}

export function BulkPriceEditor({
  priceList,
  canManage,
  canPublish,
  onSuccess,
}: {
  priceList: PriceList | null;
  canManage: boolean;
  canPublish: boolean;
  onSuccess: (message: string) => void;
}) {
  const [raw, setRaw] = useState("");
  const [publish, setPublish] = useState(false);
  const [pending, setPending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending || !priceList) return;

    const parsed = parseBulkRows(raw, priceList.id, priceList.currency_code);
    if (parsed.errors) {
      setFormError(parsed.errors);
      return;
    }
    if (parsed.payloadItems.length === 0) {
      setFormError("Add at least one variant_id,amount line.");
      return;
    }

    setPending(true);
    setFormError(null);
    setFieldErrors({});
    try {
      const result = await bulkAdminPrices({
        items: parsed.payloadItems,
        publish: canPublish && publish,
      });
      onSuccess(
        `Bulk update complete (${result.created} created, ${result.updated} updated).`,
      );
      setRaw("");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setFieldErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to apply bulk prices.");
      }
    } finally {
      setPending(false);
    }
  }

  if (!priceList) {
    return (
      <p className="text-sm text-muted-foreground">
        Select a price list to use bulk editing.
      </p>
    );
  }

  if (!canManage) {
    return (
      <p className="text-sm text-muted-foreground">
        You do not have permission to bulk edit prices.
      </p>
    );
  }

  return (
    <AdminPanel>
      <AdminPanelHeader title="Bulk price editor" />
      <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
        <p className="text-sm text-muted-foreground">
          One line per variant: <code>variant_id, amount</code> in{" "}
          {priceList.currency_code}. Amounts use major units (e.g. 19.99).
        </p>
        <Field label="Rows" htmlFor="bulk-price-rows" error={fieldErrors.items}>
          <Textarea
            id="bulk-price-rows"
            rows={8}
            value={raw}
            onChange={(event) => setRaw(event.target.value)}
            placeholder={"101, 29.90\n102, 34.50"}
          />
        </Field>
        {canPublish ? (
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={publish}
              onChange={(event) => setPublish(event.target.checked)}
            />
            Publish immediately after bulk save
          </label>
        ) : null}
        {formError ? (
          <p
            className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            role="alert"
          >
            {formError}
          </p>
        ) : null}
        <Button type="submit" size="sm" disabled={pending}>
          {pending ? "Applying…" : "Apply bulk prices"}
        </Button>
      </form>
    </AdminPanel>
  );
}
