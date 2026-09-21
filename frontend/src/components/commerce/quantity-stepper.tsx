"use client";

import { cn } from "@/lib/utils";
import { IconButton } from "@/components/ui/icon-button";

export function QuantityStepper({
  value,
  onChange,
  min = 1,
  max,
  disabled = false,
  loading = false,
  label,
  decrementLabel,
  incrementLabel,
}: {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  disabled?: boolean;
  loading?: boolean;
  label: string;
  decrementLabel: string;
  incrementLabel: string;
}) {
  const atMin = value <= min;
  const atMax = max != null && value >= max;

  return (
    <div className="inline-flex items-center rounded-lg border border-border">
      <IconButton
        type="button"
        variant="ghost"
        label={decrementLabel}
        disabled={disabled || loading || atMin}
        onClick={() => onChange(Math.max(min, value - 1))}
        className="size-11 rounded-none"
      >
        −
      </IconButton>
      <input
        aria-label={label}
        inputMode="numeric"
        disabled={disabled || loading}
        value={value}
        onChange={(event) => {
          const next = Number.parseInt(event.target.value, 10);
          if (Number.isInteger(next)) {
            const boundedMax = max ?? next;
            onChange(Math.min(boundedMax, Math.max(min, next)));
          }
        }}
        className={cn(
          "h-11 w-14 border-x border-border bg-transparent text-center text-sm tabular-nums outline-none",
        )}
      />
      <IconButton
        type="button"
        variant="ghost"
        label={incrementLabel}
        disabled={disabled || loading || atMax}
        onClick={() =>
          onChange(max == null ? value + 1 : Math.min(max, value + 1))
        }
        className="size-11 rounded-none"
      >
        +
      </IconButton>
    </div>
  );
}
