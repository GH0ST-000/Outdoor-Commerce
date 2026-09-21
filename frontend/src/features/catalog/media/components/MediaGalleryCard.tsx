"use client";

import type { MediaAttachment } from "@/features/catalog/media/types/media-types";
import { MediaStatusBadge } from "@/features/catalog/media/components/MediaStatusBadge";
import { previewUrlForAttachment } from "@/features/catalog/media/utils/media-preview-url";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function MediaGalleryCard({
  attachment,
  index,
  total,
  canManage,
  pending,
  onSetPrimary,
  onEdit,
  onRemove,
  onRetry,
  onMoveUp,
  onMoveDown,
}: {
  attachment: MediaAttachment;
  index: number;
  total: number;
  canManage: boolean;
  pending: boolean;
  onSetPrimary: () => void;
  onEdit: () => void;
  onRemove: () => void;
  onRetry: () => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
}) {
  const previewUrl = previewUrlForAttachment(attachment, "thumbnail");
  const label = attachment.original_filename ?? `Image ${index + 1}`;

  return (
    <li
      className="flex flex-col overflow-hidden rounded-xl border border-border/70 bg-card"
      data-testid={`media-card-${attachment.id}`}
    >
      <div className="relative aspect-square bg-muted">
        {previewUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={previewUrl} alt="" className="size-full object-cover" />
        ) : (
          <div className="flex size-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
            {attachment.status === "ready" ? "No preview" : "Processing…"}
          </div>
        )}
        <div className="absolute top-2 left-2 flex flex-wrap gap-1">
          <MediaStatusBadge status={attachment.status} />
          {attachment.is_primary ? (
            <Badge variant="default">Primary</Badge>
          ) : null}
        </div>
        {canManage ? (
          <Button
            type="button"
            size="sm"
            variant="destructive"
            className="absolute top-2 right-2"
            disabled={pending}
            aria-label={`Remove ${label}`}
            onClick={onRemove}
          >
            Remove
          </Button>
        ) : null}
      </div>
      <div className="space-y-2 p-3">
        <p className="truncate text-xs font-medium" title={label}>
          {label}
        </p>
        {attachment.alt_text ? (
          <p className="sf-line-clamp-2 text-xs text-muted-foreground">
            {attachment.alt_text}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-1">
          {canManage ? (
            <>
              {!attachment.is_primary && attachment.status === "ready" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={pending}
                  onClick={onSetPrimary}
                >
                  Set primary
                </Button>
              ) : null}
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={pending}
                onClick={onEdit}
              >
                Edit
              </Button>
              {attachment.status === "failed" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={pending}
                  onClick={onRetry}
                >
                  Retry
                </Button>
              ) : null}
              <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={pending || index === 0}
                aria-label={`Move ${label} up`}
                onClick={onMoveUp}
              >
                ↑
              </Button>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={pending || index >= total - 1}
                aria-label={`Move ${label} down`}
                onClick={onMoveDown}
              >
                ↓
              </Button>
              <Button
                type="button"
                size="sm"
                variant="destructive"
                disabled={pending}
                onClick={onRemove}
              >
                Remove
              </Button>
            </>
          ) : (
            <p className={cn("text-xs text-muted-foreground")}>
              View-only (catalog.manage required to edit)
            </p>
          )}
        </div>
      </div>
    </li>
  );
}
