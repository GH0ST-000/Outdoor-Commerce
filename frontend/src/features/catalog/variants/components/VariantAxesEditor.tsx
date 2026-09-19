"use client";

import { useState } from "react";
import type { AttributeListItem } from "@/features/catalog/attributes/types/attribute-types";
import type {
  ProductVariantAxes,
  VariantAxis,
} from "@/features/catalog/variants/types/variant-types";
import { saveProductVariantAxes } from "@/features/catalog/variants/api/variants-api";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

function idsFromAxes(axes: VariantAxis[]): number[] {
  return [...axes]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((axis) => axis.attribute_id);
}

/** Conflict responses carry attribute/variant ids instead of field messages. */
function conflictIds(error: ApiClientError, key: string): number[] {
  const details = error.details as unknown as Record<string, unknown>;
  const value = details?.[key];
  return Array.isArray(value) ? value.map((item) => Number(item)) : [];
}

export function VariantAxesEditor({
  productId,
  axes,
  attributes,
  canManage,
  onSaved,
}: {
  productId: number | string;
  axes: VariantAxis[];
  attributes: AttributeListItem[];
  canManage: boolean;
  onSaved: (next: ProductVariantAxes) => void;
}) {
  const [selected, setSelected] = useState<number[]>(() => idsFromAxes(axes));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [conflicts, setConflicts] = useState<number[]>([]);
  const [notice, setNotice] = useState<string | null>(null);

  function toggle(attributeId: number) {
    setSelected((current) =>
      current.includes(attributeId)
        ? current.filter((id) => id !== attributeId)
        : [...current, attributeId],
    );
  }

  function move(attributeId: number, delta: number) {
    setSelected((current) => {
      const index = current.indexOf(attributeId);
      const target = index + delta;
      if (index === -1 || target < 0 || target >= current.length) {
        return current;
      }
      const next = [...current];
      next[index] = next[target];
      next[target] = attributeId;
      return next;
    });
  }

  async function onSave() {
    if (pending || !canManage) return;
    setPending(true);
    setError(null);
    setConflicts([]);
    setNotice(null);
    try {
      const saved = await saveProductVariantAxes(
        productId,
        selected.map((attributeId, index) => ({
          attribute_id: attributeId,
          sort_order: index,
        })),
      );
      onSaved(saved);
      setNotice("Variant axes saved.");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setError(err.message);
        setConflicts(conflictIds(err, "variant_ids"));
      } else {
        setError("Unable to save variant axes.");
      }
    } finally {
      setPending(false);
    }
  }

  const selectable = attributes.filter(
    (attribute) =>
      attribute.status === "active" || selected.includes(attribute.id),
  );

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Variant axes"
        description="Pick the attributes that define this product's variants. Order controls how combinations are labeled."
      />

      {error ? (
        <p
          className="mb-4 rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {error}
          {conflicts.length > 0
            ? ` Affected variants: ${conflicts.map((id) => `#${id}`).join(", ")}.`
            : ""}
        </p>
      ) : null}
      {notice ? (
        <p
          className="mb-4 rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      {selectable.length === 0 ? (
        <p className="text-sm text-muted-foreground" role="status">
          No active attributes are available yet. Create and activate attributes
          first.
        </p>
      ) : (
        <div className="space-y-4">
          <div className="grid gap-2 sm:grid-cols-2">
            {selectable.map((attribute) => (
              <label
                key={attribute.id}
                className="flex items-center gap-2 text-sm"
              >
                <input
                  type="checkbox"
                  checked={selected.includes(attribute.id)}
                  disabled={!canManage || pending}
                  onChange={() => toggle(attribute.id)}
                />
                <span>{attribute.name ?? attribute.code}</span>
                <span className="font-mono text-xs text-muted-foreground">
                  {attribute.code}
                </span>
                {attribute.status !== "active" ? (
                  <Badge variant="warning">{attribute.status}</Badge>
                ) : null}
              </label>
            ))}
          </div>

          {selected.length > 0 ? (
            <ol
              className="space-y-2"
              aria-label="Selected axes order"
              data-testid="axes-order"
            >
              {selected.map((attributeId, index) => {
                const attribute = attributes.find(
                  (item) => item.id === attributeId,
                );
                const label =
                  attribute?.name ??
                  attribute?.code ??
                  `Attribute #${attributeId}`;
                return (
                  <li
                    key={attributeId}
                    className="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/20 px-3 py-2 text-sm"
                  >
                    <span>
                      {index + 1}. {label}
                    </span>
                    <span className="flex gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!canManage || pending || index === 0}
                        aria-label={`Move ${label} up`}
                        onClick={() => move(attributeId, -1)}
                      >
                        ↑
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={
                          !canManage || pending || index === selected.length - 1
                        }
                        aria-label={`Move ${label} down`}
                        onClick={() => move(attributeId, 1)}
                      >
                        ↓
                      </Button>
                    </span>
                  </li>
                );
              })}
            </ol>
          ) : null}

          {canManage ? (
            <Button
              type="button"
              size="sm"
              disabled={pending}
              onClick={() => void onSave()}
            >
              {pending ? "Saving…" : "Save axes"}
            </Button>
          ) : (
            <p className="text-sm text-muted-foreground">
              You need catalog.manage to change variant axes.
            </p>
          )}
        </div>
      )}
    </AdminPanel>
  );
}
