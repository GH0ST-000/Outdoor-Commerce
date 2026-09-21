import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

const tones = {
  info: "border-[color-mix(in_oklab,var(--status-info)_28%,transparent)] bg-[color-mix(in_oklab,var(--status-info)_10%,transparent)]",
  success:
    "border-[color-mix(in_oklab,var(--status-success)_28%,transparent)] bg-[color-mix(in_oklab,var(--status-success)_10%,transparent)]",
  warning:
    "border-[color-mix(in_oklab,var(--status-warning)_28%,transparent)] bg-[color-mix(in_oklab,var(--status-warning)_10%,transparent)]",
  danger:
    "border-[color-mix(in_oklab,var(--status-danger)_28%,transparent)] bg-[color-mix(in_oklab,var(--status-danger)_10%,transparent)]",
} as const;

export function Alert({
  tone = "info",
  title,
  children,
  className,
}: {
  tone?: keyof typeof tones;
  title?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      role="status"
      className={cn(
        "rounded-[var(--radius-lg)] border px-4 py-3 text-sm",
        tones[tone],
        className,
      )}
    >
      {title ? <p className="font-semibold">{title}</p> : null}
      <div className={cn(title && "mt-1")}>{children}</div>
    </div>
  );
}

export function InlineMessage({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <p className={cn("text-sm text-muted-foreground", className)}>{children}</p>
  );
}
