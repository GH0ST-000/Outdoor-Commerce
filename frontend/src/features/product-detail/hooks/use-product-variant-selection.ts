"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import type { PublicProductDetail } from "@/features/catalog/types/public-catalog";
import {
  buildVariantIndex,
  parseVariantIdParam,
  resolveAfterValueClick,
  resolveInitialVariant,
  selectionFromCombination,
} from "@/features/product-detail/state/variant-resolver";

function replaceVariantQuery(variantId: number | null) {
  if (typeof window === "undefined") {
    return;
  }
  const url = new URL(window.location.href);
  if (variantId == null) {
    url.searchParams.delete("variant");
  } else {
    url.searchParams.set("variant", String(variantId));
  }
  const next = `${url.pathname}${url.search}${url.hash}`;
  if (
    `${window.location.pathname}${window.location.search}${window.location.hash}` ===
    next
  ) {
    return;
  }
  window.history.replaceState(window.history.state, "", next);
}

export function useProductVariantSelection(
  detail: PublicProductDetail,
  initialVariantId?: number | null,
) {
  const index = useMemo(
    () => buildVariantIndex(detail.variants.combinations),
    [detail.variants.combinations],
  );

  const initial = useMemo(
    () =>
      resolveInitialVariant(index, {
        urlVariantId: initialVariantId ?? null,
        defaultVariantId: detail.default_variant_id,
      }),
    [detail.default_variant_id, index, initialVariantId],
  );

  const [variantId, setVariantId] = useState<number | null>(
    initial.variant?.id ?? null,
  );

  useEffect(() => {
    if (initial.usedUrlFallback && initial.variant) {
      replaceVariantQuery(initial.variant.id);
    }
  }, [initial.usedUrlFallback, initial.variant]);

  const selectedVariant =
    (variantId != null ? index.variantById.get(variantId) : null) ??
    initial.variant;

  const selection = useMemo(
    () => (selectedVariant ? selectionFromCombination(selectedVariant) : {}),
    [selectedVariant],
  );

  const selectValue = useCallback(
    (axisCode: string, valueCode: string) => {
      const next = resolveAfterValueClick(
        index,
        selection,
        axisCode,
        valueCode,
      );
      if (!next) {
        return;
      }
      setVariantId(next.id);
      replaceVariantQuery(next.id);
    },
    [index, selection],
  );

  const selectVariantId = useCallback(
    (id: number) => {
      const parsed = parseVariantIdParam(id);
      if (parsed == null) {
        return;
      }
      const next = index.variantById.get(parsed);
      if (!next) {
        return;
      }
      setVariantId(next.id);
      replaceVariantQuery(next.id);
    },
    [index],
  );

  return {
    index,
    selectedVariant,
    selection,
    usedUrlFallback: initial.usedUrlFallback,
    selectValue,
    selectVariantId,
  };
}
