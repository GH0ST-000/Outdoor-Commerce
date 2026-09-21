import { cn } from "@/lib/utils";

export type AvailabilityTone =
  "in_stock" | "low_stock" | "out_of_stock" | "unavailable";

const toneClass: Record<AvailabilityTone, string> = {
  in_stock: "text-[var(--status-success)]",
  low_stock: "text-[var(--status-warning)]",
  out_of_stock: "text-[var(--status-danger)]",
  unavailable: "text-muted-foreground",
};

export function AvailabilityStatus({
  status,
  label,
  className,
}: {
  status: AvailabilityTone;
  label: string;
  className?: string;
}) {
  return (
    <span
      className={cn("sf-line-clamp-1 text-xs", toneClass[status], className)}
    >
      {label}
    </span>
  );
}

export function LowStockMessage({ children }: { children: string }) {
  return <AvailabilityStatus status="low_stock" label={children} />;
}

export function OutOfStockMessage({ children }: { children: string }) {
  return <AvailabilityStatus status="out_of_stock" label={children} />;
}

export function PurchasabilityState({
  purchasable,
  purchasableLabel,
  unavailableLabel,
}: {
  purchasable: boolean;
  purchasableLabel: string;
  unavailableLabel: string;
}) {
  return (
    <AvailabilityStatus
      status={purchasable ? "in_stock" : "unavailable"}
      label={purchasable ? purchasableLabel : unavailableLabel}
    />
  );
}
