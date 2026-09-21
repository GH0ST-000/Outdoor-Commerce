"use client";

import type { MediaAttachment } from "@/features/catalog/media/types/media-types";
import { MediaGalleryCard } from "@/features/catalog/media/components/MediaGalleryCard";

export function MediaGallery({
  attachments,
  canManage,
  pending,
  onSetPrimary,
  onEdit,
  onRemove,
  onRetry,
  onReorder,
}: {
  attachments: MediaAttachment[];
  canManage: boolean;
  pending: boolean;
  onSetPrimary: (attachment: MediaAttachment) => void;
  onEdit: (attachment: MediaAttachment) => void;
  onRemove: (attachment: MediaAttachment) => void;
  onRetry: (attachment: MediaAttachment) => void;
  onReorder: (orderedIds: number[]) => void;
}) {
  if (attachments.length === 0) {
    return (
      <div
        className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-4 py-10 text-center"
        data-testid="media-gallery-empty"
      >
        <p className="text-sm font-medium">No images yet</p>
        <p className="mt-1 text-xs text-muted-foreground">
          Upload product photos to build the gallery. The first ready image can
          become primary.
        </p>
      </div>
    );
  }

  const sorted = [...attachments].sort(
    (a, b) => a.sort_order - b.sort_order || a.id - b.id,
  );

  function move(index: number, direction: -1 | 1) {
    const nextIndex = index + direction;
    if (nextIndex < 0 || nextIndex >= sorted.length) return;
    const ids = sorted.map((item) => item.id);
    const [removed] = ids.splice(index, 1);
    ids.splice(nextIndex, 0, removed);
    onReorder(ids);
  }

  return (
    <ul
      className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
      aria-label="Media gallery"
    >
      {sorted.map((attachment, index) => (
        <MediaGalleryCard
          key={attachment.id}
          attachment={attachment}
          index={index}
          total={sorted.length}
          canManage={canManage}
          pending={pending}
          onSetPrimary={() => onSetPrimary(attachment)}
          onEdit={() => onEdit(attachment)}
          onRemove={() => onRemove(attachment)}
          onRetry={() => onRetry(attachment)}
          onMoveUp={() => move(index, -1)}
          onMoveDown={() => move(index, 1)}
        />
      ))}
    </ul>
  );
}
