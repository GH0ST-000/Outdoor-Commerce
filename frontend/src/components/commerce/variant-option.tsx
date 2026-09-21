import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/utils";

export function ColorSwatch({
  label,
  color,
  selected = false,
  disabled = false,
  unavailable = false,
  onClick,
}: {
  label: string;
  color?: string;
  selected?: boolean;
  disabled?: boolean;
  unavailable?: boolean;
  onClick?: () => void;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      aria-pressed={selected}
      aria-label={label}
      title={label}
      onClick={onClick}
      className={cn(
        "inline-flex size-11 items-center justify-center rounded-full border-2 transition-transform duration-[var(--duration-fast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40",
        selected ? "scale-105 border-foreground" : "border-transparent",
        unavailable && "opacity-50",
      )}
    >
      <span
        className={cn(
          "size-7 rounded-full border border-border",
          unavailable &&
            "bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,var(--border)_4px,var(--border)_5px)]",
        )}
        style={{ backgroundColor: color ?? "var(--muted)" }}
        aria-hidden
      />
    </button>
  );
}

export function SizeOption({
  label,
  selected = false,
  disabled = false,
  unavailable = false,
  onClick,
  "aria-label": ariaLabel,
}: {
  label: string;
  selected?: boolean;
  disabled?: boolean;
  unavailable?: boolean;
  onClick?: () => void;
  "aria-label"?: string;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      aria-pressed={selected}
      aria-label={ariaLabel}
      onClick={onClick}
      className={cn(
        "min-h-11 min-w-12 rounded-lg border px-3 py-2 text-sm font-medium transition-colors duration-[var(--duration-fast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40",
        selected
          ? "border-foreground bg-foreground text-background"
          : "border-border bg-card hover:bg-muted",
        unavailable && "line-through",
      )}
    >
      {label}
    </button>
  );
}

export function UnavailableOption(props: ComponentProps<typeof SizeOption>) {
  return <SizeOption unavailable disabled {...props} />;
}

export function VariantGroup({
  legend,
  summary,
  children,
}: {
  legend: string;
  summary?: string;
  children: ReactNode;
}) {
  return (
    <fieldset className="space-y-2">
      <legend className="text-sm font-medium">
        {legend}
        {summary ? (
          <span className="ms-2 font-normal text-muted-foreground">
            {summary}
          </span>
        ) : null}
      </legend>
      <div className="flex flex-wrap gap-2">{children}</div>
    </fieldset>
  );
}

export function VariantOption(props: ComponentProps<typeof SizeOption>) {
  return <SizeOption {...props} />;
}

export function SelectedVariantSummary({ children }: { children: string }) {
  return <p className="text-sm text-muted-foreground">{children}</p>;
}
