"use client";

import {
  ColorSwatch,
  SizeOption,
  VariantGroup,
} from "@/components/commerce/variant-option";
import type { PublicVariantAxis } from "@/features/catalog/types/public-catalog";
import { valueAvailability } from "@/features/product-detail/state/variant-resolver";
import type {
  VariantIndex,
  VariantSelection,
} from "@/features/product-detail/types";

export function ProductVariantSelector({
  axes,
  index,
  selection,
  onSelect,
  invalidLabel,
  outOfStockLabel,
}: {
  axes: PublicVariantAxis[];
  index: VariantIndex;
  selection: VariantSelection;
  onSelect: (axisCode: string, valueCode: string) => void;
  invalidLabel: string;
  outOfStockLabel: string;
}) {
  if (axes.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4">
      {axes.map((axis) => {
        const selected = axis.values.find(
          (value) => selection[axis.code] === value.code,
        );
        return (
          <VariantGroup
            key={axis.code}
            legend={axis.name}
            summary={selected?.name}
          >
            {axis.values.map((value) => {
              const state = valueAvailability(
                index,
                selection,
                axis.code,
                value.code,
              );
              const selectedNow = selection[axis.code] === value.code;
              const name =
                state === "invalid"
                  ? `${value.name}, ${invalidLabel}`
                  : state === "out_of_stock"
                    ? `${value.name}, ${outOfStockLabel}`
                    : value.name;

              if (axis.type === "color") {
                return (
                  <ColorSwatch
                    key={value.code}
                    label={name}
                    color={value.color_hex ?? undefined}
                    selected={selectedNow}
                    disabled={state === "invalid"}
                    unavailable={state === "out_of_stock"}
                    onClick={() => onSelect(axis.code, value.code)}
                  />
                );
              }

              return (
                <SizeOption
                  key={value.code}
                  label={value.name}
                  aria-label={name}
                  selected={selectedNow}
                  disabled={state === "invalid"}
                  unavailable={state === "out_of_stock"}
                  onClick={() => onSelect(axis.code, value.code)}
                />
              );
            })}
          </VariantGroup>
        );
      })}
    </div>
  );
}
