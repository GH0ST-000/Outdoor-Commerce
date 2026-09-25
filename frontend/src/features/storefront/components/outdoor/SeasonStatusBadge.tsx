"use client";

import {
  Ban,
  CircleAlert,
  CircleCheck,
  CircleHelp,
  Split,
  TriangleAlert,
} from "lucide-react";
import type { SeasonStatus } from "@/features/storefront/types/storefront-types";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { cn } from "@/lib/utils";

const ICONS = {
  open: CircleCheck,
  partially_open: Split,
  closed: Ban,
  conditional: CircleAlert,
  unknown: CircleHelp,
  conflict: TriangleAlert,
} as const;

export function SeasonStatusBadge({ status }: { status: SeasonStatus }) {
  const { t } = useStorefrontCopy();
  const Icon = ICONS[status];
  const label =
    status === "open"
      ? t.calendar.open
      : status === "partially_open"
        ? t.calendar.partiallyOpen
        : status === "closed"
          ? t.calendar.closed
          : status === "conditional"
            ? t.calendar.conditional
            : status === "conflict"
              ? t.calendar.conflict
              : t.calendar.unknown;

  return (
    <span
      role="status"
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold",
        status === "open" &&
          "border-[color-mix(in_oklab,var(--status-success)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-success)_12%,transparent)] text-[var(--status-success)]",
        status === "partially_open" &&
          "border-[color-mix(in_oklab,var(--status-warning)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-warning)_12%,transparent)] text-[var(--status-warning)]",
        status === "closed" &&
          "border-[color-mix(in_oklab,var(--status-danger)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-danger)_12%,transparent)] text-destructive",
        status === "conditional" &&
          "border-[color-mix(in_oklab,var(--status-warning)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-warning)_12%,transparent)] text-[var(--status-warning)]",
        status === "conflict" &&
          "border-[color-mix(in_oklab,var(--status-danger)_40%,transparent)] bg-muted text-foreground",
        status === "unknown" && "border-border bg-muted text-muted-foreground",
      )}
    >
      <Icon className="size-3.5" aria-hidden />
      <span>{label}</span>
    </span>
  );
}
