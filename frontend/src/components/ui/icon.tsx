import type { LucideIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { iconSizes } from "@/lib/design-system/tokens";

export function Icon({
  icon: Glyph,
  size = "md",
  decorative = true,
  label,
  className,
}: {
  icon: LucideIcon;
  size?: keyof typeof iconSizes;
  decorative?: boolean;
  label?: string;
  className?: string;
}) {
  return (
    <Glyph
      size={iconSizes[size]}
      aria-hidden={decorative || !label}
      aria-label={decorative ? undefined : label}
      className={cn("shrink-0", className)}
    />
  );
}
