import { cn } from "@/lib/utils";

export function Spinner({
  className,
  label,
}: {
  className?: string;
  label?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex size-4 shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent",
        className,
      )}
      role={label ? "status" : "presentation"}
      aria-label={label}
      aria-hidden={label ? undefined : true}
    />
  );
}
