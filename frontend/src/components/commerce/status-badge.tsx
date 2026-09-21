import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";

export type StatusBadgeKind =
  | "featured"
  | "new"
  | "sale"
  | "low_stock"
  | "out_of_stock"
  | "in_stock"
  | "open_season"
  | "closed_season"
  | "conditional"
  | "draft"
  | "active"
  | "archived";

const variantMap: Record<
  StatusBadgeKind,
  ComponentProps<typeof Badge>["variant"]
> = {
  featured: "default",
  new: "secondary",
  sale: "warning",
  low_stock: "warning",
  out_of_stock: "danger",
  in_stock: "success",
  open_season: "success",
  closed_season: "danger",
  conditional: "warning",
  draft: "outline",
  active: "success",
  archived: "secondary",
};

export function StatusBadge({
  kind,
  children,
  className,
}: {
  kind: StatusBadgeKind;
  children: string;
  className?: string;
}) {
  return (
    <Badge
      variant={variantMap[kind]}
      className={cn("normal-case tracking-normal", className)}
    >
      {children}
    </Badge>
  );
}
