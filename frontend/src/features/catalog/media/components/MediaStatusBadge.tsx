import { Badge } from "@/components/ui/badge";
import type { MediaStatus } from "@/features/catalog/media/types/media-types";

const VARIANT: Record<
  MediaStatus,
  "default" | "secondary" | "success" | "warning" | "danger" | "outline"
> = {
  pending: "secondary",
  processing: "warning",
  ready: "success",
  failed: "danger",
  quarantined: "danger",
};

export function MediaStatusBadge({ status }: { status: MediaStatus }) {
  return (
    <Badge variant={VARIANT[status] ?? "outline"} className="capitalize">
      {status}
    </Badge>
  );
}
