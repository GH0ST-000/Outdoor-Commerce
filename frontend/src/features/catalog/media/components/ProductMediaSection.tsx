"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  fetchMediaAssetStatus,
  fetchProductMedia,
  fetchVariantMedia,
  removeMediaAttachment,
  reorderMedia,
  retryMediaProcessing,
  setPrimaryMedia,
  updateMediaMetadata,
  uploadMedia,
} from "@/features/catalog/media/api/media-api";
import { MediaEditDialog } from "@/features/catalog/media/components/MediaEditDialog";
import { MediaGallery } from "@/features/catalog/media/components/MediaGallery";
import { MediaRemoveConfirm } from "@/features/catalog/media/components/MediaRemoveConfirm";
import {
  MediaUploadDropzone,
  type UploadProgressItem,
} from "@/features/catalog/media/components/MediaUploadDropzone";
import type {
  MediaAttachment,
  MediaOwnerScope,
} from "@/features/catalog/media/types/media-types";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";

function messageFrom(error: unknown, fallback: string): string {
  return error instanceof ApiClientError ? error.message : fallback;
}

async function loadMedia(scope: MediaOwnerScope): Promise<MediaAttachment[]> {
  if (scope.kind === "product") {
    return fetchProductMedia(scope.productId);
  }
  return fetchVariantMedia(scope.productId, scope.variantId);
}

export function ProductMediaSection({
  scope,
  canManage,
  title = "Media",
  description = "Upload images, set the primary photo, and add alt text for accessibility.",
}: {
  scope: MediaOwnerScope;
  canManage: boolean;
  title?: string;
  description?: string;
}) {
  const [attachments, setAttachments] = useState<MediaAttachment[] | null>(
    null,
  );
  const [loadError, setLoadError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [liveMessage, setLiveMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [uploadItems, setUploadItems] = useState<UploadProgressItem[]>([]);
  const [editing, setEditing] = useState<MediaAttachment | null>(null);
  const [removeTarget, setRemoveTarget] = useState<MediaAttachment | null>(
    null,
  );
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const refresh = useCallback(async () => {
    try {
      const next = await loadMedia(scope);
      setAttachments(next);
      setLoadError(null);
      return next;
    } catch (err) {
      setLoadError(messageFrom(err, "Unable to load media."));
      setAttachments([]);
      return [];
    }
  }, [scope]);

  useEffect(() => {
    let cancelled = false;
    void loadMedia(scope)
      .then((next) => {
        if (cancelled) return;
        setAttachments(next);
        setLoadError(null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setLoadError(messageFrom(err, "Unable to load media."));
        setAttachments([]);
      });
    return () => {
      cancelled = true;
    };
  }, [scope]);

  useEffect(() => {
    const needsPoll = (attachments ?? []).some(
      (item) =>
        item.status === "pending" || item.status === "processing",
    );
    if (!needsPoll) {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
      return;
    }

    pollRef.current = setInterval(() => {
      void (async () => {
        const current = attachments ?? [];
        let changed = false;
        for (const item of current) {
          if (
            item.status !== "pending" &&
            item.status !== "processing"
          ) {
            continue;
          }
          try {
            const status = await fetchMediaAssetStatus(item.asset_id);
            if (status.status !== item.status) {
              changed = true;
            }
          } catch {
            /* ignore transient poll errors */
          }
        }
        if (changed) {
          await refresh();
        }
      })();
    }, 3000);

    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
    };
  }, [attachments, refresh]);

  async function onFilesSelected(files: File[]) {
    if (!canManage || pending) return;
    setActionError(null);
    setPending(true);

    const items: UploadProgressItem[] = files.map((file, index) => ({
      id: `${file.name}-${index}-${Date.now()}`,
      name: file.name,
      progress: 10,
      state: "uploading",
    }));
    setUploadItems(items);

    try {
      const uploaded = await uploadMedia(scope, files);
      setUploadItems((current) =>
        current.map((row) => ({ ...row, progress: 100, state: "done" })),
      );
      setLiveMessage(
        uploaded.length === 1
          ? `Uploaded ${uploaded[0]?.original_filename ?? "1 file"}. Processing on server.`
          : `Uploaded ${uploaded.length} files. Processing on server.`,
      );
      await refresh();
    } catch (err) {
      const message = messageFrom(err, "Upload failed.");
      setActionError(message);
      setUploadItems((current) =>
        current.map((row) => ({
          ...row,
          progress: 100,
          state: "error",
          message,
        })),
      );
      setLiveMessage(message);
    } finally {
      setPending(false);
      setTimeout(() => setUploadItems([]), 4000);
    }
  }

  async function runAction(fn: () => Promise<void>) {
    if (pending) return;
    setPending(true);
    setActionError(null);
    try {
      await fn();
      await refresh();
    } catch (err) {
      setActionError(messageFrom(err, "Action failed."));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="space-y-4">
      <div className="sr-only" role="status" aria-live="polite">
        {liveMessage}
      </div>

      {loadError ? (
        <p className="text-sm text-destructive" role="alert">
          {loadError}
        </p>
      ) : null}
      {actionError ? (
        <p className="text-sm text-destructive" role="alert">
          {actionError}
        </p>
      ) : null}

      <AdminPanel>
        <AdminPanelHeader title={title} description={description} />
        {canManage ? (
          <MediaUploadDropzone
            disabled={pending}
            uploadItems={uploadItems}
            onFilesSelected={(files) => void onFilesSelected(files)}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            You can view the gallery but need catalog.manage to upload or edit.
          </p>
        )}
      </AdminPanel>

      {attachments === null ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading gallery…
        </p>
      ) : (
        <MediaGallery
          attachments={attachments}
          canManage={canManage}
          pending={pending}
          onSetPrimary={(attachment) =>
            void runAction(async () => {
              await setPrimaryMedia(scope, attachment.id);
              setLiveMessage("Primary image updated.");
            })
          }
          onEdit={(attachment) => setEditing(attachment)}
          onRemove={(attachment) => setRemoveTarget(attachment)}
          onRetry={(attachment) =>
            void runAction(async () => {
              await retryMediaProcessing(scope, attachment.id);
              setLiveMessage("Retry queued.");
            })
          }
          onReorder={(orderedIds) =>
            void runAction(async () => {
              await reorderMedia(scope, orderedIds);
              setLiveMessage("Gallery order saved.");
            })
          }
        />
      )}

      {editing ? (
        <MediaEditDialog
          attachment={editing}
          canManage={canManage}
          pending={pending}
          onCancel={() => setEditing(null)}
          onSave={(payload) =>
            void runAction(async () => {
              await updateMediaMetadata(scope, editing.id, payload);
              setEditing(null);
              setLiveMessage("Metadata saved.");
            })
          }
        />
      ) : null}

      {removeTarget ? (
        <MediaRemoveConfirm
          filename={removeTarget.original_filename ?? `#${removeTarget.id}`}
          pending={pending}
          onCancel={() => setRemoveTarget(null)}
          onConfirm={() =>
            void runAction(async () => {
              await removeMediaAttachment(scope, removeTarget.id);
              setRemoveTarget(null);
              setLiveMessage("Image removed.");
            })
          }
        />
      ) : null}
    </div>
  );
}
