"use client";

import { Ban, CircleAlert, CircleCheck, CircleHelp } from "lucide-react";
import type { SeasonStatus } from "@/features/storefront/types/storefront-types";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { cn } from "@/lib/utils";

const ICONS = {
  open: CircleCheck,
  closed: Ban,
  conditional: CircleAlert,
  unknown: CircleHelp,
} as const;

export function SeasonStatusBadge({ status }: { status: SeasonStatus }) {
  const { t } = useStorefrontCopy();
  const Icon = ICONS[status];
  const label =
    status === "open"
      ? t.calendar.open
      : status === "closed"
        ? t.calendar.closed
        : status === "conditional"
          ? t.calendar.conditional
          : t.calendar.unknown;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold",
        status === "open" &&
          "border-[color-mix(in_oklab,var(--success)_35%,transparent)] bg-[color-mix(in_oklab,var(--success)_12%,transparent)] text-[var(--success)]",
        status === "closed" &&
          "border-[color-mix(in_oklab,var(--destructive)_35%,transparent)] bg-[color-mix(in_oklab,var(--destructive)_12%,transparent)] text-destructive",
        status === "conditional" &&
          "border-[color-mix(in_oklab,var(--warning)_35%,transparent)] bg-[color-mix(in_oklab,var(--warning)_12%,transparent)] text-[var(--warning)]",
        status === "unknown" && "border-border bg-muted text-muted-foreground",
      )}
    >
      <Icon className="size-3.5" aria-hidden />
      <span>{label}</span>
    </span>
  );
}
