import type { PublicVariantCombination } from "@/features/catalog/types/public-catalog";
import type {
  VariantIndex,
  VariantSelection,
  VariantValueAvailability,
} from "@/features/product-detail/types";

export function combinationKeyFromAttributes(
  attributes: PublicVariantCombination["attributes"],
): string {
  return attributes
    .map((attribute) => [attribute.code, attribute.value.code] as const)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([code, value]) => `${code}=${value}`)
    .join("&");
}

export function combinationKeyFromSelection(
  selection: VariantSelection,
): string {
  return Object.entries(selection)
    .filter(([, value]) => Boolean(value))
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([code, value]) => `${code}=${value}`)
    .join("&");
}

export function selectionFromCombination(
  combination: PublicVariantCombination,
): VariantSelection {
  const selection: VariantSelection = {};
  for (const attribute of combination.attributes) {
    selection[attribute.code] = attribute.value.code;
  }
  return selection;
}

export function parseVariantIdParam(raw: unknown): number | null {
  if (typeof raw === "number" && Number.isInteger(raw) && raw > 0) {
    return raw;
  }
  if (typeof raw !== "string" || raw.trim() === "") {
    return null;
  }
  const parsed = Number.parseInt(raw, 10);
  if (
    !Number.isInteger(parsed) ||
    parsed <= 0 ||
    String(parsed) !== raw.trim()
  ) {
    return null;
  }
  return parsed;
}

export function buildVariantIndex(
  combinations: PublicVariantCombination[],
): VariantIndex {
  const variantById = new Map<number, PublicVariantCombination>();
  const variantByKey = new Map<string, PublicVariantCombination>();
  const valuesByAttribute = new Map<string, Set<string>>();

  for (const combination of combinations) {
    variantById.set(combination.id, combination);
    variantByKey.set(
      combinationKeyFromAttributes(combination.attributes),
      combination,
    );
    for (const attribute of combination.attributes) {
      let values = valuesByAttribute.get(attribute.code);
      if (!values) {
        values = new Set();
        valuesByAttribute.set(attribute.code, values);
      }
      values.add(attribute.value.code);
    }
  }

  return { combinations, variantById, variantByKey, valuesByAttribute };
}

export function resolveInitialVariant(
  index: VariantIndex,
  options: {
    urlVariantId?: number | null;
    defaultVariantId?: number | null;
  },
): { variant: PublicVariantCombination | null; usedUrlFallback: boolean } {
  if (options.urlVariantId != null) {
    const fromUrl = index.variantById.get(options.urlVariantId);
    if (fromUrl) {
      return { variant: fromUrl, usedUrlFallback: false };
    }
  }

  if (options.defaultVariantId != null) {
    const fromDefault = index.variantById.get(options.defaultVariantId);
    if (fromDefault) {
      return {
        variant: fromDefault,
        usedUrlFallback: options.urlVariantId != null,
      };
    }
  }

  const markedDefault = index.combinations.find((item) => item.is_default);
  if (markedDefault) {
    return {
      variant: markedDefault,
      usedUrlFallback: options.urlVariantId != null,
    };
  }

  return {
    variant: index.combinations[0] ?? null,
    usedUrlFallback: options.urlVariantId != null,
  };
}

function combinationHasValue(
  combination: PublicVariantCombination,
  axisCode: string,
  valueCode: string,
): boolean {
  return combination.attributes.some(
    (attribute) =>
      attribute.code === axisCode && attribute.value.code === valueCode,
  );
}

/**
 * Prefer keeping other selected axes, then purchasable, then default, then id.
 */
export function resolveAfterValueClick(
  index: VariantIndex,
  current: VariantSelection,
  axisCode: string,
  valueCode: string,
): PublicVariantCombination | null {
  const attempted: VariantSelection = { ...current, [axisCode]: valueCode };
  const exact = index.variantByKey.get(combinationKeyFromSelection(attempted));
  if (exact) {
    return exact;
  }

  const candidates = index.combinations.filter((combination) =>
    combinationHasValue(combination, axisCode, valueCode),
  );
  if (candidates.length === 0) {
    return null;
  }

  const scored = candidates.map((combination) => {
    let matches = 0;
    for (const [code, value] of Object.entries(current)) {
      if (code === axisCode) {
        continue;
      }
      if (combinationHasValue(combination, code, value)) {
        matches += 1;
      }
    }
    return {
      combination,
      matches,
      purchasable: combination.availability.purchasable ? 1 : 0,
      isDefault: combination.is_default ? 1 : 0,
      id: combination.id,
    };
  });

  scored.sort((left, right) => {
    if (right.matches !== left.matches) {
      return right.matches - left.matches;
    }
    if (right.purchasable !== left.purchasable) {
      return right.purchasable - left.purchasable;
    }
    if (right.isDefault !== left.isDefault) {
      return right.isDefault - left.isDefault;
    }
    return left.id - right.id;
  });

  return scored[0]?.combination ?? null;
}

export function valueAvailability(
  index: VariantIndex,
  current: VariantSelection,
  axisCode: string,
  valueCode: string,
): VariantValueAvailability {
  if (!index.valuesByAttribute.get(axisCode)?.has(valueCode)) {
    return "invalid";
  }
  const next = resolveAfterValueClick(index, current, axisCode, valueCode);
  if (!next) {
    return "invalid";
  }
  if (
    !next.availability.purchasable ||
    next.availability.status === "out_of_stock" ||
    next.availability.status === "unavailable"
  ) {
    return "out_of_stock";
  }
  return "available";
}

export function compatibleValues(
  index: VariantIndex,
  axisCode: string,
): Set<string> {
  return index.valuesByAttribute.get(axisCode) ?? new Set();
}
