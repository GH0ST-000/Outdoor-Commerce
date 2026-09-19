"use client";

import { useState } from "react";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import type { ProductDetailFixture } from "@/features/storefront/types/storefront-types";
import { cn } from "@/lib/utils";

type Axis = ProductDetailFixture["variants"]["axes"][number];

export function VariantSelector({
  axes,
  onChange,
}: {
  axes: Axis[];
  onChange?: (selected: Record<string, string>) => void;
}) {
  const { locale, t } = useStorefrontCopy();
  const [selected, setSelected] = useState<Record<string, string>>(() => {
    const initial: Record<string, string> = {};
    for (const axis of axes) {
      const first = axis.values.find((value) => !value.disabled);
      if (first) initial[axis.id] = first.id;
    }
    return initial;
  });

  function choose(axisId: string, valueId: string) {
    const next = { ...selected, [axisId]: valueId };
    setSelected(next);
    onChange?.(next);
  }

  if (axes.length === 0) {
    return null;
  }

  return (
    <div className="space-y-5" aria-label={t.product.selectVariant}>
      {axes.map((axis) => (
        <fieldset key={axis.id} className="space-y-2">
          <legend className="text-sm font-medium">
            {axis.name[locale]}
            {selected[axis.id] ? (
              <span className="ml-2 font-normal text-muted-foreground">
                {
                  axis.values.find((value) => value.id === selected[axis.id])
                    ?.name[locale]
                }
              </span>
            ) : null}
          </legend>
          <div className="flex flex-wrap gap-2">
            {axis.values.map((value) => {
              const isSelected = selected[axis.id] === value.id;
              if (axis.type === "color") {
                return (
                  <button
                    key={value.id}
                    type="button"
                    disabled={value.disabled}
                    aria-pressed={isSelected}
                    aria-label={value.name[locale]}
                    title={value.name[locale]}
                    onClick={() => choose(axis.id, value.id)}
                    className={cn(
                      "inline-flex size-10 items-center justify-center rounded-full border-2 transition-transform duration-[var(--duration-micro)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40",
                      isSelected
                        ? "border-foreground scale-105"
                        : "border-transparent",
                    )}
                  >
                    <span
                      className="size-7 rounded-full border border-black/15"
                      style={{ backgroundColor: value.colorHex ?? "#888" }}
                      aria-hidden
                    />
                  </button>
                );
              }
              return (
                <button
                  key={value.id}
                  type="button"
                  disabled={value.disabled}
                  aria-pressed={isSelected}
                  onClick={() => choose(axis.id, value.id)}
                  className={cn(
                    "min-w-12 rounded-lg border px-3 py-2 text-sm font-medium transition-colors duration-[var(--duration-micro)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40",
                    isSelected
                      ? "border-foreground bg-foreground text-background"
                      : "border-border bg-card hover:bg-muted",
                  )}
                >
                  {value.name[locale]}
                </button>
              );
            })}
          </div>
        </fieldset>
      ))}
    </div>
  );
}
