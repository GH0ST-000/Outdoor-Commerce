import { Badge } from "@/components/ui/badge";

type BadgeVariant =
  "default" | "secondary" | "success" | "warning" | "danger" | "outline";

const STATUS_VARIANT: Record<string, BadgeVariant> = {
  active: "success",
  draft: "secondary",
  archived: "warning",
  disabled: "danger",
};

export function StatusBadge({ status }: { status: string }) {
  return (
    <Badge variant={STATUS_VARIANT[status] ?? "outline"}>
      {status.replace(/_/g, " ")}
    </Badge>
  );
}
