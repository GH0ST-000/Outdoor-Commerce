"use client";

import {
  formatMoneyMinor,
  formatMoneyRange,
} from "@/features/pricing/lib/money";

type PriceDisplayProps = {
  amountMinor?: number | null;
  currency?: string;
  locale?: string;
  /** When both set and different, shows strike + final. */
  baseAmountMinor?: number | null;
  finalAmountMinor?: number | null;
  /** Range mode */
  minAmountMinor?: number | null;
  maxAmountMinor?: number | null;
  isRange?: boolean;
  missingLabel?: string;
  className?: string;
  loading?: boolean;
};

/**
 * Presentational price display. Never calculates promotions — uses backend values.
 */
export function PriceDisplay({
  amountMinor,
  currency = "GEL",
  locale = "ka-GE",
  baseAmountMinor,
  finalAmountMinor,
  minAmountMinor,
  maxAmountMinor,
  isRange = false,
  missingLabel = "Price on request",
  className,
  loading = false,
}: PriceDisplayProps) {
  if (loading) {
    return (
      <span
        className={className}
        aria-busy="true"
        data-testid="price-display-skeleton"
      >
        <span className="inline-block h-[1em] w-20 animate-pulse rounded bg-current/15" />
      </span>
    );
  }

  if (
    isRange &&
    minAmountMinor != null &&
    maxAmountMinor != null &&
    Number.isInteger(minAmountMinor) &&
    Number.isInteger(maxAmountMinor)
  ) {
    return (
      <span className={className} data-testid="price-display-range">
        {formatMoneyRange(minAmountMinor, maxAmountMinor, currency, locale)}
      </span>
    );
  }

  const hasDiscount =
    baseAmountMinor != null &&
    finalAmountMinor != null &&
    Number.isInteger(baseAmountMinor) &&
    Number.isInteger(finalAmountMinor) &&
    finalAmountMinor < baseAmountMinor;

  if (hasDiscount) {
    return (
      <span className={className} data-testid="price-display-discounted">
        <span className="sr-only">Was </span>
        <span className="line-through opacity-60">
          {formatMoneyMinor(baseAmountMinor!, currency, locale)}
        </span>{" "}
        <span className="sr-only">Now </span>
        <span>{formatMoneyMinor(finalAmountMinor!, currency, locale)}</span>
      </span>
    );
  }

  const single = finalAmountMinor ?? amountMinor ?? baseAmountMinor ?? null;

  if (single == null || !Number.isInteger(single)) {
    return (
      <span className={className} data-testid="price-display-missing">
        {missingLabel}
      </span>
    );
  }

  // Explicitly never render a fake "free" for missing — zero is only shown when provided.
  return (
    <span className={className} data-testid="price-display-single">
      {formatMoneyMinor(single, currency, locale)}
    </span>
  );
}
