import type { ReactNode } from "react";
import { cn } from "@/lib/utils";
import { StatusBadge } from "@/components/commerce/status-badge";

export type OutdoorSeasonStatus = "open" | "closed" | "conditional" | "unknown";

export function SeasonStatus({
  status,
  label,
}: {
  status: OutdoorSeasonStatus;
  label: string;
}) {
  const kind =
    status === "open"
      ? "open_season"
      : status === "closed"
        ? "closed_season"
        : status === "conditional"
          ? "conditional"
          : "featured";

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold",
        status === "open" &&
          "border-[color-mix(in_oklab,var(--status-success)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-success)_12%,transparent)]",
        status === "closed" &&
          "border-[color-mix(in_oklab,var(--status-danger)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-danger)_12%,transparent)]",
        status === "conditional" &&
          "border-[color-mix(in_oklab,var(--status-warning)_35%,transparent)] bg-[color-mix(in_oklab,var(--status-warning)_12%,transparent)]",
        status === "unknown" && "border-border bg-muted text-muted-foreground",
      )}
    >
      <StatusBadge kind={kind} className="border-0 bg-transparent px-0 py-0">
        {label}
      </StatusBadge>
    </span>
  );
}

export function SpeciesSummary({
  name,
  meta,
}: {
  name: string;
  meta?: string;
}) {
  return (
    <div>
      <p className="font-semibold">{name}</p>
      {meta ? <p className="text-sm text-muted-foreground">{meta}</p> : null}
    </div>
  );
}

export function LegalLimit({
  label,
  value,
  verificationLabel,
  source,
  updatedAt,
}: {
  label: string;
  value: string;
  verificationLabel: string;
  source?: string;
  updatedAt?: string;
}) {
  return (
    <div className="rounded-[var(--radius-md)] border border-border px-3 py-2">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-medium tabular-nums">{value}</p>
      <p className="mt-1 text-xs text-muted-foreground">
        {verificationLabel}
        {source ? ` · ${source}` : ""}
        {updatedAt ? ` · ${updatedAt}` : ""}
      </p>
    </div>
  );
}

export function RegionBadge({ children }: { children: string }) {
  return <StatusBadge kind="featured">{children}</StatusBadge>;
}

export function MapLegend({
  items,
}: {
  items: Array<{ label: string; swatch: string }>;
}) {
  return (
    <ul className="space-y-1 text-sm">
      {items.map((item) => (
        <li key={item.label} className="flex items-center gap-2">
          <span
            className="size-3 rounded-sm border border-border"
            style={{ backgroundColor: item.swatch }}
            aria-hidden
          />
          {item.label}
        </li>
      ))}
    </ul>
  );
}

export function MapInfoPanel({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  return (
    <aside className="rounded-[var(--radius-lg)] border border-border bg-card p-4">
      <h3 className="font-semibold">{title}</h3>
      <div className="mt-2 text-sm">{children}</div>
    </aside>
  );
}

export function ConditionsIndicator({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <p className="text-sm">
      <span className="text-muted-foreground">{label}: </span>
      {value}
    </p>
  );
}

export function RelatedGearPanel({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  return (
    <section>
      <h3 className="type-h4">{title}</h3>
      <div className="mt-3">{children}</div>
    </section>
  );
}
