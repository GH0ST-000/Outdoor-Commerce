"use client";

import { useEffect, useState } from "react";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import type { ProductDetailFixture } from "@/features/storefront/types/storefront-types";
import {
  ColorSwatch,
  SizeOption,
  VariantGroup,
} from "@/components/commerce/variant-option";

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

  useEffect(() => {
    onChange?.(selected);
    // Sync the parent once after the default combination is chosen.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
      {axes.map((axis) => {
        const summary = axis.values.find(
          (value) => value.id === selected[axis.id],
        )?.name[locale];
        return (
          <VariantGroup
            key={axis.id}
            legend={axis.name[locale]}
            summary={summary}
          >
            {axis.values.map((value) =>
              axis.type === "color" ? (
                <ColorSwatch
                  key={value.id}
                  label={value.name[locale]}
                  color={value.colorHex}
                  selected={selected[axis.id] === value.id}
                  disabled={value.disabled}
                  onClick={() => choose(axis.id, value.id)}
                />
              ) : (
                <SizeOption
                  key={value.id}
                  label={value.name[locale]}
                  selected={selected[axis.id] === value.id}
                  disabled={value.disabled}
                  onClick={() => choose(axis.id, value.id)}
                />
              ),
            )}
          </VariantGroup>
        );
      })}
    </div>
  );
}
