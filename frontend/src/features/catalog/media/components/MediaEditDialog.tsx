"use client";

import { FormEvent, useState } from "react";
import type { MediaAttachment } from "@/features/catalog/media/types/media-types";
import { FocalPointPicker } from "@/features/catalog/media/components/FocalPointPicker";
import { previewUrlForAttachment } from "@/features/catalog/media/utils/media-preview-url";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

type LocaleDraft = { alt_text: string; caption: string };

function draftFrom(
  attachment: MediaAttachment,
): Record<"ka" | "en", LocaleDraft> {
  const ka = attachment.translations.find((row) => row.locale === "ka");
  const en = attachment.translations.find((row) => row.locale === "en");
  return {
    ka: {
      alt_text: ka?.alt_text ?? attachment.alt_text ?? "",
      caption: ka?.caption ?? attachment.caption ?? "",
    },
    en: {
      alt_text: en?.alt_text ?? "",
      caption: en?.caption ?? "",
    },
  };
}

export function MediaEditDialog({
  attachment,
  canManage,
  pending,
  onSave,
  onCancel,
}: {
  attachment: MediaAttachment;
  canManage: boolean;
  pending: boolean;
  onSave: (payload: {
    translations: Array<{
      locale: string;
      alt_text: string | null;
      caption: string | null;
    }>;
    focal_point: { x: number; y: number } | null;
  }) => void;
  onCancel: () => void;
}) {
  const [localeTab, setLocaleTab] = useState<"ka" | "en">("ka");
  const [draft, setDraft] = useState(() => draftFrom(attachment));
  const [focalPoint, setFocalPoint] = useState(attachment.focal_point);
  const previewUrl = previewUrlForAttachment(attachment, "detail");

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending) return;
    onSave({
      translations: [
        {
          locale: "ka",
          alt_text: draft.ka.alt_text.trim() || null,
          caption: draft.ka.caption.trim() || null,
        },
        {
          locale: "en",
          alt_text: draft.en.alt_text.trim() || null,
          caption: draft.en.caption.trim() || null,
        },
      ],
      focal_point: focalPoint,
    });
  }

  const active = draft[localeTab];

  return (
    <AdminPanel className="border-primary/20 shadow-lg">
      <AdminPanelHeader
        title="Edit media"
        description={
          attachment.original_filename ?? `Attachment #${attachment.id}`
        }
      />
      <form className="space-y-4" onSubmit={onSubmit}>
        <div className="flex gap-2">
          <Button
            type="button"
            size="sm"
            variant={localeTab === "ka" ? "default" : "outline"}
            onClick={() => setLocaleTab("ka")}
          >
            Georgian (ka)
          </Button>
          <Button
            type="button"
            size="sm"
            variant={localeTab === "en" ? "default" : "outline"}
            onClick={() => setLocaleTab("en")}
          >
            English (en)
          </Button>
        </div>
        <Field label="Alt text" htmlFor="media-alt">
          <Input
            id="media-alt"
            value={active.alt_text}
            disabled={!canManage || pending}
            onChange={(event) =>
              setDraft((current) => ({
                ...current,
                [localeTab]: {
                  ...current[localeTab],
                  alt_text: event.target.value,
                },
              }))
            }
          />
        </Field>
        <Field label="Caption" htmlFor="media-caption">
          <Textarea
            id="media-caption"
            className="min-h-20"
            value={active.caption}
            disabled={!canManage || pending}
            onChange={(event) =>
              setDraft((current) => ({
                ...current,
                [localeTab]: {
                  ...current[localeTab],
                  caption: event.target.value,
                },
              }))
            }
          />
        </Field>

        <FocalPointPicker
          previewUrl={previewUrl}
          width={attachment.width}
          height={attachment.height}
          focalPoint={focalPoint}
          disabled={!canManage || pending}
          onChange={setFocalPoint}
        />

        <div className="flex flex-wrap gap-2">
          {canManage ? (
            <Button type="submit" size="sm" disabled={pending}>
              {pending ? "Saving…" : "Save metadata"}
            </Button>
          ) : null}
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={pending}
            onClick={onCancel}
          >
            Close
          </Button>
        </div>
      </form>
    </AdminPanel>
  );
}
