"use client";

import { useState } from "react";
import type { AttributeValueListItem } from "@/features/catalog/attributes/types/attribute-types";
import type {
  VariantAxis,
  VariantAxisSelection,
  VariantGenerationPreview,
} from "@/features/catalog/variants/types/variant-types";
import {
  generateProductVariants,
  previewProductVariantGeneration,
} from "@/features/catalog/variants/api/variants-api";
import { ColorSwatch } from "@/features/catalog/attributes/components/ColorSwatch";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";

export function CombinationBuilder({
  productId,
  axes,
  valuesByAxis,
  canManage,
  onGenerated,
}: {
  productId: number | string;
  axes: VariantAxis[];
  valuesByAxis: Record<number, AttributeValueListItem[]>;
  canManage: boolean;
  onGenerated: (message: string) => void;
}) {
  const [selection, setSelection] = useState<Record<number, number[]>>({});
  const [preview, setPreview] = useState<VariantGenerationPreview | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);

  function toggleValue(attributeId: number, valueId: number) {
    setPreview(null);
    setConfirming(false);
    setSelection((current) => {
      const existing = current[attributeId] ?? [];
      const next = existing.includes(valueId)
        ? existing.filter((id) => id !== valueId)
        : [...existing, valueId];
      return { ...current, [attributeId]: next };
    });
  }

  function buildSelection(): VariantAxisSelection[] {
    return axes.map((axis) => ({
      attribute_id: axis.attribute_id,
      attribute_value_ids: selection[axis.attribute_id] ?? [],
    }));
  }

  const everyAxisHasValue = axes.every(
    (axis) => (selection[axis.attribute_id] ?? []).length > 0,
  );

  async function onPreview() {
    if (pending) return;
    setPending(true);
    setError(null);
    setConfirming(false);
    try {
      setPreview(
        await previewProductVariantGeneration(productId, buildSelection()),
      );
    } catch (err) {
      setPreview(null);
      setError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to preview combinations.",
      );
    } finally {
      setPending(false);
    }
  }

  async function onGenerate() {
    if (pending || !canManage) return;
    setPending(true);
    setError(null);
    try {
      const result = await generateProductVariants(
        productId,
        buildSelection(),
        "draft",
      );
      setConfirming(false);
      setPreview(null);
      setSelection({});
      onGenerated(
        result.summary
          ? `Generated ${result.summary.created} variants (${result.summary.skipped} skipped).`
          : `Generated ${result.created.length} variants.`,
      );
    } catch (err) {
      setError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to generate variants.",
      );
    } finally {
      setPending(false);
    }
  }

  if (axes.length === 0) {
    return null;
  }

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Generate combinations"
        description="Pick the values to combine on each axis. Preview before generating; existing combinations are skipped."
      />

      {error ? (
        <p
          className="mb-4 rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      <div className="space-y-4">
        {axes.map((axis) => {
          const values = (valuesByAxis[axis.attribute_id] ?? []).filter(
            (value) => value.status === "active",
          );
          const chosen = selection[axis.attribute_id] ?? [];
          return (
            <fieldset
              key={axis.attribute_id}
              className="rounded-xl border border-border/70 bg-muted/20 p-3"
            >
              <legend className="px-1 text-sm font-medium">
                {axis.name ?? axis.code}
              </legend>
              {values.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No active values on this axis yet.
                </p>
              ) : (
                <div className="grid gap-2 sm:grid-cols-3">
                  {values.map((value) => (
                    <label
                      key={value.id}
                      className="flex items-center gap-2 text-sm"
                    >
                      <input
                        type="checkbox"
                        checked={chosen.includes(value.id)}
                        disabled={!canManage || pending}
                        onChange={() =>
                          toggleValue(axis.attribute_id, value.id)
                        }
                      />
                      <span>{value.name ?? value.code}</span>
                      {axis.type === "color" && value.color_hex ? (
                        <ColorSwatch hex={value.color_hex} />
                      ) : null}
                    </label>
                  ))}
                </div>
              )}
            </fieldset>
          );
        })}
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={pending || !everyAxisHasValue}
          onClick={() => void onPreview()}
        >
          {pending ? "Working…" : "Preview combinations"}
        </Button>
        {canManage && preview && !preview.exceeds_limit ? (
          confirming ? (
            <>
              <Button
                type="button"
                size="sm"
                disabled={pending}
                onClick={() => void onGenerate()}
              >
                Confirm generate
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={pending}
                onClick={() => setConfirming(false)}
              >
                Cancel
              </Button>
            </>
          ) : (
            <Button
              type="button"
              size="sm"
              disabled={pending || preview.new_count === 0}
              onClick={() => setConfirming(true)}
            >
              Generate variants
            </Button>
          )
        ) : null}
      </div>

      {preview ? (
        <div className="mt-4 space-y-2" data-testid="preview-summary">
          <p className="text-sm">
            Total combinations: <strong>{preview.total_combinations}</strong> ·
            Existing: <strong>{preview.existing_count}</strong> · New:{" "}
            <strong>{preview.new_count}</strong> · Limit:{" "}
            <strong>{preview.limit}</strong>
          </p>
          {preview.exceeds_limit ? (
            <p className="text-sm text-destructive" role="alert">
              This selection exceeds the {preview.limit} combination limit per
              generation. Narrow the selection and try again.
            </p>
          ) : null}
          {preview.truncated && !preview.exceeds_limit ? (
            <p className="text-sm text-muted-foreground">
              Only the first {preview.limit} combinations are listed.
            </p>
          ) : null}
          {confirming ? (
            <p className="text-sm font-medium" role="status">
              Generate {preview.new_count} new draft variants?
            </p>
          ) : null}
        </div>
      ) : null}
    </AdminPanel>
  );
}
