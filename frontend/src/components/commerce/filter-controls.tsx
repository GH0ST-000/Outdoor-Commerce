import type { ReactNode } from "react";
import { cn } from "@/lib/utils";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";

export function FilterGroup({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  return (
    <section className="space-y-2">
      <h3 className="text-sm font-semibold text-foreground">{title}</h3>
      <div className="space-y-1">{children}</div>
    </section>
  );
}

export function FilterOption({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return <div className={cn("min-w-0", className)}>{children}</div>;
}

export function FilterCheckbox({
  id,
  label,
  checked,
  onChange,
  count,
}: {
  id: string;
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  count?: number;
}) {
  return (
    <div
      className={cn(
        "flex min-h-[var(--touch-min)] items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm transition-colors duration-[var(--duration-fast)]",
        checked
          ? "border-primary/30 bg-[color-mix(in_oklab,var(--action-primary)_8%,var(--card))]"
          : "border-transparent hover:bg-muted/70",
      )}
    >
      <span className="flex min-w-0 items-center gap-2.5">
        <Checkbox
          id={id}
          checked={checked}
          onCheckedChange={(value) => onChange(value === true)}
        />
        <label htmlFor={id} className="truncate font-medium">
          {label}
        </label>
      </span>
      {typeof count === "number" ? (
        <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground tabular-nums">
          {count}
        </span>
      ) : null}
    </div>
  );
}

export function FilterRange({
  minId,
  maxId,
  minLabel,
  maxLabel,
  minValue,
  maxValue,
  onMinChange,
  onMaxChange,
}: {
  minId: string;
  maxId: string;
  minLabel: string;
  maxLabel: string;
  minValue: string;
  maxValue: string;
  onMinChange: (value: string) => void;
  onMaxChange: (value: string) => void;
}) {
  return (
    <div className="grid grid-cols-2 gap-2">
      <label className="text-xs text-muted-foreground" htmlFor={minId}>
        {minLabel}
        <input
          id={minId}
          inputMode="decimal"
          value={minValue}
          onChange={(event) => onMinChange(event.target.value)}
          className="mt-1 flex h-10 w-full rounded-lg border border-[var(--input-border)] bg-background px-3 text-sm"
        />
      </label>
      <label className="text-xs text-muted-foreground" htmlFor={maxId}>
        {maxLabel}
        <input
          id={maxId}
          inputMode="decimal"
          value={maxValue}
          onChange={(event) => onMaxChange(event.target.value)}
          className="mt-1 flex h-10 w-full rounded-lg border border-[var(--input-border)] bg-background px-3 text-sm"
        />
      </label>
    </div>
  );
}

export function ActiveFilterChip({
  label,
  onRemove,
  removeLabel,
}: {
  label: string;
  onRemove: () => void;
  removeLabel: string;
}) {
  return (
    <button
      type="button"
      onClick={onRemove}
      className="inline-flex min-h-9 items-center gap-1 rounded-full border border-border bg-card px-3 text-xs font-medium"
      aria-label={removeLabel}
    >
      {label}
      <span aria-hidden>×</span>
    </button>
  );
}

export function FilterSummary({
  count,
  resultsLabel,
}: {
  count: number;
  resultsLabel: string;
}) {
  return (
    <p className="text-sm text-muted-foreground">
      <span className="font-semibold text-foreground tabular-nums">
        {count}
      </span>{" "}
      {resultsLabel}
    </p>
  );
}

export function ClearFiltersButton({
  label,
  onClick,
}: {
  label: string;
  onClick: () => void;
}) {
  return (
    <Button type="button" variant="outline" onClick={onClick}>
      {label}
    </Button>
  );
}
