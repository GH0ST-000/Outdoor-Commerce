"use client";

import { basisPointsToPercentLabel } from "@/features/pricing/lib/money";

type PromotionBadgeProps = {
  label?: string;
  /** Prefer backend-provided display; basis points only for presentational fallback. */
  percentageBasisPoints?: number | null;
  className?: string;
};

export function PromotionBadge({
  label,
  percentageBasisPoints,
  className,
}: PromotionBadgeProps) {
  const text =
    label ??
    (percentageBasisPoints != null
      ? basisPointsToPercentLabel(percentageBasisPoints)
      : null);

  if (!text) {
    return null;
  }

  return (
    <span className={className} data-testid="promotion-badge">
      {text}
    </span>
  );
}
